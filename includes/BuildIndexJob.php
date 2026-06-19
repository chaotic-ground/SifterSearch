<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use MediaWiki\JobQueue\GenericParameterJob;
use MediaWiki\JobQueue\Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Shell\Shell;
use MediaWiki\Title\Title;
use RuntimeException;

/**
 * Rebuild the Pagefind search index from the current wiki content.
 *
 * The index is built from page content (not by crawling a deployed site), so
 * the same job serves a live wiki and a static export. Rendered HTML is kept in
 * a cache directory between runs and only re-rendered for pages that changed,
 * so each run is incremental; the (cheap) Pagefind pass then rebuilds the whole
 * bundle from the cache.
 */
class BuildIndexJob extends Job implements GenericParameterJob {

	public function __construct( array $params = [] ) {
		parent::__construct( 'sifterSearchBuildIndex', $params );
		// Collapse a burst of edits (or a bulk import) into one rebuild.
		$this->removeDuplicates = true;
	}

	/**
	 * @inheritDoc
	 */
	public function getDeduplicationInfo() {
		$info = parent::getDeduplicationInfo();
		// A batch delay must not make otherwise-identical jobs look distinct.
		if ( isset( $info['params']['jobReleaseTimestamp'] ) ) {
			unset( $info['params']['jobReleaseTimestamp'] );
		}
		return $info;
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		$bundleDir = $config->get( 'SifterSearchOutputDir' );
		if ( $bundleDir === '' ) {
			$this->setLastError( 'SifterSearchOutputDir is not configured' );
			return false;
		}

		$cacheDir = $this->resolveCacheDir( $config );
		if ( $cacheDir === null ) {
			$this->setLastError( 'SifterSearch: set $wgSifterSearchCacheDir or $wgCacheDirectory' );
			return false;
		}

		try {
			$binary = Pagefind::resolveBinaryPath( $config );
		} catch ( RuntimeException $e ) {
			$this->setLastError( $e->getMessage() );
			return false;
		}

		if ( !wfMkdirParents( $cacheDir ) ) {
			$this->setLastError( "SifterSearch: cannot create cache directory $cacheDir" );
			return false;
		}

		$this->syncCache( $services, $config, $cacheDir );

		$result = Shell::command( $binary, '--site', $cacheDir, '--output-path', $bundleDir )
			->execute();
		if ( $result->getExitCode() !== 0 ) {
			$this->setLastError( 'Pagefind failed: ' . $result->getStderr() );
			return false;
		}

		return true;
	}

	private function resolveCacheDir( Config $config ): ?string {
		$dir = $config->get( 'SifterSearchCacheDir' );
		if ( $dir !== '' ) {
			return rtrim( $dir, '/' );
		}
		$cacheDir = $config->get( 'CacheDirectory' );
		if ( $cacheDir ) {
			return rtrim( $cacheDir, '/' ) . '/sifter-search';
		}
		return null;
	}

	/**
	 * Re-render only the pages that changed since the last run, drop pages that
	 * were deleted or moved, and record state in a manifest the next run reads.
	 */
	private function syncCache( MediaWikiServices $services, Config $config, string $cacheDir ): void {
		$manifestPath = $cacheDir . '/sifter-manifest.json';
		$manifest = is_file( $manifestPath )
			? ( json_decode( file_get_contents( $manifestPath ), true ) ?: [] )
			: [];

		$dbr = $services->getConnectionProvider()->getReplicaDatabase();
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title', 'page_touched' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => $config->get( 'SifterSearchNamespaces' ),
				'page_is_redirect' => 0,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$wikiPageFactory = $services->getWikiPageFactory();
		$parserOptions = ParserOptions::newFromAnon();
		$lang = $services->getContentLanguage()->getHtmlCode();

		$seen = [];
		foreach ( $res as $row ) {
			$id = (int)$row->page_id;
			$seen[$id] = true;
			if ( isset( $manifest[$id] ) && $manifest[$id]['touched'] === $row->page_touched ) {
				// Unchanged since the last run: keep the cached HTML as-is.
				continue;
			}

			$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			$page = $wikiPageFactory->newFromTitle( $title );
			$parserOutput = $page->getParserOutput( $parserOptions );
			if ( !$parserOutput ) {
				continue;
			}

			$relPath = $this->cachePathForTitle( $title );
			// If the page moved, its old cache file would otherwise linger.
			if ( isset( $manifest[$id]['file'] ) && $manifest[$id]['file'] !== $relPath ) {
				$this->removeCacheFile( $cacheDir, $manifest[$id]['file'] );
			}
			$this->writeCacheFile(
				$cacheDir,
				$relPath,
				$this->wrapHtml( $lang, $title, $parserOutput->getContentHolderText() )
			);
			$manifest[$id] = [ 'touched' => $row->page_touched, 'file' => $relPath ];
		}

		// Drop pages that no longer exist (deleted, or now redirects).
		foreach ( array_keys( $manifest ) as $id ) {
			if ( !isset( $seen[$id] ) ) {
				$this->removeCacheFile( $cacheDir, $manifest[$id]['file'] );
				unset( $manifest[$id] );
			}
		}

		file_put_contents( $manifestPath, json_encode( $manifest ) );
	}

	/**
	 * Map a page to a cache file path so the crawler computes a matching result
	 * URL. Pretty URLs (/wiki/Foo) become wiki/Foo/index.html, i.e. /wiki/Foo/.
	 * Query-string ($wgArticlePath without rewrites) URLs cannot map to a path
	 * and fall back to a slug whose URL will not match the live URL.
	 */
	private function cachePathForTitle( Title $title ): string {
		$url = $title->getLocalURL();
		// Query-string URLs (no rewriting) cannot map to a file path.
		if ( strpos( $url, '?' ) !== false ) {
			return 'sifter/' . $title->getArticleID() . '/index.html';
		}
		// Strip any leading "./" or "/" so the path is relative to the cache root.
		$path = ltrim( (string)parse_url( $url, PHP_URL_PATH ), './' );
		if ( $path === '' ) {
			return 'sifter/' . $title->getArticleID() . '/index.html';
		}
		// A URL already pointing at a file (e.g. wikven's ./Foo.html) keeps its
		// path; a directory-style URL (/wiki/Foo) gets an index.html so the
		// crawler's computed URL matches the served one.
		if ( preg_match( '/\.html?$/i', $path ) ) {
			return $path;
		}
		return rtrim( $path, '/' ) . '/index.html';
	}

	private function wrapHtml( string $lang, Title $title, string $bodyHtml ): string {
		$titleText = htmlspecialchars( $title->getPrefixedText() );
		return '<!DOCTYPE html><html lang="' . htmlspecialchars( $lang ) . '">'
			. "<head><title>$titleText</title></head>"
			. "<body><h1>$titleText</h1>"
			. "<div data-pagefind-body>$bodyHtml</div>"
			. '</body></html>';
	}

	private function writeCacheFile( string $cacheDir, string $relPath, string $html ): void {
		$full = $cacheDir . '/' . $relPath;
		wfMkdirParents( dirname( $full ) );
		file_put_contents( $full, $html );
	}

	private function removeCacheFile( string $cacheDir, string $relPath ): void {
		$full = $cacheDir . '/' . $relPath;
		if ( is_file( $full ) ) {
			unlink( $full );
		}
	}
}
