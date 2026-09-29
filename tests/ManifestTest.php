<?php

declare(strict_types=1);

namespace Hirtz\Vite\Tests;

use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Vite\Chunk;
use Hirtz\Vite\InvalidManifestException;
use Hirtz\Vite\Manifest;
use Yii;
use yii\helpers\FileHelper;

class ManifestTest extends TestCase
{
    public const string MANIFEST = __DIR__ . '/Data/manifest.json';

    public function testChunk(): void
    {
        $chunk = (new Manifest(self::MANIFEST))->getChunk('resources/js/app.ts');

        self::assertSame('assets/app-BRBmoGS9.js', $chunk->file);
        self::assertSame('resources/js/app.ts', $chunk->src);
        self::assertTrue($chunk->isEntry);
        self::assertSame(['_shared-B7PI925R.js', '_vendor-C1xUmRwd.js'], $chunk->imports);
        self::assertSame(['assets/app-5UjPuW-k.css'], $chunk->css);
        self::assertSame('sha384-app', $chunk->integrity);
        self::assertFalse($chunk->isStylesheet());
        self::assertTrue((new Manifest(self::MANIFEST))->getChunk('resources/css/print.scss')->isStylesheet());
    }

    public function testPathIsAnAlias(): void
    {
        Yii::setAlias('@viteTest', __DIR__ . '/Data');

        self::assertSame(self::MANIFEST, (new Manifest('@viteTest/manifest.json'))->path);
    }

    public function testImportedChunksAreDepthFirstAndUnique(): void
    {
        $chunks = (new Manifest(self::MANIFEST))->getImportedChunks('resources/js/app.ts');

        self::assertSame(['assets/shared-B7PI925R.js', 'assets/vendor-C1xUmRwd.js'], $this->files($chunks));
    }

    public function testDynamicImportsAreNotPreloaded(): void
    {
        $files = $this->files((new Manifest(self::MANIFEST))->getImportedChunks('resources/js/app.ts'));

        self::assertNotContains('assets/lazy-CvGQzq7L.js', $files);
    }

    public function testImportedCssComesBeforeTheEntrysOwn(): void
    {
        $files = (new Manifest(self::MANIFEST))->getCssFiles('resources/js/app.ts');

        self::assertSame(['assets/shared-ChJ_j-JJ.css', 'assets/app-5UjPuW-k.css'], $files);
    }

    public function testCyclicImportsTerminate(): void
    {
        $manifest = new Manifest(self::MANIFEST);

        self::assertSame(['assets/cycle-a-DxT5.js', 'assets/cycle-b-Bq2w.js'], $this->files($manifest->getImportedChunks('resources/js/cycle.ts')));
        self::assertSame(['assets/cycle-b-Ab1c.css', 'assets/cycle-a-Dd3e.css'], $manifest->getCssFiles('resources/js/cycle.ts'));
    }

    public function testUnknownEntry(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('"resources/js/missing.ts" is not in the Vite manifest');

        (new Manifest(self::MANIFEST))->getChunk('resources/js/missing.ts');
    }

    public function testMissingManifest(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('Run `vite build`');

        new Manifest(__DIR__ . '/Data/missing.json');
    }

    public function testInvalidManifest(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('is not valid JSON');

        $this->withManifest('{"resources/js/app.ts": ', static fn (string $path): Manifest => new Manifest($path));
    }

    public function testEntryWithoutFile(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('"resources/js/app.ts" has no file');

        $this->withManifest('{"resources/js/app.ts": {"src": "resources/js/app.ts"}}', static fn (string $path): Manifest => new Manifest($path));
    }

    /**
     * @param list<Chunk> $chunks
     * @return list<string>
     */
    private function files(array $chunks): array
    {
        return array_map(static fn (Chunk $chunk): string => $chunk->file, $chunks);
    }

    /**
     * @param callable(string): mixed $callback
     */
    private function withManifest(string $json, callable $callback): void
    {
        $dir = Yii::getAlias('@runtime/vite-manifest-test');
        FileHelper::createDirectory($dir);
        file_put_contents("$dir/manifest.json", $json);

        try {
            $callback("$dir/manifest.json");
        } finally {
            FileHelper::removeDirectory($dir);
        }
    }
}
