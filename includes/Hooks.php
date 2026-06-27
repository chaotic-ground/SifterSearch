<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Revision\Hook\RevisionRecordInsertedHook;
use MediaWiki\Skins\Hook\SkinPageReadyConfigHook;
use MediaWiki\Title\Title;

/**
 * Hook handlers for SifterSearch.
 */
class Hooks implements
	BeforePageDisplayHook,
	SkinPageReadyConfigHook,
	RevisionRecordInsertedHook,
	PageDeleteCompleteHook
{

	private JobQueueGroup $jobQueueGroup;
	private Config $config;

	public function __construct( JobQueueGroup $jobQueueGroup, Config $config ) {
		$this->jobQueueGroup = $jobQueueGroup;
		$this->config = $config;
	}

	/**
	 * Expose the client config. The search module itself is not queued here; it
	 * is loaded on demand when a search input is focused, wired in via
	 * onSkinPageReadyConfig(). The results-UI module is queued only on the
	 * configured results page.
	 *
	 * @param \MediaWiki\Output\OutputPage $out
	 * @param \Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$vars = [
			'wgSifterSearchBundlePath' => $this->config->get( 'SifterSearchBundlePath' ),
			'wgSifterSearchFullText' => $this->config->get( 'SifterSearchFullText' ),
		];

		$resultsPage = $this->config->get( 'SifterSearchResultsPage' );
		$resultsTitle = $resultsPage !== '' ? Title::newFromText( $resultsPage ) : null;
		if ( $resultsTitle ) {
			// The bare page URL (no query); the client appends ?search=, since a
			// static export drops the query from server-generated URLs.
			$vars['wgSifterSearchResultsPageUrl'] = $resultsTitle->getLocalURL();
			// Retarget the search form at the results page on every page, so a
			// plain submit reaches SifterSearch even before the on-focus typeahead
			// module loads (which otherwise 404s on a static export).
			$out->addModules( 'ext.sifter.retarget' );
			if ( $out->getTitle() && $out->getTitle()->equals( $resultsTitle ) ) {
				// Flag the results page so ext.sifter.results only mounts here. A
				// static export (e.g. wikven) bundles every module into one
				// self-executing file, so the module's code runs on every page and
				// must gate on this rather than on having been added.
				$vars['wgSifterSearchOnResultsPage'] = true;
				$out->addModules( 'ext.sifter.results' );
			}
		}

		$out->addJsConfigVars( $vars );
	}

	/**
	 * Redirect a skin's search module to ours so the native search box is fed
	 * Pagefind results instead of querying a backend:
	 *  - mediawiki.searchSuggest (legacy skins) -> ext.sifter, which reuses the
	 *    native jquery.suggestions widget.
	 *  - skins.vector.search (Vector 2022 Codex typeahead) -> ext.sifter.vector,
	 *    which mounts Vector's own search app with a Pagefind search client.
	 *
	 * @param RL\Context $context
	 * @param mixed[] &$config
	 */
	public function onSkinPageReadyConfig( RL\Context $context, array &$config ): void {
		$searchModule = $config['searchModule'] ?? null;
		if ( $searchModule === 'mediawiki.searchSuggest' ) {
			$config['searchModule'] = 'ext.sifter';
		} elseif ( $searchModule === 'skins.vector.search' ) {
			$config['searchModule'] = 'ext.sifter.vector';
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
