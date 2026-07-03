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
		];
	}
}
