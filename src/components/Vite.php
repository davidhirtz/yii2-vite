<?php

namespace davidhirtz\yii2\vite\components;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class Vite extends Component
{
    /**
     * @var string The public URL to use when not using the dev server
     */
    public string $baseUrl = '/dist/';

    /**
     * @var bool whether the presence of the dev server should be checked by pinging `$devServerInternal` to make sure
     * it's running.
     */
    public bool $checkDevServer = true;

    /**
     * @var string the public URL to the dev server
     */
    public string $devBaseUrl = 'http://localhost:5173/';

    /**
     * @var string|null containing the internal URL to the dev server. When accessed from the environment in which PHP
     * is executing. This can be the same as `$devServer`, but may be different in containerized or VM setups.
     */
    public ?string $devBaseUrlInternal = null;

    /**
     * @var string file system path to the Vite-built manifest.json
     */
    public string $manifestPath = '@webroot/dist/.vite/manifest.json';

    /**
     * @var bool whether the dev server should be used at all
     */
    public bool $useDevServer = YII_ENV_DEV;

    private ?Manifest $manifest = null;
    private ?bool $isDevServerRunningCached = null;

    public function init(): void
    {
        $this->devBaseUrlInternal ??= $this->devBaseUrl;
        parent::init();
    }

    public function register(string $path, array $cssOptions = [], array $jsOptions = []): void
    {
        $path = ltrim($path, '/');

        if ($this->isDevServerRunning()) {
            $this->registerFromDevServer($path, $jsOptions);
            return;
        }

        $this->registerFromManifest($path, $cssOptions, $jsOptions);
    }

    public function registerFromDevServer(string $path, array $options = []): void
    {
        $url = rtrim($this->devBaseUrl, '/') . "/$path";
        $options['type'] ??= 'module';

        Yii::$app->getView()->registerJsFile($url, $options, $path);
    }

    public function registerFromManifest(string $path, array $cssOptions = [], array $jsOptions = []): void
    {
        $tags = $this->getManifest()->getTagsForPath($path, $cssOptions, $jsOptions);
        $this->registerTagsFromManifest($tags);
    }

    public function getScriptUrl(string $path, array $cssOptions = [], array $jsOptions = []): string
    {
        $path = ltrim($path, '/');

        if ($this->isDevServerRunning()) {
            return $this->getScriptUrlFromDevServer($path);
        }

        return $this->getScriptUrlFromManifest($path, $cssOptions, $jsOptions);
    }

    public function getScriptUrlFromDevServer(string $path): string
    {
        return rtrim($this->devBaseUrl, '/') . "/$path";
    }

    public function getScriptUrlFromManifest(string $path, array $cssOptions = [], array $jsOptions = []): string
    {
        $tags = $this->getManifest()->getTagsForPath($path, $cssOptions, $jsOptions);
        $script = ArrayHelper::remove($tags, $path);

        $this->registerTagsFromManifest($tags);

        return $this->formatManifestUrl($script['url']);
    }

    protected function registerTagsFromManifest(array $tags): void
    {
        $view = Yii::$app->getView();

        foreach ($tags as $key => $tag) {
            $url = $this->formatManifestUrl($tag['url']);

            switch ($tag['type']) {
                case Manifest::TYPE_JS:
                    $view->registerJsFile($url, $tag['options'], $key);
                    break;

                case Manifest::TYPE_CSS:
                    $view->registerCssFile($url, $tag['options'], $key);
                    break;

                case Manifest::TYPE_LINK:
                    $tag['options']['href'] = $url;
                    $view->registerLinkTag($tag['options'], $key);
                    break;
            }
        }
    }

    protected function formatManifestUrl(string $url): string
    {
        return rtrim($this->baseUrl, '/') . "/$url";
    }

    public function getManifest(): Manifest
    {
        return $this->manifest ??= Yii::$container->get(Manifest::class, [
            'path' => $this->manifestPath,
        ]);
    }

    public function isDevServerRunning(): bool
    {
        if ($this->isDevServerRunningCached !== null) {
            return $this->isDevServerRunningCached;
        }

        if (!$this->useDevServer) {
            return false;
        }

        if (!$this->checkDevServer) {
            return true;
        }

        Yii::debug('Pinging Vite dev server ...');

        $this->isDevServerRunningCached = false;

        try {
            $response = (new Client())->head($this->devBaseUrlInternal, [
                'http_errors' => false,
            ]);

            if (in_array($response->getStatusCode(), [200, 404])) {
                Yii::debug('Vite dev server is running');
                $this->isDevServerRunningCached = true;
            }
        } catch (ConnectException) {
            Yii::debug('Vite dev server not found');
        }

        return $this->isDevServerRunningCached;
    }
}
