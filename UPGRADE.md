# Upgrading to 3.0

## Requirements

- PHP `^8.3`
- `davidhirtz/yii2-skeleton` `^3.6`. A project without it stays on `davidhirtz/yii2-vite` `^0.5`

## Configuration

The bundle registers the `vite` component itself, so an entry that only named the class can go. An entry that
configures anything, in any environment's config, keeps the `class` (now `Hirtz\Vite\Vite`): Yii refuses a component
without one while the application is built, before the bundle's `Bootstrap` runs.

| 0.x                  | 3.0                    |
|----------------------|------------------------|
| `devBaseUrl`         | `devServerUrl`         |
| `devBaseUrlInternal` | `devServerInternalUrl` |

`baseUrl` now defaults to `@web/dist` (was `/dist/`), which is the same URL unless the application lives in a
subdirectory.

## Usage

| 0.x                                                                      | 3.0                                       |
|--------------------------------------------------------------------------|-------------------------------------------|
| `davidhirtz\yii2\vite\components\Vite`                                   | `Hirtz\Vite\Vite`                         |
| `davidhirtz\yii2\vite\components\Manifest`                               | `Hirtz\Vite\Manifest`                     |
| `Yii::$app->get('vite')`                                                 | `Vite::current()`                         |
| `$view->registerJsModule($vite->getScriptUrl($entry), $arguments)`       | `$vite->registerJsModule($entry, $arguments)` |
| `getScriptUrl()` for a URL alone                                         | `getUrl()`                                |
| `registerFromDevServer()`, `registerFromManifest()`                      | `register()`                              |

`register($entry, $cssOptions, $jsOptions)` keeps its signature.

The `async` stylesheet option is gone: its inline `onload` handler is blocked by a nonce CSP. Load a stylesheet
that must not block rendering from the script instead.
