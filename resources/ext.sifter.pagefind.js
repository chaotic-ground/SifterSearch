'use strict';

// Shared Pagefind access used by the per-skin search integrations. Skin-agnostic
// so it pulls in no search-UI dependencies of its own.

// Where the Pagefind index bundle is served from. Configurable because it need
// not sit at the site root.
const bundlePath = mw.config.get( 'wgSifterSearchBundlePath' ) || '/pagefind/';
const MAX_RESULTS = 10;

let pagefindPromise = null;
function loadPagefind() {
	if ( !pagefindPromise ) {
		// Pagefind is published only as an ES module, so load it dynamically.
		// eslint-disable-next-line es-x/no-dynamic-import
		pagefindPromise = import( bundlePath + 'pagefind.js' );
	}
	return pagefindPromise;
}

// Run a query and resolve to its result data, or null when a newer query
// supersedes this one (Pagefind debounces internally).
function query( term, limit ) {
	return loadPagefind()
		.then( ( pagefind ) => pagefind.debouncedSearch( term ) )
		.then( ( found ) => {
			if ( found === null ) {
				return null;
			}
			return Promise.all(
				found.results.slice( 0, limit ).map( ( result ) => result.data() )
			);
		} );
}

function titleOf( data ) {
	return ( data.meta && data.meta.title ) || data.url;
}

// Pagefind excerpts are HTML with <mark> highlights; flatten to text without
// assigning innerHTML, for UIs that show plain-text descriptions.
function textOf( html ) {
	return new DOMParser().parseFromString( html, 'text/html' ).body.textContent;
}

// When there is no full-text search page, the search form would submit to the
// wiki's (absent) Special:Search. Intercept the submit and go to the top
// Pagefind result for the typed query instead. Skin-agnostic: any search form
// carries an input[name="search"] (Vector's Codex input and the legacy box
// alike). Selecting a suggestion is a separate code path, so it is unaffected.
function navigateToTopResultOnSubmit() {
	document.addEventListener( 'submit', ( e ) => {
		const form = e.target;
		const input = form.querySelector &&
			form.querySelector( 'input[name="search"], #searchInput' );
		if ( !input ) {
			return;
		}
		e.preventDefault();
		const term = input.value.trim();
		if ( !term ) {
			return;
		}
		query( term, 1 ).then( ( items ) => {
			if ( items && items.length ) {
				window.location.assign( items[ 0 ].url );
			}
		} );
	}, true );
}

module.exports = { MAX_RESULTS, query, titleOf, textOf, navigateToTopResultOnSubmit };
