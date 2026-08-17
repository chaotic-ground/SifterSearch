<?php

namespace MediaWiki\Extension\SifterSearch\Hook;

use MediaWiki\Title\Title;

/**
 * Whether a page belongs in the search index.
 *
 * SifterSearch indexes every page of the configured namespaces, which is the right default and
 * the only thing it can decide on its own: it sees titles and content, not what the wiki means by
 * them. A wiki that knows one of its pages is not worth answering with -- a duplicate of another
 * under a second title, a page kept for machinery rather than readers -- says so here.
 *
 * A page turned away is removed from the index like a deleted one, so a wiki may change its mind
 * and the next run will carry it out either way.
 *
 * @see \MediaWiki\Extension\SifterSearch\BuildIndexJob
 */
interface SifterSearchIndexPageHook {
	/**
	 * @param Title $title The page being considered.
	 * @param bool &$index Whether to index it; true when the hook is called.
	 * @return bool|void False to stop other handlers being asked.
	 */
	public function onSifterSearchIndexPage( Title $title, bool &$index );
}
