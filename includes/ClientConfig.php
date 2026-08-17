<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\Title\Title;

/**
 * Site-wide client configuration, embedded into the modules as a virtual
 * config.json packageFile. These values do not vary by page, so shipping them
 * with the module (versioned with it) beats repeating them in every page's
 * HTML as JS config vars.
 */
class ClientConfig {

	/**
	 * @param Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function forModules( Context $context, Config $config ): array {
		$resultsPage = $config->get( 'SifterSearchResultsPage' );
		$resultsTitle = $resultsPage !== '' ? Title::newFromText( $resultsPage ) : null;
		$bundlePath = $config->get( 'SifterSearchBundlePath' );
		return [
			'bundlePath' => $bundlePath,
			'fullText' => $config->get( 'SifterSearchFullText' ),
			// The bare page URL (no query); the client appends ?search=, since a
			// static export drops the query from server-generated URLs. Anchored,
			// because one module serves pages sitting at every depth.
			'resultsPageUrl' => $resultsTitle
				? self::anchored( $resultsTitle->getLocalURL(), $bundlePath )
				: null,
			// The page title, for repointing the search form's hidden title input.
			'resultsPageTitle' => $resultsTitle ? $resultsTitle->getPrefixedText() : null,
			// Whether a result's own URL is the URL this wiki serves it at, which
			// depends on the article path (see IndexUrl). Where it is not, the
			// client builds the URL from the result's title instead.
			'indexUrlsAreServed' => self::indexUrlsAreServed(),
		];
	}

	/**
	 * A local URL that means the same thing read from any page.
	 *
	 * Title::getLocalURL() normally answers with one -- /wiki/Search,
	 * /index.php?title=Search -- and such a URL is returned untouched. A host
	 * that exports the wiki as static files can answer with a document-relative
	 * one instead (wikven writes ./Search.html and corrects the depth per page,
	 * in the HTML alone), and that is not a value a single module shared by
	 * pages at every depth can carry: read from /Deploying/ko.html it points at
	 * /Deploying/Search.html, which is not where the page is.
	 *
	 * The bundle path is the one setting here that is site-root-anchored by
	 * construction -- the client loads the bundle by it from every page -- so
	 * where it is, its parent directory is the site root, and a relative URL
	 * can be anchored there once. Where it is not (a bundle served from another
	 * host, say), the site root is not known and the URL is left as it came.
	 */
	public static function anchored( string $url, string $bundlePath ): string {
		// Already root-relative, protocol-relative or absolute: nothing to do. A
		// relative URL whose first segment carries a colon (Help:Search.html) is
		// read as absolute here, exactly as a browser reads it: the "./" is what
		// tells the two apart, and a host writing relative URLs writes it.
		if ( $url === '' || str_starts_with( $url, '/' ) ||
			preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $url )
		) {
			return $url;
		}
		// Only a path anchored at this site's root says where that root is. A
		// bundle on another host -- protocol-relative, or with a scheme -- says
		// nothing about it, so the URL is left as it came.
		if ( !str_starts_with( $bundlePath, '/' ) || str_starts_with( $bundlePath, '//' ) ) {
			return $url;
		}
		// The bundle path's parent, cut as the string it is: dirname() answers
		// with a filesystem path, whose root is a backslash on Windows.
		$path = rtrim( $bundlePath, '/' );
		$cut = strrpos( $path, '/' );
		$root = $cut === false ? '/' : substr( $path, 0, $cut + 1 );
		return $root . preg_replace( '#^(\./)+#', '', $url );
	}

	/**
	 * Asked of a title that need not exist: the article path is what decides
	 * this, and it is the same for every page in the namespace.
	 */
	private static function indexUrlsAreServed(): bool {
		$sample = Title::makeTitle( NS_MAIN, 'SifterSearch' );
		return IndexUrl::isServed( $sample->getLocalURL(), $sample->getArticleID() );
	}
}
