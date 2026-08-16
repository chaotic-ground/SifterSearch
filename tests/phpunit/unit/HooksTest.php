<?php

namespace MediaWiki\Extension\SifterSearch\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\SifterSearch\Hooks;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\ResourceLoader\Context;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SifterSearch\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSearchModules
	 */
	public function testSearchModuleIsRedirected( string $skinModule, string $expected ) {
		$hooks = new Hooks(
			$this->createMock( JobQueueGroup::class ),
			new HashConfig( [] )
		);
		$config = [ 'search' => true, 'searchModule' => $skinModule ];

		$hooks->onSkinPageReadyConfig( $this->createMock( Context::class ), $config );

		$this->assertSame( $expected, $config['searchModule'] );
	}

	public static function provideSearchModules() {
		return [
			'legacy skins' => [ 'mediawiki.searchSuggest', 'ext.sifter' ],
			'Vector 2022' => [ 'skins.vector.search', 'ext.sifter.vector' ],
			'Minerva' => [ 'skins.minerva.search', 'ext.sifter.minerva' ],
			'a skin we know nothing about' => [ 'skins.example.search', 'skins.example.search' ],
		];
	}
}
