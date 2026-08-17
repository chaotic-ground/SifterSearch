# Changelog

## [0.8.0](https://github.com/chaotic-ground/SifterSearch/compare/v0.7.2...v0.8.0) (2026-08-17)


### Features

* ask the wiki whether a page belongs in the index ([#71](https://github.com/chaotic-ground/SifterSearch/issues/71)) ([02b1e35](https://github.com/chaotic-ground/SifterSearch/commit/02b1e35d4d6d47c18f32469e5cc878af5cb45ffa))

## [0.7.2](https://github.com/chaotic-ground/SifterSearch/compare/v0.7.1...v0.7.2) (2026-08-17)


### Bugfixes

* index each page in its own language, not the wiki's ([#69](https://github.com/chaotic-ground/SifterSearch/issues/69)) ([6c406a5](https://github.com/chaotic-ground/SifterSearch/commit/6c406a5e60695be102bf7605125081c07d172ff2))
* separate the results page query from one the page URL already has ([#67](https://github.com/chaotic-ground/SifterSearch/issues/67)) ([bd84591](https://github.com/chaotic-ground/SifterSearch/commit/bd84591a72f14b44441a0fdce4817592ca660a97)), closes [#65](https://github.com/chaotic-ground/SifterSearch/issues/65)

## [0.7.1](https://github.com/chaotic-ground/SifterSearch/compare/v0.7.0...v0.7.1) (2026-08-17)


### Bugfixes

* anchor the results page URL one bundle carries to every depth ([#64](https://github.com/chaotic-ground/SifterSearch/issues/64)) ([301f56d](https://github.com/chaotic-ground/SifterSearch/commit/301f56dbc07b44e226703f921b53fa6d26529692)), closes [#63](https://github.com/chaotic-ground/SifterSearch/issues/63)

## [0.7.0](https://github.com/chaotic-ground/SifterSearch/compare/v0.6.1...v0.7.0) (2026-08-16)


### Features

* drive Minerva's typeahead with Pagefind ([#40](https://github.com/chaotic-ground/SifterSearch/issues/40)) ([6ef2409](https://github.com/chaotic-ground/SifterSearch/commit/6ef2409f3ec26a2a906e1945b6ca8eada3e737bc))


### Bugfixes

* link a result to the URL the wiki serves it at ([#61](https://github.com/chaotic-ground/SifterSearch/issues/61)) ([c8ca816](https://github.com/chaotic-ground/SifterSearch/commit/c8ca8169934f74fb9600327650b865a645e32c7c))
* link a suggestion to the page it names ([#39](https://github.com/chaotic-ground/SifterSearch/issues/39)) ([0b1e53d](https://github.com/chaotic-ground/SifterSearch/commit/0b1e53db2ae1397ad5d22bd0d7aadb757ea5511b))
* send a full-text search to one place, not two ([#62](https://github.com/chaotic-ground/SifterSearch/issues/62)) ([a08bc70](https://github.com/chaotic-ground/SifterSearch/commit/a08bc7085ce191d308a4f93b57c7829bd6c2511f))
* stay silent on a superseded query instead of answering empty ([#60](https://github.com/chaotic-ground/SifterSearch/issues/60)) ([5e7ffc8](https://github.com/chaotic-ground/SifterSearch/commit/5e7ffc8805249d3ba5ae8fab8e488b7ae65a1aa9))

## [0.6.1](https://github.com/chaotic-ground/SifterSearch/compare/v0.6.0...v0.6.1) (2026-07-19)


### Bugfixes

* repoint the search form's title input instead of removing it ([#35](https://github.com/chaotic-ground/SifterSearch/issues/35)) ([df727a4](https://github.com/chaotic-ground/SifterSearch/commit/df727a4c34646e92d88c20fac440405968045584))
* retarget the skin's search fallback link at the results page ([#32](https://github.com/chaotic-ground/SifterSearch/issues/32)) ([369c310](https://github.com/chaotic-ground/SifterSearch/commit/369c3107e62a236ccea4f19eeccd7c595a908f82))

## [0.6.0](https://github.com/chaotic-ground/SifterSearch/compare/v0.5.0...v0.6.0) (2026-06-27)


### Features

* hide the category footer on the results page ([#24](https://github.com/chaotic-ground/SifterSearch/issues/24)) ([5394ed3](https://github.com/chaotic-ground/SifterSearch/commit/5394ed38f8b780998c7af5fcff68c81d5f6e5b03))
* rewrite the legacy "containing" suggestion to the results page ([#26](https://github.com/chaotic-ground/SifterSearch/issues/26)) ([95e3ec8](https://github.com/chaotic-ground/SifterSearch/commit/95e3ec89f23febf5ec4c1274c17f83da054e1370))


### Bugfixes

* drop the empty image column from the results page ([#21](https://github.com/chaotic-ground/SifterSearch/issues/21)) ([a49561f](https://github.com/chaotic-ground/SifterSearch/commit/a49561fd58116d559f49b8eb968341c3b043952e))
* only mount the results widget on the results page ([#23](https://github.com/chaotic-ground/SifterSearch/issues/23)) ([c0896d1](https://github.com/chaotic-ground/SifterSearch/commit/c0896d1ebaffb2b50488080c68c938047684940d))
* retarget the search form at the results page ([#30](https://github.com/chaotic-ground/SifterSearch/issues/30)) ([0a4f696](https://github.com/chaotic-ground/SifterSearch/commit/0a4f696b3166080c164ed603ef9d8402877f4d81))

## [0.5.0](https://github.com/chaotic-ground/SifterSearch/compare/v0.4.0...v0.5.0) (2026-06-21)


### Features

* add a full Pagefind results page (SifterSearchResultsPage) ([#17](https://github.com/chaotic-ground/SifterSearch/issues/17)) ([ab5cd3b](https://github.com/chaotic-ground/SifterSearch/commit/ab5cd3b73aa0ddbc97789499a9f66c244875c897)), closes [#12](https://github.com/chaotic-ground/SifterSearch/issues/12)

## [0.4.0](https://github.com/chaotic-ground/SifterSearch/compare/v0.3.0...v0.4.0) (2026-06-21)


### Features

* send the search submit to the top result when full-text is off ([#15](https://github.com/chaotic-ground/SifterSearch/issues/15)) ([804b22e](https://github.com/chaotic-ground/SifterSearch/commit/804b22e31bc4dc82749624943519ca3729a4d725)), closes [#12](https://github.com/chaotic-ground/SifterSearch/issues/12)

## [0.3.0](https://github.com/chaotic-ground/SifterSearch/compare/v0.2.0...v0.3.0) (2026-06-21)


### Features

* add SifterSearchFullText to show or hide the full-text affordance ([#13](https://github.com/chaotic-ground/SifterSearch/issues/13)) ([6276007](https://github.com/chaotic-ground/SifterSearch/commit/6276007d676e0e119ed978b2cef9085703554d26))

## [0.2.0](https://github.com/chaotic-ground/SifterSearch/compare/v0.1.0...v0.2.0) (2026-06-21)


### Features

* drive native search suggestions with Pagefind instead of a separate box ([#9](https://github.com/chaotic-ground/SifterSearch/issues/9)) ([7d9ddbc](https://github.com/chaotic-ground/SifterSearch/commit/7d9ddbcd4ac08c84d6a296c2fdbfea54b4e2357d))
* drive Vector 2022 Codex search with Pagefind ([#10](https://github.com/chaotic-ground/SifterSearch/issues/10)) ([9d15020](https://github.com/chaotic-ground/SifterSearch/commit/9d15020caec8aec0f97170961cf1b715b2c4c5c1)), closes [#8](https://github.com/chaotic-ground/SifterSearch/issues/8)


### Bugfixes

* download the Windows Pagefind binary as a tar.gz ([#7](https://github.com/chaotic-ground/SifterSearch/issues/7)) ([01a0816](https://github.com/chaotic-ground/SifterSearch/commit/01a08164ac728f5b48289e10d9b2e099dfbe3285))

## 0.1.0 (2026-06-20)


### chore

* bootstrap the first release ([#4](https://github.com/chaotic-ground/SifterSearch/issues/4)) ([93fe89d](https://github.com/chaotic-ground/SifterSearch/commit/93fe89d71d52e539e565b4d9f6f5f947f0b1ac8b))
