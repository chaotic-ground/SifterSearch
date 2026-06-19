<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Revision\Hook\RevisionRecordInsertedHook;

/**
 * Hook handlers for SifterSearch.
 */
class Hooks implements BeforePageDisplayHook, RevisionRecordInsertedHook, PageDeleteCompleteHook {

	private JobQueueGroup $jobQueueGroup;
	private Config $config;

	public function __construct( JobQueueGroup $jobQueueGroup, Config $config ) {
		$this->jobQueueGroup = $jobQueueGroup;
		$this->config = $config;
	}

	/**
	 * Load the client-side search UI on every page, and expose the configured
	 * bundle path to it. The module is queued unconditionally so a static
	 * exporter that bundles a page's modules picks it up without extra wiring.
	 *
	 * @param \MediaWiki\Output\OutputPage $out
	 * @param \Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addJsConfigVars(
			'wgSifterSearchBundlePath',
			$this->config->get( 'SifterSearchBundlePath' )
		);
		$out->addModules( 'ext.sifter' );
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
	public function onPageDeleteComplete( $page, $deleter, $reason, $pageID, $deletedRev, $logEntry, $archivedRevisionCount ) {
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
