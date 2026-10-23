<?php

namespace Omnidrive\Model;

use Omnidrive\Visibility;

/**
 * A file or a directory of a drive, as a listing or metadata() gives it: what the
 * drive told, null where it told nothing (a listing gives no type, a bucket no
 * directory's date).
 */
final readonly class Entry
{
    public function __construct(
        public string $path,
        public bool $directory = false,
        public ?int $size = null,
        public ?\DateTimeImmutable $lastModified = null,
        public ?Visibility $visibility = null,
        public ?string $mimeType = null,
    ) {
    }

    public function isFile(): bool
    {
        return !$this->directory;
    }

    /** The last segment of the path. */
    public function basename(): string
    {
        return basename($this->path);
    }

    /** @return array{path: string, directory: bool, size: ?int, lastModified: ?string, visibility: ?string, mimeType: ?string} */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'directory' => $this->directory,
            'size' => $this->size,
            'lastModified' => $this->lastModified?->format(\DATE_ATOM),
            'visibility' => $this->visibility?->value,
            'mimeType' => $this->mimeType,
        ];
    }
}
