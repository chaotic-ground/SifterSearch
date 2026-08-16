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
		return [
			'bundlePath' => $config->get( 'SifterSearchBundlePath' ),
			'fullText' => $config->get( 'SifterSearchFullText' ),
			// The bare page URL (no query); the client appends ?search=, since a
			// static export drops the query from server-generated URLs.
			'resultsPageUrl' => $resultsTitle ? $resultsTitle->getLocalURL() : null,
			// The page title, for repointing the search form's hidden title input.
			'resultsPageTitle' => $resultsTitle ? $resultsTitle->getPrefixedText() : null,
			// Whether a result's own URL is the URL this wiki serves it at, which
			// depends on the article path (see IndexUrl). Where it is not, the
			// client builds the URL from the result's title instead.
			'indexUrlsAreServed' => self::indexUrlsAreServed(),
		];
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
