<?php

namespace MediaWiki\Extension\SifterSearch\Tests\Unit\Hook;

use MediaWiki\Extension\SifterSearch\Hook\HookRunner;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SifterSearch\Hook\HookRunner
 */
class HookRunnerTest extends MediaWikiUnitTestCase {

	/**
	 * The one thing worth pinning: $index reaches a handler by reference, so a wiki turning a page
	 * away is heard. Passed by value the hook would run, report nothing, and every page would be
	 * indexed as before -- a failure that looks exactly like a wiki that asked for nothing.
	 */
	public function testIndexIsPassedByReference() {
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->method( 'run' )->willReturnCallback(
			static function ( string $hook, array $args ): bool {
				$args[1] = false;
				return true;
			}
		);

		$index = true;
		( new HookRunner( $hookContainer ) )
			->onSifterSearchIndexPage( $this->createMock( Title::class ), $index );

		$this->assertFalse( $index );
	}

	/** A wiki that registers no handler, or one that looks and says nothing, keeps the default. */
	public function testIndexIsLeftAloneWhenNoHandlerObjects() {
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->method( 'run' )->willReturn( true );

		$index = true;
		( new HookRunner( $hookContainer ) )
			->onSifterSearchIndexPage( $this->createMock( Title::class ), $index );

		$this->assertTrue( $index );
	}
}
