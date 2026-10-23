<?php

namespace Omnidrive\Flysystem;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use Omnidrive\Drive;
use Omnidrive\Exception\DriveException;
use Omnidrive\Exception\FileNotFoundException;
use Omnidrive\Exception\NotSupportedException;
use Omnidrive\Model\Capabilities;
use Omnidrive\Model\Entry;
use Omnidrive\Visibility;

/**
 * A Drive over a Flysystem operator: Flysystem is the engine, this class the only
 * place that speaks to it. Its exceptions become the contract's (the engine's
 * kept as the previous one), its attributes Entry, its visibility strings
 * Visibility.
 *
 * Built by a provider's factory (which knows its root and what it can do), or by
 * a host over the storages it already has (of(): the root of a local adapter and
 * the generators of public and temporary URLs read from the operator).
 */
final class FlysystemDrive implements Drive
{
    private readonly Capabilities $capabilities;

    /**
     * @param string|null $localRoot the directory of this machine behind the drive's paths; null for a remote drive
     */
    public function __construct(
        private readonly string $name,
        private readonly FilesystemOperator $operator,
        private readonly ?string $localRoot = null,
        ?Capabilities $capabilities = null,
    ) {
        $this->capabilities = $capabilities ?? Introspection::capabilities($operator, null !== $localRoot);
    }

    /** A drive over an operator a host gives: its local root, if any, read from its adapter. */
    public static function of(string $name, FilesystemOperator $operator): self
    {
        return new self($name, $operator, Introspection::localRoot($operator));
    }

    /**
     * The engine, for a host that hands it to a library that speaks Flysystem
     * itself. An application keeps to Drive.
     */
    public function operator(): FilesystemOperator
    {
        return $this->operator;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function capabilities(): Capabilities
    {
        return $this->capabilities;
    }

    public function read(string $path): string
    {
        return $this->attempt('read', $path, fn () => $this->operator->read($path), true);
    }

    public function readStream(string $path)
    {
        return $this->attempt('read', $path, fn () => $this->operator->readStream($path), true);
    }

    public function write(string $path, string $contents, array $options = []): void
    {
        $this->attempt('write', $path, fn () => $this->operator->write($path, $contents, self::options($options)));
    }

    public function writeStream(string $path, $stream, array $options = []): void
    {
        if (!\is_resource($stream)) {
            throw DriveException::of('write', $this->name, $path, new \InvalidArgumentException('Not a stream: '.get_debug_type($stream)));
        }
        $this->attempt('write', $path, fn () => $this->operator->writeStream($path, $stream, self::options($options)));
    }

    public function exists(string $path): bool
    {
        return $this->attempt('exists', $path, fn () => $this->operator->fileExists($path));
    }

    public function directoryExists(string $path): bool
    {
        return $this->attempt('exists', $path, fn () => $this->operator->directoryExists($path));
    }

    public function delete(string $path): void
    {
        $this->attempt('delete', $path, fn () => $this->operator->delete($path));
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->operator->deleteDirectory($path);
        } catch (FilesystemException $e) {
            // A directory that is not there is no error, as a file is not (an FTP server refuses to remove it).
            if ($this->directoryExistsQuietly($path)) {
                throw DriveException::of('delete', $this->name, $path, $e);
            }
        }
    }

    public function createDirectory(string $path, array $options = []): void
    {
        $this->attempt('directory', $path, fn () => $this->operator->createDirectory($path, self::options($options)));
    }

    public function list(string $path = '', bool $deep = false): iterable
    {
        try {
            $listing = $this->operator->listContents($path, $deep);
            foreach ($listing as $attributes) {
                yield self::entry($attributes);
            }
        } catch (FilesystemException $e) {
            throw DriveException::of('list', $this->name, $path, $e);
        }
    }

    public function move(string $from, string $to): void
    {
        $this->attempt('move', $from, fn () => $this->operator->move($from, $to), true);
    }

    public function copy(string $from, string $to): void
    {
        $this->attempt('copy', $from, fn () => $this->operator->copy($from, $to), true);
    }

    public function metadata(string $path): Entry
    {
        return new Entry(
            ltrim($path, '/'),
            false,
            $this->size($path),
            $this->lastModified($path),
            $this->visibilityOrNull($path),
            $this->mimeTypeOrNull($path),
        );
    }

    public function size(string $path): int
    {
        return $this->attempt('metadata', $path, fn () => $this->operator->fileSize($path), true);
    }

    public function mimeType(string $path): string
    {
        return $this->attempt('metadata', $path, fn () => $this->operator->mimeType($path), true);
    }

    public function lastModified(string $path): \DateTimeImmutable
    {
        $timestamp = $this->attempt('metadata', $path, fn () => $this->operator->lastModified($path), true);

        return (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone('UTC'));
    }

    public function checksum(string $path, string $algorithm = 'md5'): string
    {
        if (!method_exists($this->operator, 'checksum')) {
            throw NotSupportedException::operation($this->name, 'checksums', 'its engine computes none');
        }

        return $this->attempt('checksum', $path, fn () => (string) $this->operator->checksum($path, ['checksum_algo' => $algorithm]), true);
    }

    public function visibility(string $path): Visibility
    {
        return Visibility::of($this->attempt('visibility', $path, fn () => $this->operator->visibility($path), true));
    }

    public function setVisibility(string $path, Visibility $visibility): void
    {
        $this->attempt('visibility', $path, fn () => $this->operator->setVisibility($path, $visibility->value), true);
    }

    public function publicUrl(string $path): string
    {
        if (!method_exists($this->operator, 'publicUrl')) {
            throw NotSupportedException::operation($this->name, 'public addresses', 'its engine gives none');
        }
        try {
            return (string) $this->operator->publicUrl($path);
        } catch (UnableToGeneratePublicUrl $e) {
            throw NotSupportedException::operation($this->name, 'public addresses', 'no "public_url" configured and its adapter gives none');
        } catch (FilesystemException $e) {
            throw DriveException::of('public_url', $this->name, $path, $e);
        }
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt): string
    {
        if (!method_exists($this->operator, 'temporaryUrl')) {
            throw NotSupportedException::operation($this->name, 'temporary addresses', 'its engine signs none');
        }
        try {
            return (string) $this->operator->temporaryUrl($path, $expiresAt);
        } catch (UnableToGenerateTemporaryUrl $e) {
            if (str_contains($e->getMessage(), 'No generator was configured')) {
                throw NotSupportedException::operation($this->name, 'temporary addresses', 'its adapter signs none');
            }
            throw DriveException::of('temporary_url', $this->name, $path, $e);
        } catch (FilesystemException $e) {
            throw DriveException::of('temporary_url', $this->name, $path, $e);
        }
    }

    public function localPath(string $path): ?string
    {
        return null === $this->localRoot ? null : (new PathPrefixer($this->localRoot))->prefixPath(ltrim($path, '/'));
    }

    /**
     * Runs one call to the engine; its failure becomes a DriveException, or a
     * FileNotFoundException when the file is not there (asked again only then).
     *
     * @template T
     *
     * @param \Closure(): T $call
     *
     * @return T
     */
    private function attempt(string $operation, string $path, \Closure $call, bool $file = false): mixed
    {
        try {
            return $call();
        } catch (FilesystemException $e) {
            if ($file && !$e instanceof UnableToCheckExistence && !$this->existsQuietly($path)) {
                throw FileNotFoundException::of($operation, $this->name, $path, $e);
            }
            throw DriveException::of($operation, $this->name, $path, $e);
        }
    }

    private function existsQuietly(string $path): bool
    {
        try {
            return $this->operator->fileExists($path);
        } catch (FilesystemException) {
            return true;
        }
    }

    private function directoryExistsQuietly(string $path): bool
    {
        try {
            return $this->operator->directoryExists($path);
        } catch (FilesystemException) {
            return true;
        }
    }

    private function visibilityOrNull(string $path): ?Visibility
    {
        try {
            return $this->visibility($path);
        } catch (DriveException) {
            return null; // a drive that keeps none (an SFTP server's permission it cannot read, a bucket without ACLs)
        }
    }

    private function mimeTypeOrNull(string $path): ?string
    {
        try {
            return $this->mimeType($path);
        } catch (DriveException) {
            return null;
        }
    }

    /**
     * The contract's options as Flysystem's config: a Visibility as its string.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function options(array $options): array
    {
        foreach (['visibility', 'directory_visibility'] as $key) {
            if (isset($options[$key]) && ($options[$key] instanceof Visibility || \is_string($options[$key]))) {
                $options[$key] = Visibility::of($options[$key])->value;
            }
        }

        return $options;
    }

    private static function entry(StorageAttributes $attributes): Entry
    {
        $visibility = $attributes->visibility();
        $modified = $attributes->lastModified();

        return new Entry(
            $attributes->path(),
            $attributes instanceof DirectoryAttributes,
            $attributes instanceof FileAttributes ? $attributes->fileSize() : null,
            null === $modified ? null : (new \DateTimeImmutable('@'.$modified))->setTimezone(new \DateTimeZone('UTC')),
            null === $visibility ? null : Visibility::tryFrom($visibility),
            $attributes instanceof FileAttributes ? $attributes->mimeType() : null,
        );
    }
}
