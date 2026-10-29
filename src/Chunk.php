<?php

declare(strict_types=1);

namespace Hirtz\Vite;

final readonly class Chunk
{
    /**
     * `imports` are manifest keys, `css` built files.
     *
     * @param list<string> $imports
     * @param list<string> $css
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

    public function getPath(): string
    {
        return (string)strtok($this->file, '?#');
    }

    public function isStylesheet(): bool
    {
        return str_ends_with($this->getPath(), '.css');
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
