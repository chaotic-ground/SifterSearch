<?php

namespace MediaWiki\Extension\SifterSearch;

/**
 * The crawl cache's layout, and the result URLs Pagefind derives from it.
 *
 * Pagefind has no notion of a wiki: it indexes a directory of HTML files and
 * reports each result's URL as its path below the crawl root, with index.html
 * folded away. So a page's cache path is chosen to land on the URL the wiki
 * serves, which is possible where the article path is a file path (a static
 * export's ./Foo.html) and not where it is a directory-style pretty URL
 * (/wiki/Foo, which no .html file can produce) or a query string.
 *
 * isServed() is how the client learns which of the two it is looking at.
 */
class IndexUrl {

	/** Cache-relative path to crawl for a page served at this local URL. */
	public static function pathFor( string $localUrl, int $pageId ): string {
		// Query-string URLs (no rewriting) cannot map to a file path.
		if ( strpos( $localUrl, '?' ) !== false ) {
			return "sifter/$pageId/index.html";
		}
		// Strip any leading "./" or "/" so the path is relative to the cache root.
		$path = ltrim( (string)parse_url( $localUrl, PHP_URL_PATH ), './' );
		if ( $path === '' ) {
			return "sifter/$pageId/index.html";
		}
		// A URL already pointing at a file (e.g. wikven's ./Foo.html) keeps its
		// path; a directory-style URL (/wiki/Foo) gets an index.html.
		if ( preg_match( '/\.html?$/i', $path ) ) {
			return $path;
		}
		return rtrim( $path, '/' ) . '/index.html';
	}

	/** The result URL Pagefind computes for a crawled path. */
	public static function urlFor( string $path ): string {
		$path = preg_replace( '#(^|/)index\.html$#', '$1', $path );
		return '/' . $path;
	}

	/**
	 * Whether a page's result URL is the URL the wiki serves it at, give or take
	 * the leading "./" a relative article path carries.
	 */
	public static function isServed( string $localUrl, int $pageId ): bool {
		if ( strpos( $localUrl, '?' ) !== false ) {
			return false;
		}
		$indexed = self::urlFor( self::pathFor( $localUrl, $pageId ) );
		return ltrim( $indexed, '/' ) === ltrim( $localUrl, './' );
	}
}
