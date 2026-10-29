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

    public string $baseUrl = '@web/dist';

    /**
     * Not `@webroot`, which `Test\TestCase` points at a scratch directory.
     */
    public string $basePath = '@root/web/dist';

    /**
     * An entry's stylesheets are inlined all or none, so their order holds; `0` never inlines.
     */
    public int $inlineCssMaxSize = 0;

    /**
     * The inline module script importing the entry comes last in the body, so without the preload the browser
     * requests the entry only once the whole page is parsed.
     */
    public bool $preloadJsModule = true;

    public string $manifestPath = '@root/web/dist/.vite/manifest.json';

    public bool $useDevServer = YII_ENV_DEV;

    /**
     * Without the check, a page loads nothing while the dev server is down.
     */
    public bool $checkDevServer = true;

    public string $devServerUrl = 'http://localhost:5173';

    /**
     * The URL PHP reaches the dev server at when it differs from the browser's (a container or a VM).
     */
    public ?string $devServerInternalUrl = null;

    public float $devServerTimeout = 0.1;

    private ?Manifest $manifest = null;
    private ?bool $isDevServerRunning = null;

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
     * @param array<string, mixed> $cssOptions
     * @param array<string, mixed> $jsOptions
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
     * The page's inline module script imports the entry and calls its default export with `$arguments`.
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
            if ($this->preloadJsModule) {
                $this->registerModulePreload($this->getManifest()->getChunk($entry), true);
            }

            $this->registerDependencies($entry, $cssOptions, true);
        }

        $this->getView()->registerJsModule($this->getUrl($entry), $arguments, $importName, $entry);
    }

    /**
     * Any manifest entry, an image a script imports included; registers nothing.
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
     * A dev server behind an address that drops packets costs a page `$devServerTimeout`, not a connect timeout.
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
            $this->registerModulePreload($chunk, $crossorigin, true);
        }
    }

    protected function registerModulePreload(Chunk $chunk, mixed $crossorigin, bool $isImported = false): void
    {
        $view = $this->getView();

        // The built scripts import a chunk without the query string a manifest may name it with (`chunk.js?v=…`).
        $file = $isImported ? $chunk->getPath() : $chunk->file;

        $view->registerLinkTag([
            'rel' => 'modulepreload',
            'href' => $this->getBuildUrl($file),
            'crossorigin' => $crossorigin,
            'integrity' => $chunk->integrity,
            'nonce' => $view->nonce,
        ], $file);
    }

    /**
     * A stylesheet an entry sharing it already registered, linked or inlined, is left as it is.
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

        $baseUrl = dirname($this->getBuildUrl((string)strtok($file, '?#'))) . '/';

        // A quoted URL with a scheme is skipped whole, so the `url()` of an SVG in a data URI stays untouched.
        $css = (string)preg_replace_callback(
            '/url\(\s*([\'"])[a-z][a-z\d+.-]*:(?:(?!\1)[^\\\\]|\\\\.)*\1\s*\)(*SKIP)(*FAIL)'
            . '|url\(\s*([\'"]?)(?![a-z][a-z\d+.-]*:|\/|#)(?:\.\/)?([^\'")]+)\2\s*\)/i',
            fn (array $matches): string => "url($matches[2]$baseUrl$matches[3]$matches[2])",
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

    /**
     * A built file's path on disk, without the query string or fragment a manifest may name it with (`app.css?v=…`).
     */
    protected function getBuildPath(string $file): string
    {
        return rtrim(Yii::getAlias($this->basePath), '/') . '/' . (string)strtok($file, '?#');
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
