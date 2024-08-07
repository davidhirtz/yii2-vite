<?php

namespace davidhirtz\yii2\vite\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

class Manifest
{
    final public const TYPE_JS = 'js';
    final public const TYPE_CSS = 'css';
    final public const TYPE_LINK = 'link';

    public readonly array $data;
    public readonly string $path;

    public function __construct(string $path)
    {
        $this->path = Yii::getAlias($path);
        $this->data = $this->getManifestContents();
    }

    protected function getManifestContents(): array
    {
        return json_decode(file_get_contents($this->path), true);
    }

    public function getTagsForPath(string $path, array $cssOptions = [], array $jsOptions = []): array
    {
        $data = $this->data[$path] ?? [];

        if (empty($data['file'])) {
            throw new InvalidConfigException("File \"$path\" not found in Vite manifest.");
        }

        $tags = [
            $path => [
                'type' => self::TYPE_JS,
                'url' => $data['file'],
                'options' => [
                    'crossorigin' => true,
                    'integrity' => $data['integrity'] ?? null,
                    'type' => 'module',
                    ...$jsOptions,
                ],
            ],
        ];

        $importFiles = [];
        $this->extractImportFiles($path, $importFiles);

        foreach ($importFiles as $key => $file) {
            $tags[$file] = [
                'type' => self::TYPE_LINK,
                'url' => $file,
                'options' => [
                    'crossorigin' => true,
                    'integrity' => $this->data[$key]['integrity'] ?? null,
                    'rel' => 'modulepreload',
                    ...$jsOptions,
                ],
            ];
        }

        $asyncCss = ArrayHelper::remove($cssOptions, 'async', false);

        if ($asyncCss) {
            $cssOptions['media'] ??= 'print';
            $cssOptions['onload'] ??= "this.media='all'";
        }

        $cssFiles = [];
        $this->extractCssFiles($path, $cssFiles);

        foreach ($cssFiles ?? [] as $file) {
            $tags[$file] = [
                'type' => self::TYPE_CSS,
                'url' => $file,
                'options' => [
                    'rel' => 'stylesheet',
                    ...$cssOptions
                ],
            ];
        }

        return $tags;
    }

    protected function extractImportFiles(string $manifestKey, array &$importFiles): void
    {
        $entry = $this->data[$manifestKey] ?? null;

        foreach ($entry['imports'] ?? [] as $import) {
            $importFiles[$import] = $this->data[$import]['file'];
            $this->extractImportFiles($import, $importFiles);
        }
    }

    protected function extractCssFiles(string $manifestKey, array &$cssFiles): void
    {
        $entry = $this->data[$manifestKey] ?? null;

        $cssFiles = [
            ...$cssFiles,
            ...($entry['css'] ?? []),
        ];

        foreach ($entry['imports'] ?? [] as $import) {
            $this->extractCssFiles($import, $cssFiles);
        }
    }
}
