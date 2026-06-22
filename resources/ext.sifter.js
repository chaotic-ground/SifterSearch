'use strict';

// Called by mediawiki.page.ready once a search input is focused, because a
// SkinPageReadyConfig handler points skins that use core's searchSuggest at this
// module. The native jquery.suggestions widget, attached by searchSuggest (our
// dependency), is left intact; only its data source is swapped to Pagefind.
const {
	query, titleOf, MAX_RESULTS, resultsPageUrl, navigateToTopResultOnSubmit
} = require( 'ext.sifter.pagefind' );

// Repoint core's "search for pages containing X" suggestion at the results page.
// searchSuggest rebuilds that link (with a trailing &fulltext=1 aimed at the
// absent Special:Search) on every keystroke, so watch the suggestion containers
// and overwrite the href as it is (re)written. The check makes our own write a
// no-op on the next callback, so it settles instead of looping.
function rewriteSpecialSuggestionLinks() {
	const rewrite = () => {
		for ( const special of document.querySelectorAll( '.suggestions-special' ) ) {
			const link = special.parentElement;
			if ( !link || !link.classList.contains( 'mw-searchSuggest-link' ) ) {
				continue;
			}
			const queryEl = special.querySelector( '.special-query' );
			const dest = resultsPageUrl( queryEl ? queryEl.textContent : '' );
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
}

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

		// The native "search for pages containing X" suggestion points at the
		// wiki's full-text search, which a static export lacks. Send the form
		// submit to the results page (or the top Pagefind result), and then either
		// repoint that suggestion at the results page too or, with no results page
		// to aim it at, hide the dead affordance.
		if ( !mw.config.get( 'wgSifterSearchFullText' ) ) {
			navigateToTopResultOnSubmit();
			if ( resultsPageUrl() ) {
				rewriteSpecialSuggestionLinks();
			} else {
				mw.util.addCSS( '.suggestions-special { display: none; }' );
			}
		}
	}
};
