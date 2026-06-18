( function () {
	'use strict';

	// Where the Pagefind index bundle (its UI script/style and the index itself)
	// is served from. Configurable because it need not sit at the site root.
	var bundlePath = mw.config.get( 'wgSifterSearchBundlePath' ) || '/pagefind/';

	// Place the search box for the active skin: next to the skin's own search
	// form when one can be found, otherwise at the top of the content area.
	function ensureContainer() {
		if ( document.getElementById( 'sifter-search' ) ) {
			return;
		}
		var container = document.createElement( 'div' );
		container.id = 'sifter-search';

		var host =
			document.getElementById( 'p-search' ) ||
			document.getElementById( 'searchform' ) ||
			document.querySelector( 'form[role="search"]' );
		if ( host && host.parentNode ) {
			host.parentNode.insertBefore( container, host.nextSibling );
			return;
		}

		var content = document.getElementById( 'mw-content-text' ) || document.body;
		content.insertBefore( container, content.firstChild );
	}

	function mount() {
		mw.loader.load( bundlePath + 'pagefind-ui.css', 'text/css' );

		var script = document.createElement( 'script' );
		script.src = bundlePath + 'pagefind-ui.js';
		script.onload = function () {
			ensureContainer();
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
