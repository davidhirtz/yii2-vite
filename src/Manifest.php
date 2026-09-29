<?php

declare(strict_types=1);

namespace Hirtz\Vite;

use JsonException;
use Yii;

/**
 * Vite's `manifest.json`, written by `vite build` with the `build.manifest` option.
 */
class Manifest
{
    public readonly string $path;

    /**
     * @var array<string, Chunk>
     */
    private array $chunks = [];

    public function __construct(string $path)
    {
        $this->path = Yii::getAlias($path);

        foreach ($this->read() as $name => $data) {
            if (is_array($data)) {
                $this->chunks[(string)$name] = Chunk::fromArray((string)$name, $data);
            }
        }
    }

    public function has(string $name): bool
    {
        return isset($this->chunks[$name]);
    }

    public function getChunk(string $name): Chunk
    {
        return $this->chunks[$name]
            ?? throw new InvalidManifestException("\"$name\" is not in the Vite manifest at \"$this->path\". Is it an entry of `build.rollupOptions.input`?");
    }

    /**
     * The chunks an entry imports statically, depth-first, each once, without the entry itself. These are what a
     * browser would otherwise discover one round trip at a time, so they are preloaded.
     *
     * @return list<Chunk>
     */
    public function getImportedChunks(string $name): array
    {
        $chunks = [];
        $this->collectImports($this->getChunk($name), $chunks);

        unset($chunks[$name]);

        return array_values($chunks);
    }

    /**
     * The stylesheets an entry needs, in the order Vite's own HTML links them: an imported chunk's before the
     * importing one's, so the entry's rules come last and win.
     *
     * @return list<string>
     */
    public function getCssFiles(string $name): array
    {
        $files = [];
        $seen = [];

        $this->collectCss($this->getChunk($name), $files, $seen);

        return array_values(array_unique($files));
    }

    /**
     * @param array<string, Chunk> $chunks
     */
    private function collectImports(Chunk $chunk, array &$chunks): void
    {
        $chunks[$chunk->name] = $chunk;

        foreach ($chunk->imports as $import) {
            if (!isset($chunks[$import])) {
                $this->collectImports($this->getChunk($import), $chunks);
            }
        }
    }

    /**
     * @param list<string> $files
     * @param array<string, true> $seen
     */
    private function collectCss(Chunk $chunk, array &$files, array &$seen): void
    {
        $seen[$chunk->name] = true;

        foreach ($chunk->imports as $import) {
            if (!isset($seen[$import])) {
                $this->collectCss($this->getChunk($import), $files, $seen);
            }
        }

        $files = [...$files, ...$chunk->css];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function read(): array
    {
        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            throw new InvalidManifestException("Vite manifest \"$this->path\" not found. Run `vite build` with `build.manifest` enabled, or start the dev server.");
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidManifestException("Vite manifest \"$this->path\" is not valid JSON: {$exception->getMessage()}", previous: $exception);
        }

        return is_array($data) ? $data : throw new InvalidManifestException("Vite manifest \"$this->path\" is not a JSON object.");
    }
}
