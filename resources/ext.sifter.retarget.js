'use strict';

// Point the skin's search form, and any fallback link to the wiki search page, at
// the SifterSearch results page. The skin renders the box aimed at the wiki default
// (action="/index.php" plus a hidden title="Special:Search"), so a plain submit --
// Enter or the search button, before
// the on-focus typeahead module has loaded -- queries the wiki's own search on a
// live site and 404s on a static export that has no index.php. This runs eagerly
// on every page, independent of the typeahead, so the native submit path reaches
// SifterSearch consistently. Only loaded when a results page is configured.
const { resultsPageUrl: url, resultsPageTitle: pageTitle } = require( './config.json' );

function retarget() {
	if ( !url ) {
		return;
	}
	// A GET form drops the action's own query string, so split it off and re-add
	// its params as hidden fields. Covers a wiki without pretty URLs, where the
	// local URL is /index.php?title=Results; on a static export or pretty-URL wiki
	// the URL is path-only and there is nothing to carry.
	const parts = url.split( '?' );
	const action = parts[ 0 ];
	const params = new URLSearchParams( parts[ 1 ] || '' );

	Array.prototype.forEach.call( document.querySelectorAll( 'form' ), ( form ) => {
		if ( !form.querySelector( 'input[name="search"], #searchInput' ) ) {
			return;
		}
		form.setAttribute( 'action', action );
		// Repoint the skin's hidden title=Special:Search at the results page, so
		// the submit no longer aims at the wiki search. Kept rather than removed:
		// Vector's typeahead refuses to mount a search box without a title input.
		const titleInputs = form.querySelectorAll( 'input[type="hidden"][name="title"]' );
		Array.prototype.forEach.call( titleInputs, ( el ) => {
			el.value = pageTitle;
		} );
		params.forEach( ( value, name ) => {
			// The repointed title input already carries the action's title param.
			if ( name === 'title' && titleInputs.length ) {
				return;
			}
			const hidden = document.createElement( 'input' );
			hidden.type = 'hidden';
			hidden.name = name;
			hidden.value = value;
			form.appendChild( hidden );
		} );
	} );

	// Some skins also render a plain link to the wiki search page as a fallback for
	// when scripts are off (e.g. Vector's collapsed-box toggle links to
	// Special:Search). Point those at the results page too, so the fallback lands
	// on the static results page instead of a 404.
	Array.prototype.forEach.call(
		document.querySelectorAll( '[role="search"] a[href]' ),
		( link ) => link.setAttribute( 'href', url )
	);
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', retarget );
} else {
	retarget();
}
