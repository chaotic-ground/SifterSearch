<?php

namespace MediaWiki\Extension\SifterSearch\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\SifterSearch\Hooks;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SifterSearch\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {

	private function newHooks(): Hooks {
		return new Hooks(
			$this->createMock( JobQueueGroup::class ),
			new HashConfig( [] )
		);
	}

	/**
	 * @dataProvider provideSearchModules
	 */
	public function testSearchModuleIsRedirected( string $skinModule, string $expected ) {
		$config = [ 'search' => true, 'searchModule' => $skinModule ];

		$this->newHooks()->onSkinPageReadyConfig( $this->createMock( Context::class ), $config );

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

	public function testOnlyInstalledSkinsGetAnAdapter() {
		$registered = [];
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->method( 'isModuleRegistered' )
			->willReturnCallback( static fn ( $name ) => $name === 'skins.vector.search' );
		$resourceLoader->method( 'register' )
			->willReturnCallback( static function ( $name, $info ) use ( &$registered ) {
				$registered[$name] = $info;
			} );

		$this->newHooks()->onResourceLoaderRegisterModules( $resourceLoader );

		$this->assertSame( [ 'ext.sifter.vector' ], array_keys( $registered ) );
		$this->assertSame(
			[ 'ext.sifter.pagefind', 'skins.vector.search' ],
			$registered['ext.sifter.vector']['dependencies']
		);
		$this->assertSame(
			[ 'ext.sifter.vector.js' ],
			$registered['ext.sifter.vector']['packageFiles']
		);
	}
}
