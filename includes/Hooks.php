<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\Revision\Hook\RevisionRecordInsertedHook;
use MediaWiki\Skins\Hook\SkinPageReadyConfigHook;
use MediaWiki\Title\Title;

/**
 * Hook handlers for SifterSearch.
 */
class Hooks implements
	BeforePageDisplayHook,
	ResourceLoaderRegisterModulesHook,
	SkinPageReadyConfigHook,
	RevisionRecordInsertedHook,
	PageDeleteCompleteHook
{

	/** Per-skin typeahead adapters, each keyed by the skin search module it mounts. */
	private const SKIN_ADAPTERS = [
		'ext.sifter.vector' => 'skins.vector.search',
		'ext.sifter.minerva' => 'skins.minerva.search',
	];

	private JobQueueGroup $jobQueueGroup;
	private Config $config;

	public function __construct( JobQueueGroup $jobQueueGroup, Config $config ) {
		$this->jobQueueGroup = $jobQueueGroup;
		$this->config = $config;
	}

	/**
	 * Queue the page-dependent modules. Site-wide client config ships inside the
	 * modules themselves (see ClientConfig). The search module is not queued
	 * here either; it is loaded on demand when a search input is focused, wired
	 * in via onSkinPageReadyConfig(). The results-UI module is queued only on
	 * the configured results page.
	 *
	 * @param \MediaWiki\Output\OutputPage $out
	 * @param \Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$resultsPage = $this->config->get( 'SifterSearchResultsPage' );
		$resultsTitle = $resultsPage !== '' ? Title::newFromText( $resultsPage ) : null;
		if ( !$resultsTitle ) {
			return;
		}

		// Retarget the search form at the results page on every page, so a
		// plain submit reaches SifterSearch even before the on-focus typeahead
		// module loads (which otherwise 404s on a static export).
		$out->addModules( 'ext.sifter.retarget' );
		if ( $out->getTitle() && $out->getTitle()->equals( $resultsTitle ) ) {
			// Flag the results page so ext.sifter.results only mounts here. A
			// static export (e.g. wikven) bundles every module into one
			// self-executing file, so the module's code runs on every page and
			// must gate on this rather than on having been added. Page-dependent,
			// so it stays a JS config var rather than joining ClientConfig.
			$out->addJsConfigVars( 'wgSifterSearchOnResultsPage', true );
			$out->addModules( 'ext.sifter.results' );
			// The results page opts out of indexing, which files it under the
			// "Noindexed pages" tracking category; that footer is noise on a
			// search page, so drop its category links before they render.
			$out->setCategoryLinks( [] );
		}
	}

	/**
	 * Register the per-skin adapters here rather than in extension.json: each
	 * depends on its skin's own search module, which exists only where that skin
	 * is installed, and a module whose dependency is absent is an error (core's
	 * ResourcesTest fails on it).
	 *
	 * @param RL\ResourceLoader $resourceLoader
	 */
	public function onResourceLoaderRegisterModules( RL\ResourceLoader $resourceLoader ): void {
		foreach ( self::SKIN_ADAPTERS as $module => $skinModule ) {
			if ( !$resourceLoader->isModuleRegistered( $skinModule ) ) {
				continue;
			}
			$resourceLoader->register( $module, [
				'localBasePath' => __DIR__ . '/../resources',
				'remoteExtPath' => 'SifterSearch/resources',
				'packageFiles' => [ "$module.js" ],
				'dependencies' => [ 'ext.sifter.pagefind', $skinModule ],
			] );
		}
	}

	/**
	 * Redirect a skin's search module to ours so the native search box is fed
	 * Pagefind results instead of querying a backend:
	 *  - mediawiki.searchSuggest (legacy skins) -> ext.sifter, which reuses the
	 *    native jquery.suggestions widget.
	 *  - skins.vector.search (Vector 2022 Codex typeahead) -> ext.sifter.vector,
	 *    which mounts Vector's own search app with a Pagefind search client.
	 *  - skins.minerva.search (Minerva's Codex typeahead) -> ext.sifter.minerva,
	 *    the same for Minerva, whose own module queries the REST search API.
	 *
	 * @param RL\Context $context
	 * @param mixed[] &$config
	 */
	public function onSkinPageReadyConfig( RL\Context $context, array &$config ): void {
		$ours = array_flip( self::SKIN_ADAPTERS );
		// The legacy widget is core's own, so unlike the adapters it is always there.
		$ours['mediawiki.searchSuggest'] = 'ext.sifter';
		$searchModule = (string)( $config['searchModule'] ?? '' );
		if ( isset( $ours[$searchModule] ) ) {
			$config['searchModule'] = $ours[$searchModule];
		}
	}

	/**
	 * Triggers on any revision insert, so it covers both live edits and the
	 * old-revision imports a static-site build uses (which bypass the edit path).
	 *
	 * @param \MediaWiki\Revision\RevisionRecord $revisionRecord
	 */
	public function onRevisionRecordInserted( $revisionRecord ) {
		if ( $this->isIndexed( $revisionRecord->getPage()->getNamespace() ) ) {
			$this->enqueueRebuild();
		}
	}

	/**
	 * @param \MediaWiki\Page\ProperPageIdentity $page
	 * @param \MediaWiki\Permissions\Authority $deleter
	 * @param string $reason
	 * @param int $pageID
	 * @param \MediaWiki\Revision\RevisionRecord $deletedRev
	 * @param \ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount
	 */
	public function onPageDeleteComplete(
		$page, $deleter, $reason, $pageID, $deletedRev, $logEntry, $archivedRevisionCount
	) {
		if ( $this->isIndexed( $page->getNamespace() ) ) {
			$this->enqueueRebuild();
		}
	}

	private function isIndexed( int $namespace ): bool {
		return in_array( $namespace, $this->config->get( 'SifterSearchNamespaces' ), true );
	}

	/**
	 * Queue a rebuild. The job de-duplicates, so a burst of changes collapses to
	 * a single rebuild that picks up everything changed since the last run. An
	 * optional delay coalesces bursts into one batch where the queue supports it.
	 */
	private function enqueueRebuild(): void {
		$delay = (int)$this->config->get( 'SifterSearchBatchSeconds' );
		$params = $delay > 0 ? [ 'jobReleaseTimestamp' => time() + $delay ] : [];
		$this->jobQueueGroup->push( new BuildIndexJob( $params ) );
	}
}
