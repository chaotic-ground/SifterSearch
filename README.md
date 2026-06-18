# SifterSearch

🔍 Client-side search for MediaWiki.

SifterSearch adds a fast, fully client-side search box to a MediaWiki site,
backed by a [Pagefind](https://pagefind.app/) index. The index is a set of
static files and every query runs in the browser, so there is no search server
and no database query at request time. That suits statically exported wikis and
smaller sites.

The name is from *to sift* — *sifting through* a pile to find what you want.

## How it works

The index is built from page **content**, not by crawling output HTML, so the
same path serves a live wiki and a static export:

1. When an indexed page changes, SifterSearch queues a rebuild job. The job
   dedups, so a burst of edits — or a bulk import during a static build —
   collapses into a single rebuild.
2. The job renders the indexed pages and feeds them to the Pagefind indexer,
   which writes the `pagefind/` bundle to `$wgSifterSearchOutputDir`. On a live
   wiki the queue runs via your normal job runner; on a static build it is
   drained by the build's `runJobs` step, so SifterSearch needs no knowledge of
   the build pipeline.
3. On every page, the client loads the Pagefind UI from the bundle and mounts a
   search box.

## Requirements

The indexer runs under Node.js. Install the npm dependencies (which include the
Pagefind binary) in the extension directory, and make `node` reachable from the
wiki:

```sh
npm install --omit=dev
```

## Configuration

| Setting | Default | Description |
| --- | --- | --- |
| `$wgSifterSearchOutputDir` | `""` | Filesystem directory the index is written to. Must be web-served at the bundle path. Empty disables index building. |
| `$wgSifterSearchBundlePath` | `/pagefind/` | URL path the client loads the bundle from. |
| `$wgSifterSearchNamespaces` | `[ NS_MAIN ]` | Namespace IDs to index. |
| `$wgSifterSearchNodePath` | `node` | Path to the Node.js binary. |

## License

GPL-3.0-or-later
