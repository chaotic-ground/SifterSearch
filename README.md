# SifterSearch

🔍 Client-side search for MediaWiki.

SifterSearch adds a fast, fully client-side search box to a MediaWiki site,
backed by a [Pagefind](https://pagefind.app/) index. The index is a set of
static files and every query runs in the browser, so there is no search server
and no database query at request time.

It was built for [Wikven](https://github.com/chaotic-ground/wikven), a generator
that exports a MediaWiki as a static site where there is no backend to answer
searches, so search has to run entirely in the browser. Nothing ties it to
Wikven, though: it works on any MediaWiki wiki, whether live or statically
exported, and suits smaller sites that want search without running a search
server.

The name is from *to sift*, as in *sifting through* a pile to find what you want.

## How it works

The index is built from page **content**, not by crawling a deployed site, so
the same path serves a live wiki and a static export:

1. When an indexed page changes, SifterSearch queues a rebuild job. The job
   de-duplicates, so a burst of edits — or a bulk import during a static build —
   collapses into a single rebuild.
2. The job keeps the rendered HTML of indexed pages in a cache directory and
   re-renders only what changed since the last run, then runs the bundled
   Pagefind binary over the cache to write the `pagefind/` bundle to
   `$wgSifterSearchOutputDir`. On a live wiki the queue runs via your normal job
   runner; on a static build it is drained by the build's `runJobs` step, so
   SifterSearch needs no knowledge of the build pipeline.
3. On every page, the client loads the Pagefind UI from the bundle and mounts a
   search box.

## The Pagefind binary

The indexer is a self-contained binary — no Node.js or other runtime is needed.
The extension auto-detects the bundled binary for the running platform (see
[`bin/README.md`](bin/README.md)). The default distribution ships only the most
common server platform; on another platform, drop the matching binary in `bin/`
or set `$wgSifterSearchPagefindBinary`.

## Configuration

| Setting | Default | Description |
| --- | --- | --- |
| `$wgSifterSearchOutputDir` | `""` | The Pagefind bundle directory itself, served at the bundle path (e.g. `<docroot>/pagefind`). Empty disables indexing. |
| `$wgSifterSearchBundlePath` | `/pagefind/` | URL path the client loads the bundle from. |
| `$wgSifterSearchCacheDir` | `""` | Rendered-HTML cache for incremental rebuilds. Defaults to a subdirectory of `$wgCacheDirectory`. |
| `$wgSifterSearchNamespaces` | `[ NS_MAIN ]` | Namespace IDs to index. |
| `$wgSifterSearchPagefindBinary` | `""` | Override the Pagefind binary path. Empty auto-detects `bin/`. |
| `$wgSifterSearchBatchSeconds` | `0` | Delay rebuilds so bursts coalesce into one batch. |

## License

GPL-3.0-or-later
