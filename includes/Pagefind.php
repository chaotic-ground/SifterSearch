<?php

namespace MediaWiki\Extension\SifterSearch;

use MediaWiki\Config\Config;
use RuntimeException;

/**
 * Locates the Pagefind binary used to build the index.
 *
 * Like Scribunto's LuaStandalone engine, the binary platform is fixed at
 * deploy time, so we auto-detect the bundled binary for the running platform
 * and let an admin override the path for platforms we do not ship by default.
 */
class Pagefind {

	/**
	 * @param Config $config
	 * @return string Absolute path to the Pagefind binary.
	 * @throws RuntimeException when no binary can be resolved.
	 */
	public static function resolveBinaryPath( Config $config ): string {
		$override = $config->get( 'SifterSearchPagefindBinary' );
		if ( $override !== '' ) {
			return $override;
		}

		$name = self::platformBinaryName();
		if ( $name === null ) {
			throw new RuntimeException(
				'SifterSearch: no bundled Pagefind binary for this platform; '
				. 'set $wgSifterSearchPagefindBinary to a Pagefind binary path.'
			);
		}

		$path = __DIR__ . '/../bin/' . $name;
		if ( !is_file( $path ) ) {
			throw new RuntimeException(
				"SifterSearch: the bundled Pagefind binary '$name' was not found in bin/. "
				. 'Download the Pagefind binary for this platform into bin/, '
				. 'or set $wgSifterSearchPagefindBinary.'
			);
		}
		return $path;
	}

	/**
	 * @return string|null Expected binary filename for the running platform, or
	 *   null if we have no naming for it.
	 */
	private static function platformBinaryName(): ?string {
		$arch = strtolower( php_uname( 'm' ) );
		if ( $arch === 'x86_64' || $arch === 'amd64' ) {
			$arch = 'x64';
		} elseif ( $arch === 'aarch64' || $arch === 'arm64' ) {
			$arch = 'arm64';
		} else {
			return null;
		}

		switch ( PHP_OS_FAMILY ) {
			case 'Linux':
				return "pagefind-linux-$arch";
			case 'Darwin':
				return "pagefind-darwin-$arch";
			case 'Windows':
				return "pagefind-windows-$arch.exe";
			default:
				return null;
		}
	}
}
