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

// Where to send a reader for a result. Pagefind's own URL for it comes from
// where the page's HTML sat in the crawl cache, which is the URL the wiki serves
// only where the article path is a file path, e.g. a static export's ./Foo.html.
// A pretty URL (/wiki/Foo) comes back with a trailing slash, which is a
// different title, and a query-string one comes back as a slug that is served
// nowhere. So where the two differ, build the URL from the title, exactly as the
// wiki would (ClientConfig settles which case this site is).
function urlOf( data ) {
	const title = data.meta && data.meta.title;
	if ( config.indexUrlsAreServed || !title ) {
		return data.url;
	}
	return mw.util.getUrl( title );
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
	if ( !term ) {
		return url;
	}
	// A wiki without pretty URLs answers with a page URL that carries a query of
	// its own (/index.php?title=Results), and a second "?" would be read as part
	// of the title rather than as a separator. ext.sifter.retarget splits that
	// same query off the search form's action for the same reason. Joined here
	// rather than dropped: index.php routes by the title param, a search param
	// alongside it notwithstanding, on the 1.45+ this extension requires.
	const separator = url.includes( '?' ) ? '&' : '?';
	return url + separator + 'search=' + encodeURIComponent( term );
}

// When there is no full-text search page, the search form would submit to the
// wiki's (absent) Special:Search. Intercept the submit and go to the results
// page if one is configured, otherwise to the top Pagefind result for the typed
// query. Skin-agnostic: any search form carries an input[name="search"] (Vector's
// Codex input and the legacy box alike). Selecting a suggestion is a separate
// code path, so it is unaffected. Hooked once: Minerva's search dialog is a
// route, so page.ready re-runs the search module's init() on every visit to it.
let submitHooked = false;
function navigateToTopResultOnSubmit() {
	if ( submitHooked ) {
		return;
	}
	submitHooked = true;
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
				window.location.assign( urlOf( items[ 0 ] ) );
			}
		} );
	}, true );
}

// Adapt Pagefind results to the typeahead SearchClient shape; see
// mediawiki.skinning.typeaheadSearch/restSearchClient.js. App.vue only requires
// fetchByTitle (fetchRecommendationByTitle and loadMore are optional and
// guarded), so omitting them keeps the search fully client-side.
function fetchByTitle( term, limit, showDescription ) {
	const fetch = query( term, limit || MAX_RESULTS ).then( ( items ) => {
		if ( items === null ) {
			// Superseded by a newer query, which is not the same as no results:
			// answering with an empty list here would empty a dropdown that has
			// matches in it, since App.vue accepts any answer whose term is still
			// the one in the box. Never settling leaves the display alone and
			// lets the newer query be the one that fills it.
			return new Promise( () => {} );
		}
		return {
			query: term,
			results: items.map( ( data, index ) => ( {
				id: index,
				value: index,
				key: data.url,
				label: titleOf( data ),
				title: titleOf( data ),
				description: showDescription ? textOf( data.excerpt ) : undefined,
				url: urlOf( data )
			} ) )
		};
	} );
	return { fetch, abort: () => {} };
}

// Mount a skin's Codex typeahead on Pagefind. Vector 2022 and Minerva both wrap
// core's mediawiki.skinning.typeaheadSearch app and take the same two optional
// arguments, a search client and a URL generator, which is the documented
// extension point (used by Wikidata) and avoids the deprecated
// wgVectorSearchClient config var.
function initTypeahead( skinSearch ) {
	if ( config.fullText && !resultsPageUrl() ) {
		// The wiki answers a full-text search itself, and there is nowhere else to
		// send one, so the skin's own "search for pages containing X" footer stands.
		skinSearch.init( { fetchByTitle } );
		return;
	}
	// A results page is where a full-text search goes, whether or not the wiki has
	// one of its own: configuring it is what says so, and ext.sifter.retarget has
	// already sent the form there. App.vue passes the query to generateUrl, and
	// renders the footer only for a non-empty URL, so with no results page and no
	// full-text search the footer goes away.
	skinSearch.init( { fetchByTitle }, { generateUrl: ( q ) => resultsPageUrl( q ) || '' } );
	if ( !config.fullText ) {
		navigateToTopResultOnSubmit();
	}
}

module.exports = {
	MAX_RESULTS,
	bundlePath,
	fullText: config.fullText,
	query,
	titleOf,
	urlOf,
	resultsPageUrl,
	navigateToTopResultOnSubmit,
	initTypeahead
};
