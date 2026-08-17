<?php

namespace MediaWiki\Extension\SifterSearch\Tests\Unit;

use MediaWiki\Extension\SifterSearch\BuildIndexJob;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\SifterSearch\BuildIndexJob
 */
class BuildIndexJobTest extends MediaWikiUnitTestCase {

	private function job(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromObject( new BuildIndexJob() );
	}

	public static function provideCachePaths() {
		return [
			'a rewritten .html url is the cache path verbatim' => [ './Foo_Bar.html', 0, 'Foo_Bar.html' ],
			'a directory-style url gains an index.html leaf' => [ '/wiki/Foo', 0, 'wiki/Foo/index.html' ],
			'a query-string url falls back to an id slug' => [ '/index.php?title=Foo', 7, 'sifter/7/index.html' ],
		];
	}

	/**
	 * @dataProvider provideCachePaths
	 */
	public function testCachePathForTitle( string $localUrl, int $articleId, string $expectedPath ) {
		$title = $this->createMock( Title::class );
		$title->method( 'getLocalURL' )->willReturn( $localUrl );
		$title->method( 'getArticleID' )->willReturn( $articleId );

		$this->assertSame( $expectedPath, $this->job()->cachePathForTitle( $title ) );
	}

	public function testWrapHtmlEscapesTheTitleAndWrapsTheRenderedBody() {
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'A & B' );

		$html = $this->job()->wrapHtml( 'en', $title, '<p>body</p>' );

		$this->assertStringContainsString( '<html lang="en">', $html );
		$this->assertStringContainsString( '<title>A &amp; B</title>', $html );
		$this->assertStringContainsString( '<h1>A &amp; B</h1>', $html );
		$this->assertStringContainsString( '<div data-pagefind-body><p>body</p></div>', $html );
	}

	public function testWrapHtmlStampsThePagesOwnLanguage() {
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'Installation/ko' );

		$html = $this->job()->wrapHtml( 'ko', $title, '<p>설치</p>' );

		// Pagefind reads the language from here and indexes per language, so a translation must
		// not arrive carrying the wiki's content language.
		$this->assertStringContainsString( '<html lang="ko">', $html );
	}

	public static function provideCacheCurrency() {
		return [
			'unchanged page keeps its cached html' => [
				[ 'touched' => '20260817000000', 'file' => 'Foo.html', 'lang' => 'en' ], true
			],
			'an edited page is re-rendered' => [
				[ 'touched' => '20260101000000', 'file' => 'Foo.html', 'lang' => 'en' ], false
			],
			'a page whose language changed is re-rendered' => [
				[ 'touched' => '20260817000000', 'file' => 'Foo.html', 'lang' => 'ko' ], false
			],
			// The manifest an older SifterSearch wrote records no language, so every page it
			// describes is stale: its html carries whatever the wiki's content language was.
			'an entry from before languages were recorded is stale' => [
				[ 'touched' => '20260817000000', 'file' => 'Foo.html' ], false
			],
			'a page with no entry at all is stale' => [ null, false ],
		];
	}

	/**
	 * @dataProvider provideCacheCurrency
	 */
	public function testIsCacheCurrent( ?array $entry, bool $expected ) {
		$this->assertSame(
			$expected,
			$this->job()->isCacheCurrent( $entry, '20260817000000', 'en' )
		);
	}
}
