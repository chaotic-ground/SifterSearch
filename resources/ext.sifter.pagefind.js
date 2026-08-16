'use strict';

// Shared Pagefind access used by the per-skin search integrations. Skin-agnostic
// so it pulls in no search-UI dependencies of its own.

// Site-wide settings, embedded into the module by ClientConfig.
const config = require( './config.json' );

// Where the Pagefind index bundle is served from. Configurable because it need
// not sit at the site root.
const bundlePath = config.bundlePath || '/pagefind/';
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

// The URL of the configured full-results page for a query, or null when no
// results page is configured. The bare page URL comes from ClientConfig (it
// resolves to the static ./Page.html on a static export); the query is appended
// here because the static build drops it from server-generated URLs.
function resultsPageUrl( term ) {
	const url = config.resultsPageUrl;
	if ( !url ) {
		return null;
	}
	return term ? url + '?search=' + encodeURIComponent( term ) : url;
}

// When there is no full-text search page, the search form would submit to the
// wiki's (absent) Special:Search. Intercept the submit and go to the results
// page if one is configured, otherwise to the top Pagefind result for the typed
// query. Skin-agnostic: any search form carries an input[name="search"] (Vector's
// Codex input and the legacy box alike). Selecting a suggestion is a separate
// code path, so it is unaffected.
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
		const dest = resultsPageUrl( term );
		if ( dest ) {
			window.location.assign( dest );
			return;
		}
		query( term, 1 ).then( ( items ) => {
			if ( items && items.length ) {
				window.location.assign( items[ 0 ].url );
			}
		} );
	}, true );
}

// Adapt Pagefind results to the typeahead SearchClient shape; see
// mediawiki.skinning.typeaheadSearch/restSearchClient.js. App.vue only requires
// fetchByTitle (fetchRecommendationByTitle and loadMore are optional and
// guarded), so omitting them keeps the search fully client-side.
function fetchByTitle( term, limit, showDescription ) {
	const fetch = query( term, limit || MAX_RESULTS ).then( ( items ) => ( {
		query: term,
		results: ( items || [] ).map( ( data, index ) => ( {
			id: index,
			value: index,
			key: data.url,
			label: titleOf( data ),
			title: titleOf( data ),
			description: showDescription ? textOf( data.excerpt ) : undefined,
			url: data.url
		} ) )
	} ) );
	return { fetch, abort: () => {} };
}

// Mount a skin's Codex typeahead on Pagefind. Vector 2022 and Minerva both wrap
// core's mediawiki.skinning.typeaheadSearch app and take the same two optional
// arguments, a search client and a URL generator, which is the documented
// extension point (used by Wikidata) and avoids the deprecated
// wgVectorSearchClient config var.
function initTypeahead( skinSearch ) {
	if ( config.fullText ) {
		skinSearch.init( { fetchByTitle } );
		return;
	}
	// No native full-text search page. Point the "search for pages containing X"
	// footer at the configured results page (App.vue passes the query to
	// generateUrl), or hide it when none is configured (App.vue only renders the
	// footer for a non-empty URL). The form submit is routed the same way.
	skinSearch.init( { fetchByTitle }, { generateUrl: ( q ) => resultsPageUrl( q ) || '' } );
	navigateToTopResultOnSubmit();
}

module.exports = {
	MAX_RESULTS,
	bundlePath,
	fullText: config.fullText,
	query,
	titleOf,
	resultsPageUrl,
	navigateToTopResultOnSubmit,
	initTypeahead
};
