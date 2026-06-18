( function () {
	'use strict';

	// Where the Pagefind index bundle (its UI script/style and the index itself)
	// is served from. Configurable because it need not sit at the site root.
	var bundlePath = mw.config.get( 'wgSifterSearchBundlePath' ) || '/pagefind/';

	function mount() {
		// Pagefind ships its search UI as a standalone script/style pair living
		// next to the index. Load both from the bundle, then mount the widget.
		mw.loader.load( bundlePath + 'pagefind-ui.css', 'text/css' );

		var script = document.createElement( 'script' );
		script.src = bundlePath + 'pagefind-ui.js';
		script.onload = function () {
			var container = document.createElement( 'div' );
			container.id = 'sifter-search';

			// Provisional placement: top of the content area. Skin-aware
			// placement (next to the native search box) is a later refinement.
			var content = document.getElementById( 'mw-content-text' ) || document.body;
			content.insertBefore( container, content.firstChild );

			/* global PagefindUI */
			// eslint-disable-next-line no-new
			new PagefindUI( {
				element: '#sifter-search',
				bundlePath: bundlePath,
				showSubResults: true
			} );
		};
		document.head.appendChild( script );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mount );
	} else {
		mount();
	}
}() );
