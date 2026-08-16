'use strict';

// Vector 2022's Codex typeahead. A SkinPageReadyConfig handler points its
// searchModule at this module; init() mounts Vector's own search app
// (skins.vector.search) on Pagefind, so the native Codex search box and dropdown
// work without a backend.
const { initTypeahead } = require( 'ext.sifter.pagefind' );
const vectorSearch = require( 'skins.vector.search' );

module.exports = {
	init: () => initTypeahead( vectorSearch )
};
