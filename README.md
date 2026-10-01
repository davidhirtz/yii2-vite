# yii2-vite

[Vite](https://vite.dev/) for the [Yii 2](https://www.yiiframework.com/) skeleton: registers an entry's script,
stylesheets and `modulepreload` links from Vite's manifest, or from the dev server while it runs.

Version 3 requires `davidhirtz/yii2-skeleton` `^3.8`. Projects outside the skeleton stay on `^0.5` (branch `v0`).

## Vite

`build.manifest` is required. The defaults below expect the build in `web/dist`:

```js
export default defineConfig({
    build: {
        manifest: true,
        outDir: 'web/dist',
        rollupOptions: {
            input: ['resources/js/app.ts', 'resources/css/print.scss'],
        },
    },
});
```

## Configuration

The bundle registers the `vite` component, so a project configures only what differs from these defaults:

```php
'components' => [
    'vite' => [
        'baseUrl' => '@web/dist',
        'basePath' => '@webroot/dist', // `build.outDir`, read for stylesheets to inline
        'manifestPath' => '@webroot/dist/.vite/manifest.json',
        'useDevServer' => YII_ENV_DEV,
        'checkDevServer' => true,
        'devServerUrl' => 'http://localhost:5173',
        'devServerInternalUrl' => null, // where PHP reaches the dev server, if not at `devServerUrl`
        'devServerTimeout' => 0.1, // seconds
        'inlineCssMaxSize' => 0, // bytes, uncompressed; `0` links every stylesheet
        'preloadJsModule' => true, // a `modulepreload` link for a `registerJsModule()` entry
    ],
],
```

While `useDevServer` is on, every request checks for the dev server by opening a TCP connection to
`devServerInternalUrl`, at most `devServerTimeout` long. When it answers, entries load from it with `@vite/client`
for hot module replacement; when not, from the manifest. `checkDevServer => false` skips the check and always uses
the dev server.

## Inline stylesheets

With `inlineCssMaxSize` set, an entry whose stylesheets together are no larger than that many bytes renders them as
`<style>` tags instead of links, saving the render-blocking request. It suits a small site that loads the page once
and swaps the content afterwards (`AjaxRouteTrait`): registered in the layout, the styles reach the first response
alone. An entry's stylesheets are inlined all or none, so their order holds; one an earlier entry already linked or
inlined stays as it is. Relative `url()`s and the source map comment are made absolute against `baseUrl`. The dev
server always links.

```php
'vite' => [
    'inlineCssMaxSize' => 12_000, // roughly 3 kB gzipped
],
```

An inlined stylesheet is not cached by the browser, so every full page load carries it again.

## Usage

```php
use Hirtz\Vite\Vite;

// The entry's script tag, its stylesheets and a `modulepreload` link for every chunk it imports
Vite::current()->register('resources/js/app.ts');

// A stylesheet entry, with options for the link tags
Vite::current()->register('resources/css/print.scss', ['media' => 'print']);

// Options of the script tag
Vite::current()->register('resources/js/app.ts', jsOptions: ['position' => View::POS_HEAD]);

// Imported by the page's module script, its default export called with the arguments (`View::registerJsModule()`).
// A `modulepreload` link for the entry starts its request from the head, `'preloadJsModule' => false` leaves it out
Vite::current()->registerJsModule('resources/js/gallery.ts', ['selector' => '#gallery']);

// The URL of any manifest entry, an image included; registers nothing
Vite::current()->getUrl('resources/images/logo.svg');
```

Script and `modulepreload` tags carry the view's CSP nonce. A `sha384` `integrity` in the manifest (as written by
e.g. `vite-plugin-manifest-sri`) is added to both.
