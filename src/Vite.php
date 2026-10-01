<?php

declare(strict_types=1);

namespace Hirtz\Vite;

use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Web\View;
use Override;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

class Vite extends Component
{
    final public const string CLIENT_KEY = '@vite/client';

    /**
     * @var string the public URL of the built files, `build.outDir` as the browser sees it
     */
    public string $baseUrl = '@web/dist';

    /**
     * @var string the file system path of the built files, `build.outDir`, read for stylesheets to inline
     */
    public string $basePath = '@webroot/dist';

    /**
     * @var int the most bytes an entry's stylesheets may hold together to be inlined as `<style>` tags instead of
     * linked, `0` never inlines. An entry's stylesheets are inlined all or none, so their order holds.
     */
    public int $inlineCssMaxSize = 0;

    /**
     * @var string the file system path of the manifest `vite build` writes with `build.manifest` enabled
     */
    public string $manifestPath = '@webroot/dist/.vite/manifest.json';

    /**
     * @var bool whether the dev server may be used at all
     */
    public bool $useDevServer = YII_ENV_DEV;

    /**
     * @var bool whether the dev server is checked for before it is used. Without the check, a page loads nothing
     * while the dev server is down.
     */
    public bool $checkDevServer = true;

    /**
     * @var string the public URL of the dev server
     */
    public string $devServerUrl = 'http://localhost:5173';

    /**
     * @var string|null the URL PHP reaches the dev server at, when that differs from the browser's (a container or a
     * VM), defaults to `$devServerUrl`
     */
    public ?string $devServerInternalUrl = null;

    /**
     * @var float seconds the dev server check waits for a connection
     */
    public float $devServerTimeout = 0.1;

    private ?Manifest $manifest = null;
    private ?bool $isDevServerRunning = null;

    /**
     * The application's `vite` component, which the bundle's `Bootstrap` registers.
     */
    public static function current(): static
    {
        $vite = Yii::$app->get('vite');

        if (!$vite instanceof static) {
            throw new InvalidConfigException('The "vite" component must be an instance of ' . static::class . '.');
        }

        return $vite;
    }

    #[Override]
    public function init(): void
    {
        $this->devServerInternalUrl ??= $this->devServerUrl;
        parent::init();
    }

    /**
     * Registers an entry with the view: its script (or stylesheet), the stylesheets it imports and a
     * `modulepreload` link for every chunk it imports.
     *
     * @param array<string, mixed> $cssOptions the options of every stylesheet link
     * @param array<string, mixed> $jsOptions the options of the entry's script tag
     */
    public function register(string $entry, array $cssOptions = [], array $jsOptions = []): void
    {
        $entry = $this->normalizeEntry($entry);

        if ($this->isDevServerRunning()) {
            $this->registerDevServerEntry($entry, $cssOptions, $jsOptions);
            return;
        }

        $chunk = $this->getManifest()->getChunk($entry);

        if (!$chunk->isStylesheet()) {
            $this->getView()->registerJsFile($this->getBuildUrl($chunk->file), [
                'type' => 'module',
                'crossorigin' => true,
                'integrity' => $chunk->integrity,
                ...$jsOptions,
            ], $chunk->file);
        }

        $this->registerDependencies($entry, $cssOptions, $jsOptions['crossorigin'] ?? true);
    }

    /**
     * Registers an entry as a module imported by the page's inline module script, calling its default export with
     * `$arguments`, through {@see View::registerJsModule()}. Its stylesheets and imported chunks are registered as
     * by {@see register()}.
     *
     * @param array<int|string, mixed>|string|null $arguments
     * @param array<string, mixed> $cssOptions
     */
    public function registerJsModule(string $entry, array|string|null $arguments = null, array $cssOptions = [], string|null|false $importName = null): void
    {
        $entry = $this->normalizeEntry($entry);

        if ($this->isDevServerRunning()) {
            $this->registerDevServerClient();
        } else {
            $this->registerDependencies($entry, $cssOptions, true);
        }

        $this->getView()->registerJsModule($this->getUrl($entry), $arguments, $importName, $entry);
    }

    /**
     * The URL of a manifest entry: a script, a stylesheet or an asset such as an image imported by a script or
     * listed in `build.rollupOptions.input`. It registers nothing.
     */
    public function getUrl(string $entry): string
    {
        $entry = $this->normalizeEntry($entry);

        return $this->isDevServerRunning()
            ? $this->getDevServerUrl($entry)
            : $this->getBuildUrl($this->getManifest()->getChunk($entry)->file);
    }

    public function getManifest(): Manifest
    {
        return $this->manifest ??= Yii::$container->get(Manifest::class, [$this->manifestPath]);
    }

    /**
     * Whether the dev server serves the entries, answered once per instance. The check opens a TCP connection
     * to `$devServerInternalUrl`, bounded by `$devServerTimeout`, so a dev server behind an address that drops
     * packets costs a page that long, not a connect timeout.
     */
    public function isDevServerRunning(): bool
    {
        if (!$this->useDevServer) {
            return false;
        }

        if (!$this->checkDevServer) {
            return true;
        }

        return $this->isDevServerRunning ??= $this->pingDevServer();
    }

    /**
     * @param array<string, mixed> $cssOptions
     * @param array<string, mixed> $jsOptions
     */
    protected function registerDevServerEntry(string $entry, array $cssOptions, array $jsOptions): void
    {
        $this->registerDevServerClient();

        $url = $this->getDevServerUrl($entry);

        if ($this->isStylesheetSource($entry)) {
            $this->getView()->registerCssFile($url, $cssOptions, $entry);
            return;
        }

        $this->getView()->registerJsFile($url, [
            'type' => 'module',
            ...$jsOptions,
        ], $entry);
    }

    protected function registerDevServerClient(): void
    {
        $this->getView()->registerJsFile($this->getDevServerUrl(self::CLIENT_KEY), [
            'type' => 'module',
            'position' => View::POS_HEAD,
        ], self::CLIENT_KEY);
    }

    /**
     * @param array<string, mixed> $cssOptions
     */
    protected function registerDependencies(string $entry, array $cssOptions, mixed $crossorigin): void
    {
        $manifest = $this->getManifest();
        $view = $this->getView();

        $chunk = $manifest->getChunk($entry);
        $files = $manifest->getCssFiles($entry);

        if ($chunk->isStylesheet()) {
            $files[] = $chunk->file;
        }

        $this->registerStylesheets($files, $cssOptions);

        foreach ($manifest->getImportedChunks($entry) as $chunk) {
            $view->registerLinkTag([
                'rel' => 'modulepreload',
                'href' => $this->getBuildUrl($chunk->file),
                'crossorigin' => $crossorigin,
                'integrity' => $chunk->integrity,
                'nonce' => $view->nonce,
            ], $chunk->file);
        }
    }

    /**
     * Links the stylesheets, or inlines them when together they fit `$inlineCssMaxSize`. One already registered
     * either way, by an entry sharing it, is left as it is.
     *
     * @param list<string> $files
     * @param array<string, mixed> $cssOptions
     */
    protected function registerStylesheets(array $files, array $cssOptions): void
    {
        $view = $this->getView();
        $files = array_filter($files, fn (string $file): bool => !isset($view->cssFiles[$file]) && !isset($view->css[$file]));

        if ($this->shouldInlineCss($files)) {
            foreach ($files as $file) {
                $view->registerCss($this->getInlineCss($file), $cssOptions, $file);
            }

            return;
        }

        foreach ($files as $file) {
            $view->registerCssFile($this->getBuildUrl($file), $cssOptions, $file);
        }
    }

    /**
     * @param array<array-key, string> $files
     */
    protected function shouldInlineCss(array $files): bool
    {
        if ($this->inlineCssMaxSize <= 0 || !$files) {
            return false;
        }

        $size = 0;

        foreach ($files as $file) {
            $path = $this->getBuildPath($file);
            $fileSize = @filesize($path);

            if ($fileSize === false) {
                throw new InvalidConfigException("The Vite stylesheet \"$path\" was not found. Is `basePath` Vite's `build.outDir`?");
            }

            $size += $fileSize;

            if ($size > $this->inlineCssMaxSize) {
                return false;
            }
        }

        return true;
    }

    /**
     * A built stylesheet's contents, its relative URLs made absolute: inlined, they would resolve against the page.
     */
    protected function getInlineCss(string $file): string
    {
        $path = $this->getBuildPath($file);
        $css = @file_get_contents($path);

        if ($css === false) {
            throw new InvalidConfigException("The Vite stylesheet \"$path\" could not be read.");
        }

        $baseUrl = dirname($this->getBuildUrl($file)) . '/';

        $css = (string)preg_replace_callback(
            '/url\(\s*([\'"]?)(?![a-z][a-z\d+.-]*:|\/|#)(?:\.\/)?([^\'")]+)\1\s*\)/i',
            fn (array $matches): string => "url($matches[1]$baseUrl$matches[2]$matches[1])",
            $css,
        );

        return (string)preg_replace('/(\/\*# sourceMappingURL=)(?![a-z][a-z\d+.-]*:|\/)(?:\.\/)?/i', '$1' . $baseUrl, $css);
    }

    protected function pingDevServer(): bool
    {
        $url = (string)$this->devServerInternalUrl;
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if ($host === null) {
            throw new InvalidConfigException("The Vite dev server URL \"$url\" has no host.");
        }

        $port = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $socket = @stream_socket_client("tcp://$host:$port", $code, $message, $this->devServerTimeout);

        if ($socket === false) {
            Yii::debug("Vite dev server not reachable at $url: $message", __METHOD__);
            return false;
        }

        fclose($socket);

        Yii::debug("Vite dev server running at $url", __METHOD__);
        return true;
    }

    protected function getBuildUrl(string $file): string
    {
        return rtrim(Yii::getAlias($this->baseUrl), '/') . '/' . $file;
    }

    protected function getBuildPath(string $file): string
    {
        return rtrim(Yii::getAlias($this->basePath), '/') . '/' . $file;
    }

    protected function getDevServerUrl(string $path): string
    {
        return rtrim($this->devServerUrl, '/') . '/' . $path;
    }

    protected function isStylesheetSource(string $entry): bool
    {
        return in_array(pathinfo($entry, PATHINFO_EXTENSION), ['css', 'less', 'sass', 'scss', 'styl', 'stylus', 'pcss', 'postcss', 'sss'], true);
    }

    protected function normalizeEntry(string $entry): string
    {
        return ltrim($entry, '/');
    }

    protected function getView(): View
    {
        return Application::current()->getView();
    }
}
