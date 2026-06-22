<?php

namespace MediaWiki\Extension\SifterSearch\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\SifterSearch\Pagefind;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SifterSearch\Pagefind
 */
class PagefindTest extends MediaWikiUnitTestCase {

	public function testConfiguredBinaryShortCircuitsPlatformDetection() {
		$config = new HashConfig( [ 'SifterSearchPagefindBinary' => '/opt/pagefind' ] );

		$this->assertSame( '/opt/pagefind', Pagefind::resolveBinaryPath( $config ) );
	}
}
