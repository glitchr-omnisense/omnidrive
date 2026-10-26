---
title: Drives
order: 20
---

# Drives

## The contract

`Omnidrive\Drive` is one storage; `Omnidrive\Drives` the storages by name (`get`, `has`,
`names`). `Registry` is `Drives` built from factories and options; `Flysystem\OperatorDrives` is
`Drives` over the Flysystem operators a host already has.

Paths are relative to the drive's root and `/` separated; a leading `/` is the same path. A write
replaces the file and makes the directories it needs. A missing file or directory deleted is no
error; a missing file read, measured, moved or copied is a `FileNotFoundException`.

| Exception | When |
|---|---|
| `DriveException` | an operation failed: `$operation` (read, write, delete, move, copy, list, exists, directory, metadata, checksum, visibility, public_url, temporary_url), `$drive`, `$path`; the engine's exception as `getPrevious()` |
| `FileNotFoundException` (a `DriveException`) | the file is not there |
| `NotSupportedException` | the drive does not do that - a public address without `public_url` on SFTP, a temporary one where the provider signs none (S3, Azure and Dropbox sign them) |
| `InvalidConfigException` | an unknown drive or factory, a required option missing |

All implement `Omnidrive\Exception\OmnidriveException`.

`capabilities()` says beforehand what the drive can do besides reading and writing:

| | local | S3 | SFTP | FTP | WebDAV | Azure | Dropbox | Google Drive | memory |
|---|---|---|---|---|---|---|---|---|---|
| `local` (`localPath()`) | yes | - | - | - | - | - | - | - | - |
| `publicUrls` | with `public_url` | yes (the bucket's address, or `public_url`) | with `public_url` | with `public_url` | yes (the file's WebDAV address, or `public_url`) | with `public_container` or `public_url` | with `public_url` | with `public_url` | with `public_url` |
| `temporaryUrls` | - | yes (signed) | - | - | - | yes (a read-only SAS) | yes (Dropbox's link, four hours at most) | - | - |
| a visibility per file | yes | the object's ACL, where the bucket keeps them | yes | yes | - | - | - | yes (shared with anyone who has the link) | yes |

A provider whose adapter's own public address would be a credential - Azure's, for a private
container, is a SAS valid a thousand years - passes `false` as `drive()`'s `$adapterPublicUrls`:
the drive then gives a public address only with `public_url`, and says so in its capabilities.

## Options every drive takes

| Option | |
|---|---|
| `public_url` | the prefix files are served from (`/uploads`, `https://cdn.example.org`), or a list of them - Flysystem shards the paths over it |
| `visibility` | what a file is written with when the write does not say (`public`, `private`; Flysystem's default otherwise) |
| `directory_visibility` | the same for a directory |
| `retain_visibility` | whether a copy keeps its source's visibility (true); false for a server that cannot report it |
| `permissions` | local, SFTP and FTP: the Unix mode of each visibility, `{file: {public: 0644, private: 0600}, dir: {public: 0755, private: 0700}}`, octal strings accepted |

Each provider's manual lists its own; [Configuration](configuration.md) lists them all.

## Drive, method by method

`Omnidrive\Drive` is an interface; every drive the providers and the hosts give is an
`Omnidrive\Flysystem\FlysystemDrive`. Paths below are relative to the drive's root.

| Method | Returns | |
|---|---|---|
| `name(): string` | | the name the drive is configured with (the registry's key, the host's storage name) |
| `capabilities(): Capabilities` | | what it can do besides reading and writing |
| `read(string $path): string` | the contents | `FileNotFoundException` when the file is not there |
| `readStream(string $path)` | a resource | for large files; the caller closes it |
| `write(string $path, string $contents, array $options = []): void` | | replaces the file, makes missing directories; options `visibility` (a `Visibility` or `public`/`private`), `directory_visibility`, and whatever the engine takes (`mimetype`, an S3 `ContentType`...) |
| `writeStream(string $path, $stream, array $options = []): void` | | the same from a stream; not a resource: `DriveException`. An engine may close the stream once written (Azure's, Dropbox's HTTP clients): check `is_resource()` before closing it |
| `exists(string $path): bool` | | whether a file is there; a directory is not a file |
| `directoryExists(string $path): bool` | | |
| `delete(string $path): void` | | a file that is not there is no error |
| `deleteDirectory(string $path): void` | | the directory and everything under it; one that is not there is no error |
| `createDirectory(string $path, array $options = []): void` | | option `directory_visibility`; on a flat namespace the adapter decides what a directory is: Azure's makes nothing, a directory exists while it holds a blob |
| `list(string $path = '', bool $deep = false): iterable<Entry>` | lazily | files and directories, in the storage's order; `$deep` goes down every directory |
| `move(string $from, string $to): void`, `copy(string $from, string $to): void` | | within the drive; a missing source is a `FileNotFoundException` |
| `metadata(string $path): Entry` | | size, last change, visibility and media type at once (four calls to a remote drive); a visibility or type the drive cannot read is `null` |
| `size()`, `mimeType()`, `lastModified()` | `int`, `string`, `DateTimeImmutable` (UTC) | one call each |
| `checksum(string $path, string $algorithm = 'md5'): string` | hex digest | any algorithm `hash()` knows when the engine reads and hashes the file; an engine without checksums: `NotSupportedException` |
| `visibility(string $path): Visibility`, `setVisibility(string $path, Visibility $v): void` | | a drive that keeps none: `DriveException` |
| `publicUrl(string $path): string` | | where anyone fetches the file, whether it is there or not; none: `NotSupportedException` |
| `temporaryUrl(string $path, \DateTimeInterface $expiresAt): string` | | a signed address serving the file until `$expiresAt`; none: `NotSupportedException` |
| `localPath(string $path): ?string` | | the file's path on this machine, whether it is there or not; `null` for a remote drive |

`FlysystemDrive` also has `operator(): FilesystemOperator`, the engine, for a host that hands it to
a library speaking Flysystem itself, and `FlysystemDrive::of(string $name, FilesystemOperator $operator)`,
a drive over a host's operator with its local root and capabilities read from it.

## Entry

What a listing or `metadata()` gives of a file or a directory: what the drive told, `null` where
it told nothing (a listing usually gives no media type, a bucket no directory's date).

| Property / method | Type | |
|---|---|---|
| `path` | `string` | relative to the drive's root, without a leading `/` |
| `directory` | `bool` | `false` for a file |
| `size` | `?int` | bytes; `null` for a directory |
| `lastModified` | `?\DateTimeImmutable` | UTC |
| `visibility` | `?Visibility` | |
| `mimeType` | `?string` | |
| `isFile(): bool` | | `!directory` |
| `basename(): string` | | the last segment of the path |
| `toArray(): array` | | the properties, `lastModified` as ATOM, `visibility` as its string |

## Capabilities

`Omnidrive\Model\Capabilities` (`final readonly`): `local` (`localPath()` gives a file of this
machine), `publicUrls` (`publicUrl()` answers), `temporaryUrls` (`temporaryUrl()` answers), all
`false` by default; `toArray()`. A provider's drive is given them by its factory
(`FlysystemDriveFactory::drive()`): `local` when it passes a local root, `publicUrls` when a
`public_url` is set or the adapter generates public URLs (and the factory lets it), `temporaryUrls`
when the adapter signs. The [index](index.md#providers) lists them per provider.

## Visibility

`Omnidrive\Visibility`, a string-backed enum: `Public` (`public`: anyone may read - a public
object, a file the web server serves, mode 0644) and `Private` (`private`: the drive's own user,
0600, a private object). `Visibility::of(Visibility|string $v)` accepts a case or its name in any
case, trimmed (an unknown name is PHP's `ValueError`).

## Drives and the registry

`Omnidrive\Drives` is the storages by name: `get(string $name): Drive` (an unknown name is an
`InvalidConfigException`), `has(string $name): bool`, `names(): list<string>` (as far as they are
known). Two implementations:

- `Omnidrive\Registry`, built from factories and options:

  | Method | |
  |---|---|
  | `new Registry(iterable $factories, array $config)` | factories keyed by `getName()`; `$config` is `name => ['factory' => ..., 'options' => [...]]` |
  | `get(string $name): Drive` | built on the first call, with `$name` as the drive's name, and kept |
  | `has()`, `names()` | from the configuration, in its order |
  | `options(string $name): array` | the options a drive is configured with |
  | `create(string $name, array $overrides = []): Drive` | built afresh with `$overrides` over its options (`array_replace`); not kept |
  | `all(): array<string, Drive>` | every drive, built |
  | `factories(): list<string>` | the factories installed |

  `No "x" drive; configured: uploads, archive.` and `No "s3" factory for the "archive" drive;
  installed: local.` are `InvalidConfigException`s.
- `Omnidrive\Flysystem\OperatorDrives`, over a host's operators ([Hosts](hosts.md)).

## Factories and Config

`Omnidrive\DriveFactoryInterface`: `getName(): string` (the `factory` a configuration names) and
`create(array $options = [], ?string $name = null): Drive` (`$name` is the drive's name, the
factory's when `null`). `Omnidrive\DriveFactory` implements both: `createConfig($options)` makes a
`Config` of the options and calls the factory's `populate()`, which sets its name
(`omnidrive.factory_name`), its title (`omnidrive.factory_title`), its required options
(`omnidrive.required_options`) and the defaults of the others; `create()` checks the required ones
(missing, `''` or `[]`: `The "s3" drive needs: bucket.`) and calls `build(Config $c, string $name)`.
Nothing connects there: a drive reaches its server on its first operation (the Google Drive
provider's `root` given as a path of names is the one exception: the folder is looked up when the
drive is built).

`Omnidrive\Flysystem\FlysystemDriveFactory` is the providers' base: `drive($name, $adapter, $c,
?string $localRoot = null, bool $adapterPublicUrls = true)` wraps an adapter in a `Filesystem` with
the options every drive takes and returns the `FlysystemDrive`; `unixVisibility($c)` builds
Flysystem's `PortableVisibilityConverter` from `permissions` and `directory_visibility`.

`Omnidrive\Config` reads options as configuration and environment variables give them:

| Method | |
|---|---|
| `defaults(array $defaults): self` | sets each key not set yet |
| `validateNotEmpty(list<string> $keys): void` | the required check above |
| `get(string $key, mixed $default = null)` | |
| `string(string $key): ?string` | a scalar as a string, `null` when unset or `''` |
| `int(string $key, int $default = 0): int` | a numeric value |
| `octal(string $key, int $default): int` | an integer as it is (`0644`), or a string of digits read as octal (`"0644"`, `"644"`) |
| `bool(string $key, bool $default = false): bool` | `$default` when unset or `''`; `true` for `true` and `1`, `true`, `yes`, `on` |
| `list(string $key): list<string>` | an array, or a comma-separated string, split and trimmed (`"a, b"` is `['a', 'b']`) |
