<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Revision\Hook\RevisionRecordInsertedHook;
use MediaWiki\Skins\Hook\SkinPageReadyConfigHook;

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
	 * Expose the configured bundle path to the client. The search module itself
	 * is not queued here; it is loaded on demand when a search input is focused,
	 * wired in via onSkinPageReadyConfig().
	 *
	 * @param \MediaWiki\Output\OutputPage $out
	 * @param \Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addJsConfigVars(
			'wgSifterSearchBundlePath',
			$this->config->get( 'SifterSearchBundlePath' )
		);
	}

	/**
	 * Skins whose search box is driven by core's mediawiki.searchSuggest are
	 * redirected to ext.sifter, which reuses that native suggestion widget but
	 * feeds it Pagefind results. Skins with their own search module (e.g. Vector
	 * 2022's Codex typeahead) are left untouched; see
	 * https://github.com/chaotic-ground/SifterSearch/issues/8
	 *
	 * @param RL\Context $context
	 * @param mixed[] &$config
	 */
	public function onSkinPageReadyConfig( RL\Context $context, array &$config ): void {
		if ( ( $config['searchModule'] ?? null ) === 'mediawiki.searchSuggest' ) {
			$config['searchModule'] = 'ext.sifter';
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
