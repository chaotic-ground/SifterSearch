'use strict';

// Called by mediawiki.page.ready once a search input is focused, because a
// SkinPageReadyConfig handler points skins that use core's searchSuggest at this
// module. The native jquery.suggestions widget, attached by searchSuggest (our
// dependency), is left intact; only its data source is swapped to Pagefind.
const {
	query, titleOf, MAX_RESULTS, fullText, resultsPageUrl, navigateToTopResultOnSubmit
} = require( 'ext.sifter.pagefind' );

// Page URL of each suggested title, as Pagefind gave it. Kept across queries
// because the widget re-renders cached suggestion lists without asking us again.
const pageUrls = new Map();

// Where a suggestion link should point, for a wiki with no full-text search:
// a title row at the page Pagefind found, as Vector's own typeahead does, and
// the "search for pages containing X" row at the results page, or nowhere when
// none is configured (it is hidden then).
function destinationFor( link ) {
	const special = link.querySelector( '.suggestions-special' );
	if ( !special ) {
		return pageUrls.get( link.textContent ) || null;
	}
	const queryEl = special.querySelector( '.special-query' );
	return resultsPageUrl( queryEl ? queryEl.textContent : '' );
}

// searchSuggest rebuilds the links on every keystroke, so watch the suggestion
// containers and overwrite each href as it is (re)written. The check makes our
// own write a no-op on the next callback, so it settles instead of looping.
function rewriteSuggestionLinks() {
	const rewrite = () => {
		for ( const link of document.querySelectorAll( '.mw-searchSuggest-link' ) ) {
			const dest = destinationFor( link );
			if ( dest && link.getAttribute( 'href' ) !== dest ) {
				link.setAttribute( 'href', dest );
			}
		}
	};
	const observer = new MutationObserver( rewrite );
	for ( const container of document.querySelectorAll( '.suggestions' ) ) {
		observer.observe( container, {
			subtree: true,
			childList: true,
			attributes: true,
			attributeFilter: [ 'href' ]
		} );
	}
	// Rows rendered before this ran, from a query typed while the module loaded.
	rewrite();
}

module.exports = {
	init: () => {
		mw.searchSuggest.request = ( api, term, response, limit ) => {
			let aborted = false;
			query( term, limit || MAX_RESULTS ).then( ( items ) => {
				if ( !aborted && items !== null ) {
					const titles = items.map( ( data ) => {
						const title = titleOf( data );
						pageUrls.set( title, data.url );
						return title;
					} );
					response( titles, { query: term } );
				}
			} );
			// searchSuggest calls .abort() on the previous request when cancelling.
			return { abort: () => {
				aborted = true;
			} };
		};

		// Core's suggestion links are built out of the search form, which on a
		// live wiki is MediaWiki's "Go": an exact title lands on the page and the
		// last row's &fulltext=1 reaches Special:Search. Neither is here, and
		// ext.sifter.retarget has aimed the form at the results page, so every
		// link would arrive there with the title as its query. Point them at
		// where they mean to go and route the submit the same way. Left alone on
		// a wiki that does answer a search: Pagefind's URLs come from the crawl
		// cache's layout (BuildIndexJob::cachePathForTitle) and need not be the
		// ones the wiki serves, whereas Go always is.
		if ( !fullText ) {
			rewriteSuggestionLinks();
			navigateToTopResultOnSubmit();
			if ( !resultsPageUrl() ) {
				mw.util.addCSS( '.suggestions-special { display: none; }' );
			}
		}
	}
};
