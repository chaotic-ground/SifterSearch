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
	 * A ResourceLoader that has the named modules and records what is registered.
	 *
	 * @param string[] $registeredModules
	 * @param array &$registry Filled with the modules the handler registers.
	 * @return ResourceLoader
	 */
	private function newResourceLoader( array $registeredModules, array &$registry ) {
		$resourceLoader = $this->createMock( ResourceLoader::class );
		// A closure rather than an arrow function: this has to see what the
		// registration hook registers, which an arrow function's by-value capture
		// of $registry would not.
		$resourceLoader->method( 'isModuleRegistered' )
			->willReturnCallback(
				static function ( $name ) use ( $registeredModules, &$registry ) {
					return in_array( $name, $registeredModules, true ) || isset( $registry[$name] );
				}
			);
		$resourceLoader->method( 'register' )
			->willReturnCallback( static function ( $name, $info ) use ( &$registry ) {
				$registry[$name] = $info;
			} );
		return $resourceLoader;
	}

	/**
	 * @dataProvider provideSearchModules
	 */
	public function testSearchModuleIsRedirected( string $skinModule, string $expected ) {
		$registry = [];
		$context = $this->createMock( Context::class );
		$context->method( 'getResourceLoader' )->willReturn(
			$this->newResourceLoader( [ 'ext.sifter', 'ext.sifter.vector', 'ext.sifter.minerva' ], $registry )
		);
		$config = [ 'search' => true, 'searchModule' => $skinModule ];

		$this->newHooks()->onSkinPageReadyConfig( $context, $config );

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

	public function testAnUnregisteredAdapterIsNotNamed() {
		$registry = [];
		$context = $this->createMock( Context::class );
		$context->method( 'getResourceLoader' )->willReturn(
			$this->newResourceLoader( [ 'ext.sifter' ], $registry )
		);
		$config = [ 'search' => true, 'searchModule' => 'skins.minerva.search' ];

		$this->newHooks()->onSkinPageReadyConfig( $context, $config );

		$this->assertSame( 'skins.minerva.search', $config['searchModule'] );
	}

	public function testOnlyInstalledSkinsGetAnAdapter() {
		$registry = [];

		$this->newHooks()->onResourceLoaderRegisterModules(
			$this->newResourceLoader( [ 'skins.vector.search' ], $registry )
		);

		$this->assertSame( [ 'ext.sifter.vector' ], array_keys( $registry ) );
		$this->assertSame(
			[ 'ext.sifter.pagefind', 'skins.vector.search' ],
			$registry['ext.sifter.vector']['dependencies']
		);
	}

	public function testEachAdapterPointsAtAFileThatExists() {
		$registry = [];

		$this->newHooks()->onResourceLoaderRegisterModules(
			$this->newResourceLoader( [ 'skins.vector.search', 'skins.minerva.search' ], $registry )
		);

		// CI installs no skins, so this is the only thing that reads these
		// definitions: without it a renamed file would ship registered and 404.
		$this->assertSame(
			[ 'ext.sifter.vector', 'ext.sifter.minerva' ],
			array_keys( $registry )
		);
		foreach ( $registry as $name => $info ) {
			foreach ( $info['packageFiles'] as $file ) {
				$this->assertFileExists(
					$info['localBasePath'] . '/' . $file,
					"$name is registered with a package file that does not exist"
				);
			}
		}
	}

	public function testTheModuleRegisteredIsTheModuleNamed() {
		$registry = [];
		$hooks = $this->newHooks();
		$resourceLoader = $this->newResourceLoader(
			[ 'ext.sifter', 'skins.minerva.search' ],
			$registry
		);

		// Both hooks against one ResourceLoader, in the order a request runs them:
		// what registration decides is what the swap is allowed to name.
		$hooks->onResourceLoaderRegisterModules( $resourceLoader );
		$context = $this->createMock( Context::class );
		$context->method( 'getResourceLoader' )->willReturn( $resourceLoader );
		$config = [ 'search' => true, 'searchModule' => 'skins.minerva.search' ];
		$hooks->onSkinPageReadyConfig( $context, $config );

		$this->assertSame( 'ext.sifter.minerva', $config['searchModule'] );
	}
}
