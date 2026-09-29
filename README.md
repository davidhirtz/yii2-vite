# yii2-vite

[Vite](https://vite.dev/) for the [Yii 2](https://www.yiiframework.com/) skeleton: registers an entry's script,
stylesheets and `modulepreload` links from Vite's manifest, or from the dev server while it runs.

Version 3 requires `davidhirtz/yii2-skeleton` `^3.6`. Projects outside the skeleton stay on `^0.5` (branch `v0`).

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

The bundle registers the `vite` component. Everything is optional; these are the defaults:

```php
'components' => [
    'vite' => [
        'baseUrl' => '@web/dist',
        'manifestPath' => '@webroot/dist/.vite/manifest.json',
        'useDevServer' => YII_ENV_DEV,
        'checkDevServer' => true,
        'devServerUrl' => 'http://localhost:5173',
        'devServerInternalUrl' => null, // where PHP reaches the dev server, if not at `devServerUrl`
        'devServerTimeout' => 0.1, // seconds
    ],
],
```

While `useDevServer` is on, every request checks for the dev server by opening a TCP connection to
`devServerInternalUrl`, at most `devServerTimeout` long. When it answers, entries load from it with `@vite/client`
for hot module replacement; when not, from the manifest. `checkDevServer => false` skips the check and always uses
the dev server.

## Usage

```php
use Hirtz\Vite\Vite;

// The entry's script tag, its stylesheets and a `modulepreload` link for every chunk it imports
Vite::current()->register('resources/js/app.ts');

// A stylesheet entry, with options for the link tags
Vite::current()->register('resources/css/print.scss', ['media' => 'print']);

// Options of the script tag
Vite::current()->register('resources/js/app.ts', jsOptions: ['position' => View::POS_HEAD]);

// Imported by the page's module script, its default export called with the arguments (`View::registerJsModule()`)
Vite::current()->registerJsModule('resources/js/gallery.ts', ['selector' => '#gallery']);

// The URL of any manifest entry, an image included; registers nothing
Vite::current()->getUrl('resources/images/logo.svg');
```

Script and `modulepreload` tags carry the view's CSP nonce. A `sha384` `integrity` in the manifest (as written by
e.g. `vite-plugin-manifest-sri`) is added to both.
