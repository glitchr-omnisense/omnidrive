<?php

namespace Omnidrive;

use Omnidrive\Exception\DriveException;
use Omnidrive\Exception\FileNotFoundException;
use Omnidrive\Exception\NotSupportedException;
use Omnidrive\Model\Capabilities;
use Omnidrive\Model\Entry;

/**
 * One storage, by the name the application gives it ("uploads", "s3.private"...):
 * files read and written by their path in it, wherever it is - a directory of this
 * machine, a bucket, an SFTP or FTP server.
 *
 * A path is relative to the drive's root, "/" separated ("avatars/1.png"); a
 * leading "/" is the same path. Nothing here names how the drive is reached: an
 * application type-hints Drive (and Drives), never the engine behind it.
 *
 * What fails is a DriveException (FileNotFoundException when the file is not
 * there), what the drive cannot do a NotSupportedException - its Capabilities say
 * so beforehand.
 */
interface Drive
{
    /** The name the drive is configured with. */
    public function name(): string;

    /** What the drive can do besides reading and writing: a local file, public and temporary URLs. */
    public function capabilities(): Capabilities;

    /** @throws FileNotFoundException|DriveException */
    public function read(string $path): string;

    /**
     * The file as a stream, for large files: the caller closes it.
     *
     * @return resource
     *
     * @throws FileNotFoundException|DriveException
     */
    public function readStream(string $path);

    /**
     * Writes the file, replacing it when it is there; missing directories are made.
     *
     * @param array<string, mixed> $options "visibility" (Visibility or "public"/"private"),
     *                                      "directory_visibility", and whatever the drive's engine takes
     *
     * @throws DriveException
     */
    public function write(string $path, string $contents, array $options = []): void;

    /**
     * @param resource             $stream
     * @param array<string, mixed> $options as write()
     *
     * @throws DriveException
     */
    public function writeStream(string $path, $stream, array $options = []): void;

    /** Whether a file is there (a directory is not a file). */
    public function exists(string $path): bool;

    public function directoryExists(string $path): bool;

    /** Deletes the file; a file that is not there is no error. */
    public function delete(string $path): void;

    /** Deletes the directory and everything under it; a directory that is not there is no error. */
    public function deleteDirectory(string $path): void;

    /** @param array<string, mixed> $options "directory_visibility" */
    public function createDirectory(string $path, array $options = []): void;

    /**
     * What a directory holds, files and directories, lazily; $deep goes down every
     * directory under it.
     *
     * @return iterable<Entry>
     *
     * @throws DriveException
     */
    public function list(string $path = '', bool $deep = false): iterable;

    /** @throws FileNotFoundException|DriveException */
    public function move(string $from, string $to): void;

    /** @throws FileNotFoundException|DriveException */
    public function copy(string $from, string $to): void;

    /**
     * The file's size, type, last change and visibility, as one Entry (several calls
     * to a remote drive: ask size() or mimeType() alone when that is all you need).
     *
     * @throws FileNotFoundException|DriveException
     */
    public function metadata(string $path): Entry;

    /** In bytes. @throws FileNotFoundException|DriveException */
    public function size(string $path): int;

    /** @throws FileNotFoundException|DriveException */
    public function mimeType(string $path): string;

    /** @throws FileNotFoundException|DriveException */
    public function lastModified(string $path): \DateTimeImmutable;

    /** @throws FileNotFoundException|DriveException */
    public function checksum(string $path, string $algorithm = 'md5'): string;

    /** @throws FileNotFoundException|DriveException */
    public function visibility(string $path): Visibility;

    /** @throws FileNotFoundException|DriveException */
    public function setVisibility(string $path, Visibility $visibility): void;

    /**
     * Where anyone fetches the file from: the drive's public address (its
     * "public_url" option, or a bucket's own), whether the file is there or not.
     *
     * @throws NotSupportedException when the drive has none (Capabilities::$publicUrls)
     */
    public function publicUrl(string $path): string;

    /**
     * A signed address that serves the file until $expiresAt, to whoever holds it.
     *
     * @throws NotSupportedException when the drive cannot sign one (Capabilities::$temporaryUrls)
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt): string;

    /**
     * The file's path on this machine, whether it is there or not; null for a drive
     * with no local file behind its paths (a bucket, a server).
     */
    public function localPath(string $path): ?string;
}
