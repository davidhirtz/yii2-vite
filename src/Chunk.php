<?php

declare(strict_types=1);

namespace Hirtz\Vite;

/**
 * One entry of Vite's `manifest.json`: a chunk, a stylesheet or a plain asset.
 */
final readonly class Chunk
{
    /**
     * @param list<string> $imports the manifest keys of the chunks this one imports statically
     * @param list<string> $css the stylesheets this chunk imports, as built files
     */
    public function __construct(
        public string $name,
        public string $file,
        public ?string $src = null,
        public bool $isEntry = false,
        public array $imports = [],
        public array $css = [],
        public ?string $integrity = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(string $name, array $data): self
    {
        $file = $data['file'] ?? null;

        if (!is_string($file) || $file === '') {
            throw new InvalidManifestException("Vite manifest entry \"$name\" has no file.");
        }

        return new self(
            name: $name,
            file: $file,
            src: is_string($data['src'] ?? null) ? $data['src'] : null,
            isEntry: ($data['isEntry'] ?? false) === true,
            imports: self::strings($data['imports'] ?? []),
            css: self::strings($data['css'] ?? []),
            integrity: is_string($data['integrity'] ?? null) ? $data['integrity'] : null,
        );
    }

    public function isStylesheet(): bool
    {
        return str_ends_with($this->file, '.css');
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
