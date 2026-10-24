---
title: Drives
order: 2
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

Each provider's README lists its own.

## Writing a provider

A provider is a factory: it builds the league's adapter from its options, and
`Flysystem\FlysystemDriveFactory::drive()` the drive around it.

```php
final class WebdavDriveFactory extends FlysystemDriveFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnidrive.factory_name' => 'webdav',
            'omnidrive.factory_title' => 'A WebDAV server',
            'omnidrive.required_options' => ['base_uri'],
        ]);
    }

    protected function build(Config $c, string $name): Drive
    {
        return $this->drive($name, new WebDAVAdapter(new Client(['baseUri' => $c->string('base_uri')])), $c);
    }
}
```

Its tests use `Omnidrive\Testing\DriveContractTests` and give the drive; each test works under a
directory of its own, removed afterwards:

```php
final class WebdavLiveTest extends TestCase
{
    use DriveContractTests;

    protected function drive(): Drive
    {
        return (new WebdavDriveFactory())->create(['base_uri' => getenv('WEBDAV_URI')]);
    }
}
```
