<?php

namespace davidhirtz\yii2\vite\components;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Yii;
use yii\base\Component;

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

    public function register(
        string $path,
        bool $asyncCss = true,
        array $cssOptions = [],
        array $jsOptions = [],
    ): void
    {
        $path = ltrim($path, '/');

        if ($this->isDevServerRunning()) {
            $this->registerFromDevServer($path, $jsOptions);
            return;
        }

        $this->registerFromManifest($path, $asyncCss, $cssOptions, $jsOptions);
    }

    public function registerFromDevServer(string $path, array $options = []): void
    {
        $url = rtrim($this->devBaseUrl, '/') . "/$path";
        $options['type'] ??= 'module';

        Yii::$app->getView()->registerJsFile($url, $options, $path);
    }

    public function registerFromManifest(
        string $path,
        bool $asyncCss = true,
        array $cssOptions = [],
        array $jsOptions = [],
    ): void
    {
        $tags = $this->getManifest()->getTagsForPath($path, $asyncCss, $cssOptions, $jsOptions);
        $view = Yii::$app->getView();

        foreach ($tags as $tag) {
            $url = rtrim($this->baseUrl, '/') . "/{$tag['url']}";

            switch ($tag['type']) {
                case Manifest::TYPE_JS:
                    $view->registerJsFile($url, $tag['options']);
                    break;

                case Manifest::TYPE_CSS:
                    $view->registerCssFile($url, $tag['options']);
                    break;

                case Manifest::TYPE_LINK:
                    $view->registerLinkTag($tag['options']);
                    break;
            }
        }
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