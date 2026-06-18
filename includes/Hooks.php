<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Hook\BeforePageDisplayHook;

/**
 * Hook handlers for SifterSearch.
 */
class Hooks implements BeforePageDisplayHook {

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
			$out->getConfig()->get( 'SifterSearchBundlePath' )
		);
		$out->addModules( 'ext.sifter' );
	}
}
