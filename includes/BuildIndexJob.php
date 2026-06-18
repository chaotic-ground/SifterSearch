<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\JobQueue\GenericParameterJob;
use MediaWiki\JobQueue\Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Shell\Shell;
use MediaWiki\Title\Title;

/**
 * Rebuild the Pagefind search index from the current wiki content.
 *
 * The index is built from page content (not by crawling output HTML), so the
 * same job serves a live wiki and a static export: the records come from the
 * parser, and Pagefind's Node indexer turns them into the static bundle.
 */
class BuildIndexJob extends Job implements GenericParameterJob {

	public function __construct( array $params = [] ) {
		parent::__construct( 'sifterSearchBuildIndex', $params );
		// Collapse a burst of edits (or a bulk import) into one rebuild.
		$this->removeDuplicates = true;
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		$outputDir = $config->get( 'SifterSearchOutputDir' );
		if ( $outputDir === '' ) {
			$this->setLastError( 'SifterSearchOutputDir is not configured' );
			return false;
		}

		$records = $this->collectRecords( $services, $config->get( 'SifterSearchNamespaces' ) );

		// Hand the records to the Node indexer via a temp file. Pagefind's
		// indexing API (addHTMLFile) does its own HTML text extraction, so we
		// pass a rendered HTML document per page rather than pre-stripped text.
		$tmp = tempnam( wfTempDir(), 'sifter-records-' );
		file_put_contents( $tmp, json_encode( $records ) );

		$script = __DIR__ . '/../scripts/build-index.mjs';
		$result = Shell::command(
			$config->get( 'SifterSearchNodePath' ),
			$script,
			'--records', $tmp,
			'--output', $outputDir
		)->execute();
		unlink( $tmp );

		if ( $result->getExitCode() !== 0 ) {
			$this->setLastError( 'Pagefind indexer failed: ' . $result->getStderr() );
			return false;
		}

		return true;
	}

	/**
	 * @param MediaWikiServices $services
	 * @param int[] $namespaces
	 * @return array<int,array{url:string,content:string}>
	 */
	private function collectRecords( MediaWikiServices $services, array $namespaces ): array {
		$dbr = $services->getConnectionProvider()->getReplicaDatabase();
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [ 'page_namespace' => $namespaces, 'page_is_redirect' => 0 ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$wikiPageFactory = $services->getWikiPageFactory();
		$parserOptions = ParserOptions::newFromAnon();
		$lang = $services->getContentLanguage()->getHtmlCode();

		$records = [];
		foreach ( $res as $row ) {
			$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			$page = $wikiPageFactory->newFromTitle( $title );
			$parserOutput = $page->getParserOutput( $parserOptions );
			if ( !$parserOutput ) {
				continue;
			}
			$records[] = [
				// getLocalURL is correct for a live wiki; a static exporter whose
				// output paths differ may need to remap this (future refinement).
				'url' => $title->getLocalURL(),
				'content' => $this->wrapHtml( $lang, $title, $parserOutput->getText() ),
			];
		}
		return $records;
	}

	private function wrapHtml( string $lang, Title $title, string $bodyHtml ): string {
		$titleText = htmlspecialchars( $title->getPrefixedText() );
		return '<!DOCTYPE html><html lang="' . htmlspecialchars( $lang ) . '">'
			. "<head><title>$titleText</title></head>"
			. "<body><h1>$titleText</h1>"
			. "<div data-pagefind-body>$bodyHtml</div>"
			. '</body></html>';
	}
}
