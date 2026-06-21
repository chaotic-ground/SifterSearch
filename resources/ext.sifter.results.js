'use strict';

// Full search-results UI. A BeforePageDisplay handler loads this module only on
// the page named by $wgSifterSearchResultsPage. It mounts Pagefind's prebuilt
// PagefindUI widget and runs the query taken from the URL (?search=), so a
// static export gets a real "all results" page with no backend.
const { bundlePath } = require( 'ext.sifter.pagefind' );

function loadStyle() {
	const link = document.createElement( 'link' );
	link.rel = 'stylesheet';
	link.href = bundlePath + 'pagefind-ui.css';
	document.head.appendChild( link );
}

// pagefind-ui.js is an IIFE that sets window.PagefindUI (not an ES module), so
// load it with a script element rather than import().
function loadScript() {
	return new Promise( ( resolve, reject ) => {
		const script = document.createElement( 'script' );
		script.src = bundlePath + 'pagefind-ui.js';
		script.onload = resolve;
		script.onerror = reject;
		document.head.appendChild( script );
	} );
}

function mount() {
	const host = document.getElementById( 'mw-content-text' ) || document.body;
	const element = document.createElement( 'div' );
	element.id = 'sifter-results';
	host.prepend( element );

	loadStyle();
	loadScript().then( () => {
		const ui = new window.PagefindUI( {
			element: '#sifter-results',
			bundlePath: bundlePath,
			showSubResults: true,
			showImages: false
		} );
		const term = new URLSearchParams( window.location.search ).get( 'search' );
		if ( term ) {
			if ( typeof ui.triggerSearch === 'function' ) {
				ui.triggerSearch( term );
			} else {
				const input = element.querySelector( '.pagefind-ui__search-input' );
				if ( input ) {
					input.value = term;
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
			}
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
