'use strict';

// Vector 2022's Codex typeahead. A SkinPageReadyConfig handler points its
// searchModule at this module; init() mounts Vector's own search app
// (skins.vector.search) with a Pagefind-backed search client, so the native
// Codex search box and dropdown work without a backend. Passing the client to
// the skin's init() is the documented extension point (used by Wikidata) and
// avoids the deprecated wgVectorSearchClient config var.
const { query, titleOf, textOf, MAX_RESULTS } = require( 'ext.sifter.pagefind' );
const vectorSearch = require( 'skins.vector.search' );

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

module.exports = {
	init: () => {
		vectorSearch.init( { fetchByTitle } );
	}
};
