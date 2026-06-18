# SifterSearch

🔍 Client-side search for MediaWiki.

SifterSearch adds a fast, fully client-side search box to a MediaWiki site,
backed by a [Pagefind](https://pagefind.app/) index. The index is a set of
static files and every query runs in the browser, so there is no search server
and no database query at request time. That suits statically exported wikis and
smaller sites.

The name is from *to sift* — *sifting through* a pile to find what you want.

## How it works

1. Once your site's HTML exists, build a Pagefind index over it:

   ```sh
   npx pagefind --site path/to/output
   ```

   This writes a `pagefind/` bundle next to your pages.

2. Enable this extension. It loads the Pagefind UI from that bundle and mounts a
   search box on every page. If the bundle is not served at `/pagefind/`, point
   `$wgSifterSearchBundlePath` at it.

## Configuration

| Setting | Default | Description |
| --- | --- | --- |
| `$wgSifterSearchBundlePath` | `/pagefind/` | URL path to the Pagefind bundle. |

## License

GPL-3.0-or-later
