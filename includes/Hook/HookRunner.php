<?php

namespace MediaWiki\Extension\SifterSearch\Hook;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\Title;

/**
 * Runs the hooks SifterSearch defines, so a caller states what it wants rather than how it is
 * dispatched.
 */
class HookRunner implements SifterSearchIndexPageHook {

	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onSifterSearchIndexPage( Title $title, bool &$index ) {
		return $this->hookContainer->run( 'SifterSearchIndexPage', [ $title, &$index ] );
	}
}
