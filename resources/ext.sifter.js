'use strict';

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

// Called by mediawiki.page.ready once a search input is focused, because a
// SkinPageReadyConfig handler points skins that use core's searchSuggest at this
// module. The native jquery.suggestions widget, attached by searchSuggest (our
// dependency), is left intact; only its data source is swapped to Pagefind.
//
// Vector 2022's Codex typeahead is a separate, backend-dependent path that does
// not work on a static export; tracked in
// https://github.com/chaotic-ground/SifterSearch/issues/8
module.exports = {
	init: () => {
		mw.searchSuggest.request = ( api, term, response, limit ) => {
			let aborted = false;
			query( term, limit || MAX_RESULTS ).then( ( items ) => {
				if ( !aborted && items !== null ) {
					response( items.map( titleOf ), { query: term } );
				}
			} );
			// searchSuggest calls .abort() on the previous request when cancelling.
			return { abort: () => {
				aborted = true;
			} };
		};
	}
};
