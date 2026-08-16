'use strict';

// Minerva's Codex typeahead. MinervaNeue names its own search module, so without
// this it keeps querying /rest.php/v1/search/title, which a static export has no
// backend for. A SkinPageReadyConfig handler points its searchModule here
// instead; init() mounts Minerva's own search app (skins.minerva.search) on
// Pagefind, exactly as ext.sifter.vector does for Vector 2022.
const { initTypeahead } = require( 'ext.sifter.pagefind' );
const minervaSearch = require( 'skins.minerva.search' );

module.exports = {
	init: () => initTypeahead( minervaSearch )
};
