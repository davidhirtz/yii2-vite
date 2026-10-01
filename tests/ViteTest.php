<?php

declare(strict_types=1);

namespace Hirtz\Vite\Tests;

use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Web\View;
use Hirtz\Vite\Vite;
use Override;
use Yii;
use yii\base\InvalidConfigException;

class ViteTest extends TestCase
{
    /**
     * @var resource|null a listening socket standing in for the dev server
     */
    private $devServer = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->devServer !== null) {
            fclose($this->devServer);
        }

        parent::tearDown();
    }

    public function testBootstrapRegistersTheComponent(): void
    {
        self::assertInstanceOf(Vite::class, Vite::current());
        self::assertSame(Yii::$app->get('vite'), Vite::current());
    }

    public function testAProjectConfiguresTheComponentWithoutItsClass(): void
    {
        $this->config['components']['vite'] = ['baseUrl' => '/build'];
        $this->reloadApplication();

        self::assertSame('/build', Vite::current()->baseUrl);
    }

    public function testDevServerIsOffOutsideTheDevEnvironment(): void
    {
        self::assertFalse(Vite::current()->useDevServer);
        self::assertFalse(Vite::current()->isDevServerRunning());
    }

    public function testRegisterScriptEntry(): void
    {
        $this->createVite()->register('/resources/js/app.ts');
        $view = $this->getView();

        self::assertSame(
            ['assets/app-BRBmoGS9.js' => '<script type="module" src="/dist/assets/app-BRBmoGS9.js" crossorigin integrity="sha384-app"></script>'],
            $view->jsFiles[View::POS_END] ?? [],
        );

        self::assertSame([
            'assets/shared-ChJ_j-JJ.css' => '<link href="/dist/assets/shared-ChJ_j-JJ.css" rel="stylesheet">',
            'assets/app-5UjPuW-k.css' => '<link href="/dist/assets/app-5UjPuW-k.css" rel="stylesheet">',
        ], $view->cssFiles);

        self::assertSame([
            'assets/shared-B7PI925R.js' => '<link href="/dist/assets/shared-B7PI925R.js" rel="modulepreload" crossorigin integrity="sha384-shared">',
            'assets/vendor-C1xUmRwd.js' => '<link href="/dist/assets/vendor-C1xUmRwd.js" rel="modulepreload" crossorigin>',
        ], $view->linkTags);
    }

    public function testScriptOptionsStayOnTheScript(): void
    {
        $this->createVite()->register('resources/js/app.ts', ['media' => 'screen'], [
            'position' => View::POS_HEAD,
            'crossorigin' => 'use-credentials',
            'data-entry' => 'app',
        ]);

        $view = $this->getView();

        self::assertSame(
            ['assets/app-BRBmoGS9.js' => '<script type="module" src="/dist/assets/app-BRBmoGS9.js" crossorigin="use-credentials" integrity="sha384-app" data-entry="app"></script>'],
            $view->jsFiles[View::POS_HEAD] ?? [],
        );

        self::assertStringContainsString('media="screen"', $view->cssFiles['assets/app-5UjPuW-k.css'] ?? '');
        self::assertSame('<link href="/dist/assets/vendor-C1xUmRwd.js" rel="modulepreload" crossorigin="use-credentials">', $view->linkTags['assets/vendor-C1xUmRwd.js'] ?? '');
    }

    public function testNonce(): void
    {
        $this->getView()->nonce = 'abc';
        $this->createVite()->register('resources/js/app.ts');

        $view = $this->getView();

        self::assertStringContainsString('nonce="abc"', $view->jsFiles[View::POS_END]['assets/app-BRBmoGS9.js'] ?? '');
        self::assertStringContainsString('nonce="abc"', $view->linkTags['assets/shared-B7PI925R.js'] ?? '');
    }

    public function testRegisterStylesheetEntry(): void
    {
        $this->createVite()->register('resources/css/print.scss', ['media' => 'print']);
        $view = $this->getView();

        self::assertSame(
            ['assets/print-D5sQo2Xn.css' => '<link href="/dist/assets/print-D5sQo2Xn.css" rel="stylesheet" media="print">'],
            $view->cssFiles,
        );

        self::assertSame([], $view->jsFiles);
    }

    public function testSharedChunksAreRegisteredOnce(): void
    {
        $vite = $this->createVite();
        $vite->register('resources/js/app.ts');
        $vite->register('resources/js/app.ts');

        self::assertCount(2, $this->getView()->linkTags);
        self::assertCount(2, $this->getView()->cssFiles);
    }

    public function testRegisterJsModule(): void
    {
        $this->createVite()->registerJsModule('resources/js/app.ts', ['id' => 1]);
        $view = $this->getView();

        self::assertSame(["import a from '/dist/assets/app-BRBmoGS9.js';"], array_values($view->js[View::POS_IMPORT] ?? []));
        self::assertSame(['a({"id":1});'], array_values($view->js[View::POS_MODULE] ?? []));
        self::assertCount(2, $view->cssFiles);
        self::assertSame([
            'assets/app-BRBmoGS9.js',
            'assets/shared-B7PI925R.js',
            'assets/vendor-C1xUmRwd.js',
        ], array_keys($view->linkTags));
        self::assertSame('<link href="/dist/assets/app-BRBmoGS9.js" rel="modulepreload" crossorigin integrity="sha384-app">', $view->linkTags['assets/app-BRBmoGS9.js']);
        self::assertSame([], $view->jsFiles);
    }

    public function testRegisterJsModuleWithoutPreload(): void
    {
        $this->createVite(['preloadJsModule' => false])->registerJsModule('resources/js/app.ts');

        self::assertSame([
            'assets/shared-B7PI925R.js',
            'assets/vendor-C1xUmRwd.js',
        ], array_keys($this->getView()->linkTags));
    }

    public function testGetUrlRegistersNothing(): void
    {
        $vite = $this->createVite();

        self::assertSame('/dist/assets/logo-BuPIv-Tb.svg', $vite->getUrl('resources/images/logo.svg'));
        self::assertSame('/dist/assets/app-BRBmoGS9.js', $vite->getUrl('/resources/js/app.ts'));

        $view = $this->getView();

        self::assertSame([], $view->jsFiles);
        self::assertSame([], $view->cssFiles);
        self::assertSame([], $view->linkTags);
    }

    public function testInlineCss(): void
    {
        $this->createVite(['inlineCssMaxSize' => 251])->register('resources/js/app.ts', ['media' => 'screen']);
        $view = $this->getView();

        self::assertSame([], $view->cssFiles);
        self::assertSame(['assets/shared-ChJ_j-JJ.css', 'assets/app-5UjPuW-k.css'], array_keys($view->css));
        self::assertSame("<style media=\"screen\">.shared{color:red}\n</style>", $view->css['assets/shared-ChJ_j-JJ.css'] ?? null);

        $css = $view->css['assets/app-5UjPuW-k.css'] ?? '';

        self::assertStringContainsString('url(/dist/assets/font-Ab12.woff2)', $css);
        self::assertStringContainsString('url("/fonts/x.woff")', $css);
        self::assertStringContainsString('url(data:image/png;base64,AA==)', $css);
        self::assertStringContainsString('url(#mask)', $css);
        self::assertStringContainsString('url(https://cdn.test/a.png)', $css);
        self::assertStringContainsString("url('/dist/assets/../images/bg.png')", $css);
        self::assertStringContainsString('/*# sourceMappingURL=/dist/assets/app-5UjPuW-k.css.map */', $css);
        self::assertCount(2, $view->linkTags);
    }

    public function testInlineCssOfAFileNamedWithAQueryString(): void
    {
        $this->createVite(['inlineCssMaxSize' => 1024])->register('resources/js/versioned.ts');

        self::assertSame(['versioned.css?v=hrp6o' => "<style>.versioned{background:url(/dist/bg.png)}\n</style>"], $this->getView()->css);
    }

    public function testInlineCssStylesheetEntry(): void
    {
        $this->createVite(['inlineCssMaxSize' => 1024])->register('resources/css/print.scss', ['media' => 'print']);
        $view = $this->getView();

        self::assertSame([], $view->cssFiles);
        self::assertSame(['assets/print-D5sQo2Xn.css' => "<style media=\"print\">.print{display:none}\n</style>"], $view->css);
    }

    public function testInlineCssLinksAnEntryTooLargeAsAWhole(): void
    {
        $this->createVite(['inlineCssMaxSize' => 250])->register('resources/js/app.ts');
        $view = $this->getView();

        self::assertSame([], $view->css);
        self::assertSame(['assets/shared-ChJ_j-JJ.css', 'assets/app-5UjPuW-k.css'], array_keys($view->cssFiles));
    }

    public function testInlineCssKeepsAStylesheetAlreadyLinked(): void
    {
        $this->getView()->registerCssFile('/dist/assets/shared-ChJ_j-JJ.css', [], 'assets/shared-ChJ_j-JJ.css');
        $this->createVite(['inlineCssMaxSize' => 1024])->register('resources/js/app.ts');
        $view = $this->getView();

        self::assertSame(['assets/shared-ChJ_j-JJ.css'], array_keys($view->cssFiles));
        self::assertSame(['assets/app-5UjPuW-k.css'], array_keys($view->css));
    }

    public function testInlineCssWithoutTheBuild(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->createVite(['inlineCssMaxSize' => 1024, 'basePath' => __DIR__ . '/Missing'])->register('resources/js/app.ts');
    }

    public function testBaseUrl(): void
    {
        Yii::setAlias('@web', '/sub');
        self::assertSame('/sub/dist/assets/logo-BuPIv-Tb.svg', $this->createVite(['baseUrl' => '@web/dist'])->getUrl('resources/images/logo.svg'));
        self::assertSame('https://cdn.test/build/assets/logo-BuPIv-Tb.svg', $this->createVite(['baseUrl' => 'https://cdn.test/build/'])->getUrl('resources/images/logo.svg'));
    }

    public function testDevServer(): void
    {
        $vite = $this->createDevServerVite();

        self::assertTrue($vite->isDevServerRunning());

        $vite->register('resources/js/app.ts');
        $vite->register('resources/css/print.scss');

        $view = $this->getView();

        self::assertSame(
            ['@vite/client' => '<script type="module" src="http://localhost:5173/@vite/client"></script>'],
            $view->jsFiles[View::POS_HEAD] ?? [],
        );

        self::assertSame(
            ['resources/js/app.ts' => '<script type="module" src="http://localhost:5173/resources/js/app.ts"></script>'],
            $view->jsFiles[View::POS_END] ?? [],
        );

        self::assertSame(
            ['resources/css/print.scss' => '<link href="http://localhost:5173/resources/css/print.scss" rel="stylesheet">'],
            $view->cssFiles,
        );

        self::assertSame([], $view->linkTags);
        self::assertSame('http://localhost:5173/resources/images/logo.svg', $vite->getUrl('resources/images/logo.svg'));
    }

    public function testDevServerJsModule(): void
    {
        $this->createDevServerVite()->registerJsModule('resources/js/app.ts', importName: false);
        $view = $this->getView();

        self::assertArrayHasKey('@vite/client', $view->jsFiles[View::POS_HEAD] ?? []);
        self::assertSame(["import 'http://localhost:5173/resources/js/app.ts';"], array_values($view->js[View::POS_IMPORT] ?? []));
        self::assertSame([], $view->linkTags);
    }

    public function testDevServerDownFallsBackToTheBuild(): void
    {
        $vite = $this->createDevServerVite();
        $url = (string)$vite->devServerInternalUrl;

        fclose($this->devServer ?? self::fail('No dev server socket.'));
        $this->devServer = null;

        $vite = $this->createVite(['useDevServer' => true, 'devServerInternalUrl' => $url]);

        self::assertFalse($vite->isDevServerRunning());
        self::assertSame('/dist/assets/app-BRBmoGS9.js', $vite->getUrl('resources/js/app.ts'));
    }

    public function testDevServerWithoutCheck(): void
    {
        $vite = $this->createVite([
            'useDevServer' => true,
            'checkDevServer' => false,
            'devServerInternalUrl' => 'http://127.0.0.1:1',
        ]);

        self::assertTrue($vite->isDevServerRunning());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createVite(array $config = []): Vite
    {
        return new Vite([
            'baseUrl' => '/dist/',
            'basePath' => __DIR__ . '/Data',
            'manifestPath' => __DIR__ . '/Data/manifest.json',
            ...$config,
        ]);
    }

    private function createDevServerVite(): Vite
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);

        $this->devServer = $server;
        $address = stream_socket_get_name($server, false);
        self::assertNotFalse($address);

        return $this->createVite([
            'useDevServer' => true,
            'devServerInternalUrl' => "http://$address",
        ]);
    }

    private function getView(): View
    {
        return Application::current()->getView();
    }
}
