'use strict';

// Called by mediawiki.page.ready once a search input is focused, because a
// SkinPageReadyConfig handler points skins that use core's searchSuggest at this
// module. The native jquery.suggestions widget, attached by searchSuggest (our
// dependency), is left intact; only its data source is swapped to Pagefind.
const {
	query, titleOf, urlOf, MAX_RESULTS, fullText, resultsPageUrl, navigateToTopResultOnSubmit
} = require( 'ext.sifter.pagefind' );

// Page URL of each suggested title. Kept across queries because the widget
// re-renders cached suggestion lists without asking us again.
const pageUrls = new Map();

// Where a suggestion link should point, once init() has decided that core's own
// href needs replacing: a title row at the page Pagefind found, as Vector's
// typeahead does, and the "search for pages containing X" row at the results
// page, or nowhere when none is configured (it is hidden then).
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
						pageUrls.set( title, urlOf( data ) );
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

		// Core builds the suggestion links out of the search form: on a wiki that
		// answers its own search they are MediaWiki's "Go", with the last row's
		// &fulltext=1 reaching Special:Search. ext.sifter.retarget aims the form
		// at the results page wherever one is configured, and a wiki with no
		// full-text search has nothing to aim at anyway, so in both cases every
		// link would arrive at the results page carrying the title as its query.
		// Send them where they mean to go instead, and where nothing else carries
		// the submit, route that too.
		if ( !fullText || resultsPageUrl() ) {
			rewriteSuggestionLinks();
		}
		if ( !fullText ) {
			navigateToTopResultOnSubmit();
			if ( !resultsPageUrl() ) {
				mw.util.addCSS( '.suggestions-special { display: none; }' );
			}
		}
	}
};
