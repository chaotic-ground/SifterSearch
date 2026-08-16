<?php

namespace MediaWiki\Extension\SifterSearch\Tests\Unit;

use MediaWiki\Extension\SifterSearch\IndexUrl;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SifterSearch\IndexUrl
 */
class IndexUrlTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideLayouts
	 */
	public function testTheCrawlLayoutDecidesTheResultUrl(
		string $localUrl, string $path, string $url, bool $served
	) {
		$this->assertSame( $path, IndexUrl::pathFor( $localUrl, 42 ), 'cache path' );
		$this->assertSame( $url, IndexUrl::urlFor( $path ), 'result URL' );
		$this->assertSame( $served, IndexUrl::isServed( $localUrl, 42 ), 'served as indexed' );
	}

	public static function provideLayouts() {
		return [
			'a static export, whose article path is a file path' => [
				'./Standalone_binary.html',
				'Standalone_binary.html',
				'/Standalone_binary.html',
				true,
			],
			'a subpage of one' => [
				'./Getting_Started/ko.html',
				'Getting_Started/ko.html',
				'/Getting_Started/ko.html',
				true,
			],
			'pretty URLs, which no .html file can land on' => [
				'/wiki/Foo',
				'wiki/Foo/index.html',
				'/wiki/Foo/',
				false,
			],
			'pretty URLs at the site root' => [
				'/Foo',
				'Foo/index.html',
				'/Foo/',
				false,
			],
			'query-string URLs, which are no path at all' => [
				'/index.php?title=Foo',
				'sifter/42/index.html',
				'/sifter/42/',
				false,
			],
		];
	}
}
