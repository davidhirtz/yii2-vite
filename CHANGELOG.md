## Unreleased

- Added `$preloadJsModule`, on by default: `registerJsModule()` adds a `modulepreload` link for the entry itself

## 3.2.1 (October 1, 2026)

- Fixed inlining a stylesheet the manifest names with a query string (`app.css?v=…`), which was looked for on disk under that name

## 3.2.0 (October 1, 2026)

- Added `$inlineCssMaxSize` and `$basePath`: an entry's stylesheets up to that size are inlined as `<style>` tags

## 3.1.0 (September 30, 2026)

- Requires `davidhirtz/yii2-skeleton` `^3.8`: the `vite` component is declared through `ConfigBootstrapInterface`

## 3.0.0 (September 29, 2026)

- Requires `davidhirtz/yii2-skeleton` `^3.6` and is registered as the `vite` component by the bundle's `Bootstrap`; `Vite::current()` returns it. Version `0.x` (branch `v0`) stays for projects without the skeleton
- Renamed the namespace from `davidhirtz\yii2\vite\components\` to `Hirtz\Vite\`
- Renamed `$devBaseUrl` to `$devServerUrl` and `$devBaseUrlInternal` to `$devServerInternalUrl`; `$baseUrl` defaults to `@web/dist` and takes an alias
- Added `@vite/client` to the page while the dev server runs, so every entry gets hot module replacement; a stylesheet entry is linked as a stylesheet instead of loaded as a script
- Changed the dev server check to a TCP connection bounded by `$devServerTimeout` (0.1 seconds), replacing Guzzle, which waited indefinitely for an unreachable host
- Added `registerJsModule()` and `getUrl()`, which returns the URL of any manifest entry, assets included, and registers nothing; removed `getScriptUrl()`, `getScriptUrlFromDevServer()`, `getScriptUrlFromManifest()`, `registerFromDevServer()` and `registerFromManifest()`
- Changed `Manifest` to hold `Chunk` objects (`getChunk()`, `getImportedChunks()`, `getCssFiles()`) and throw `InvalidManifestException` for a missing or invalid manifest; removed `$data`, `getTagsForPath()` and the `TYPE_*` constants
- Changed stylesheets to follow Vite's order, an imported chunk's before the entry's own; script options no longer leak onto the `modulepreload` links, which carry the view's CSP nonce
- Removed the `async` stylesheet option, whose inline `onload` handler a nonce CSP blocks

## 0.5.0 (Feb 17, 2026)

- Requires PHP 8.3+
- Allows CSS files as entry points

## 0.4.0 (Jul 29, 2024)

- Fixed `href` for linked modules via `rel="modulepreload"`

## 0.3.0 (Jul 7, 2024)

- Fixed `ArrayHelper` import
- Removed unused `asyncCss` arguments, if a CSS file should be laded with `media="print"` hack, add `asyncCss` to the
  CSS options array

## 0.2.0 (Jul 7, 2024)

- Added `Vite::getScriptUrl()` method
- Added `.phpstorm.meta.php` configuration file

## 0.1.0 (Jul 1, 2024)

- Added default functionality only