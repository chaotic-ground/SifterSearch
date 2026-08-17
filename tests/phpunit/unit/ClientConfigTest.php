<?php

namespace MediaWiki\Extension\SifterSearch\Tests\Unit;

use MediaWiki\Extension\SifterSearch\ClientConfig;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SifterSearch\ClientConfig
 */
class ClientConfigTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideUrls
	 */
	public function testTheResultsPageUrlMeansTheSameThingEverywhere(
		string $url, string $bundlePath, string $expected
	) {
		$this->assertSame( $expected, ClientConfig::anchored( $url, $bundlePath ) );
	}

	public static function provideUrls() {
		return [
			'pretty URLs, which already mean the same thing everywhere' => [
				'/wiki/Search', '/pagefind/', '/wiki/Search',
			],
			'query-string URLs, likewise' => [
				'/index.php?title=Search', '/pagefind/', '/index.php?title=Search',
			],
			'a static export, whose URLs are document-relative' => [
				'./Search.html', '/pagefind/', '/Search.html',
			],
			'one deployed in a subdirectory, where the bundle path says so' => [
				'./Search.html', '/wikven/pagefind/', '/wikven/Search.html',
			],
			'a relative URL without the ./' => [
				'Search.html', '/wikven/pagefind/', '/wikven/Search.html',
			],
			'a bundle path without its trailing slash' => [
				'./Search.html', '/wikven/pagefind', '/wikven/Search.html',
			],
			'a results page below the export root' => [
				'./Help/Search.html', '/wikven/pagefind/', '/wikven/Help/Search.html',
			],
			'a bundle served from elsewhere, which says nothing about the site root' => [
				'./Search.html', 'https://cdn.example/pagefind/', './Search.html',
			],
			'no bundle path at all' => [
				'./Search.html', '', './Search.html',
			],
			'no results page URL' => [
				'', '/pagefind/', '',
			],
		];
	}
}
