<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;

/**
 * Hook handlers for SifterSearch.
 */
class Hooks implements BeforePageDisplayHook, PageSaveCompleteHook {

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
	 * Queue an index rebuild when an indexed page changes. The job dedups, so a
	 * burst of edits (or a bulk import) collapses to a single rebuild. On a
	 * static build the queue is drained by the build's runJobs step, so this
	 * extension never has to know about the build pipeline.
	 *
	 * @param \WikiPage $wikiPage
	 * @param \MediaWiki\User\UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param \MediaWiki\Revision\RevisionRecord $revisionRecord
	 * @param \MediaWiki\Storage\EditResult $editResult
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		$namespaces = $this->config->get( 'SifterSearchNamespaces' );
		if ( in_array( $wikiPage->getTitle()->getNamespace(), $namespaces, true ) ) {
			$this->jobQueueGroup->push( new BuildIndexJob() );
		}
	}
}
