'use strict';

// Called by mediawiki.page.ready once a search input is focused, because a
// SkinPageReadyConfig handler points skins that use core's searchSuggest at this
// module. The native jquery.suggestions widget, attached by searchSuggest (our
// dependency), is left intact; only its data source is swapped to Pagefind.
const { query, titleOf, MAX_RESULTS } = require( 'ext.sifter.pagefind' );

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
		// wiki's full-text search; hide it when there is no such page.
		if ( !mw.config.get( 'wgSifterSearchFullText' ) ) {
			mw.util.addCSS( '.suggestions-special { display: none; }' );
		}
	}
};
