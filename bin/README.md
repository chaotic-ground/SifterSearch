# Pagefind binaries

SifterSearch shells out to the [Pagefind](https://pagefind.app/) binary to build
the index. The binary is platform-specific and is **not** committed here; it is
added per platform by the release tooling, or by you.

At runtime the extension auto-detects the binary for the running platform,
expecting one of these filenames in this directory:

| Platform | Filename |
| --- | --- |
| Linux x86-64 | `pagefind-linux-x64` |
| Linux arm64 | `pagefind-linux-arm64` |
| macOS x86-64 | `pagefind-darwin-x64` |
| macOS arm64 | `pagefind-darwin-arm64` |
| Windows x86-64 | `pagefind-windows-x64.exe` |

The default distribution bundles only the most common server platform
(`pagefind-linux-x64`). On another platform, drop the matching binary here, or
point `$wgSifterSearchPagefindBinary` at a Pagefind binary anywhere on disk.

Binaries come from the [Pagefind releases](https://github.com/Pagefind/pagefind/releases)
(use the *extended* build for CJK language support).
