---
title: Testing
order: 60
---

# Testing

Testing an application that reads and writes through omnidrive, and the conformance suite every
drive passes. Both ship with the core (`Omnidrive\Testing`, autoloaded with it).

## A drive in memory

`Omnidrive\Testing\InMemoryDriveFactory` (factory name `memory`) is a drive in this process's
memory over league/flysystem-memory: what is written is read back until the process ends, nothing
touches a disk or a network. It takes the options every drive takes (`public_url`, `visibility`...)
and keeps a visibility per file; it gives no local path and no temporary address. Without
`league/flysystem-memory` (`composer require --dev league/flysystem-memory`) building it is an
`InvalidConfigException` that says so.

Configure the drive the code uses with the `memory` factory in the tests, under the same name:

```php
<?php

use Omnidrive\Drive;
use Omnidrive\Drives;
use Omnidrive\Registry;
use Omnidrive\Testing\InMemoryDriveFactory;
use PHPUnit\Framework\TestCase;

/** The application's code: it only knows Drives. */
final class AvatarStore
{
    public function __construct(private readonly Drives $drives)
    {
    }

    public function save(int $user, string $png): string
    {
        $uploads = $this->drives->get('uploads');
        $uploads->write("avatars/$user.png", $png, ['visibility' => 'public']);

        return $uploads->publicUrl("avatars/$user.png");
    }
}

final class AvatarStoreTest extends TestCase
{
    public function testAnAvatarIsKeptAndServed(): void
    {
        $drives = new Registry([new InMemoryDriveFactory()], [
            'uploads' => ['factory' => 'memory', 'options' => ['public_url' => '/uploads']],
        ]);

        self::assertSame('/uploads/avatars/7.png', (new AvatarStore($drives))->save(7, "\x89PNG..."));
        self::assertSame("\x89PNG...", $drives->get('uploads')->read('avatars/7.png'));
        self::assertSame('public', $drives->get('uploads')->visibility('avatars/7.png')->value);
    }
}
```

```
$ vendor/bin/phpunit --testdox tests/
Avatar Store
 ✔ An avatar is kept and served

Scratch Contract
 ✔ A file is written read and deleted
 ✔ Streams are written and read
 ✔ A file that is not there is not found
 ✔ A directory is listed shallow and deep
 ✔ A file is moved and copied
 ✔ Directories are made and deleted
 ✔ Metadata and checksum are the file
 ✔ Visibility is written and changed
 ✔ Addresses and local paths are what the capabilities say

OK (10 tests, 45 assertions)
```

(run on 2026-10-25 in the [harness](harness.md), PHPUnit 11.5, with the provider of
[Writing a provider](providers.md#a-whole-provider) and its contract test)

For a code path that needs a file of this machine (`localPath()`), use a local drive on a
temporary directory instead (`new LocalDriveFactory()` with `root` under `sys_get_temp_dir()`), and
delete it in `tearDown()`. For a failure, a drive whose engine fails: the memory drive cannot be
told to fail; an S3 drive given a `MockHttpClient` answering 500 can
(`new S3DriveFactory(new MockHttpClient(...))`).

### In a Symfony or Laravel test

Under Symfony, configure the storage in the test environment as flysystem-bundle's `memory`
adapter (`config/packages/test/flysystem.yaml`): `Omnidrive\Drives` gives it by the same name.
Under Laravel, `Storage::fake('public')` replaces the disk, and the drive mapped to it reads the
fake ([Hosts](hosts.md)). omnibridge/symfony's `Tests/DrivesTest.php` (a `memory.uploads`
storage) and omnibridge/laravel's `Tests/DrivesTest.php` (local disks) test the bridges this way;
`Storage::fake()` was not run with omnidrive for this page. Resolve the drive after faking the
disk: `OperatorDrives` keeps a drive once it has given it.

## Conformance suite: each test

`Omnidrive\Testing\DriveContractTests` is a PHPUnit trait: a test case uses it and gives the drive
(a provider's, or an application's drive of its own). Each test works under a directory of its own
(`omnidrive-contract-<random>`), deleted after the test, so a shared bucket or server is left as it
was. It reaches whatever the drive reaches: the providers run it offline on the local and memory
drives, live against the harness's servers (`*LiveTest`), and against a stand-in API for Dropbox.

| Hook | Default | |
|---|---|---|
| `drive(): Drive` | abstract | the drive under test; called once per test |
| `keepsVisibility(): bool` | `true` | `false` for a drive that keeps no visibility per file (S3 without ACLs, WebDAV, Azure, Dropbox): the visibility test is then skipped, after its write |

| Test | What it checks |
|---|---|
| `testAFileIsWrittenReadAndDeleted` | a file not there, written, there, read back, 13 bytes, `text/plain`; written again replaces it; deleted, gone; deleted again, no error |
| `testStreamsAreWrittenAndRead` | 300 000 random bytes written from a stream and read back as one, their size |
| `testAFileThatIsNotThereIsNotFound` | `read()` of a missing file is a `FileNotFoundException` with `operation` `read`, the drive's name and the path; `size()` too |
| `testADirectoryIsListedShallowAndDeep` | three files at three depths: the shallow listing is exactly one file and one directory, the deep one holds every file, a listed file has its size and basename |
| `testAFileIsMovedAndCopied` | a copy leaves the source; a move removes it; moving a missing file is a `FileNotFoundException` |
| `testDirectoriesAreMadeAndDeleted` | a directory made with its parent exists and is not a file; deleted with a file in it, both gone |
| `testMetadataAndChecksumAreTheFile` | `metadata()` of an HTML file: a file, 31 bytes, `text/html`, its date within two minutes of the test's clock; its MD5 checksum |
| `testVisibilityIsWrittenAndChanged` | written `Private`, read `Private`; set `Public`, read `Public` (skipped when `keepsVisibility()` is false) |
| `testAddressesAndLocalPathsAreWhatTheCapabilitiesSay` | `publicUrl()` and `temporaryUrl()` hold the file's name when declared, are a `NotSupportedException` when not; `localPath()` is an existing file with the contents when `local`, `null` otherwise |

A provider adds what is particular to its service beside it (Azure overrides the directory test:
its namespace is flat; S3 and Azure add a signed address fetched and the plain one refused).
Requires `phpunit/phpunit` in the provider's (or the application's) `require-dev`.
