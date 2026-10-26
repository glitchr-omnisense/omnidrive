# omnisense/omnidrive

Omnidrive is one PHP contract for reading and writing files wherever they are kept - a directory
of this machine, an S3 bucket, an SFTP, FTP or WebDAV server, an Azure container, a Dropbox or
Google Drive folder - by the name the application gives each storage. The application type-hints
`Omnidrive\Drive` (one storage) and `Omnidrive\Drives` (the storages by name), and nothing else:

```php
$uploads = $drives->get('uploads');

$uploads->write('avatars/1.png', $bytes, ['visibility' => 'public']);
$uploads->read('avatars/1.png');                                   // the bytes
$uploads->publicUrl('avatars/1.png');                              // /uploads/avatars/1.png
$drives->get('archive')->temporaryUrl('2026/report.pdf', new DateTimeImmutable('+1 hour'));
foreach ($drives->get('archive')->list('2026', deep: true) as $entry) {
    echo $entry->path, ' ', $entry->size, "\n";
}
$uploads->capabilities();                                          // local? public addresses? signed ones?
```

[league/flysystem](https://flysystem.thephpleague.com) 3 is the engine: every drive is an
`Omnidrive\Flysystem\FlysystemDrive` over a Flysystem operator, and each provider package maps its
options onto an existing Flysystem adapter - none is rewritten. Flysystem appears in no signature
of the contract, and its exceptions become the contract's (`DriveException`,
`FileNotFoundException`), the engine's kept as `getPrevious()`. The core requires
`league/flysystem` (^3.0) and `psr/container`, and nothing of a framework. A framework that already
has its storages gives them as drives through omnibridge, rather than configuring them twice
([Hosts](hosts.md)).

## When to use it

- An application whose files may move from a local directory to a bucket or a server without its
  code changing: the configuration names the storage, the code reads and writes a `Drive`.
- Code shared across hosts (a Symfony site, its Laravel twin, a worker): each host gives its own
  storages as `Drives`, by the same names.
- Several storages at once - public uploads on a CDN-fronted bucket, private documents with signed
  links, backups on an SFTP server - each asked what it can do (`capabilities()`) before it is.

It is not a synchronisation tool, a file manager or a CDN; it keeps no index of its own, and a
drive reaches its server only when an operation asks it to.

## Providers

Eight packages. The capabilities are what each drive's `capabilities()` answers, probed in the
[harness](harness.md) on 2026-10-25 with its required options; "with X" means only once option X
is set. "Visibility" is whether the drive keeps a visibility per file.

| Package | Factory name | Required options | `local` | `publicUrls` | `temporaryUrls` | Visibility | Engine |
|---|---|---|---|---|---|---|---|
| [`omnidrive/local`](https://github.com/glitchr-omnisense/omnidrive-local) | `local` | `root` | yes | with `public_url` | - | yes (Unix modes) | league/flysystem-local |
| [`omnidrive/s3`](https://github.com/glitchr-omnisense/omnidrive-s3) | `s3` | `bucket` | - | yes (the bucket's address, or `public_url`) | yes (presigned) | the object's ACL, where the bucket keeps them | league/flysystem-async-aws-s3 |
| [`omnidrive/sftp`](https://github.com/glitchr-omnisense/omnidrive-sftp) | `sftp` | `host`, `username` | - | with `public_url` | - | yes (Unix modes) | league/flysystem-sftp-v3 |
| [`omnidrive/ftp`](https://github.com/glitchr-omnisense/omnidrive-ftp) | `ftp` | `host`, `username`, `password` | - | with `public_url` | - | yes (Unix modes) | league/flysystem-ftp |
| [`omnidrive/webdav`](https://github.com/glitchr-omnisense/omnidrive-webdav) | `webdav` | `base_uri` | - | yes (the file's WebDAV address, or `public_url`) | - | - | league/flysystem-webdav |
| [`omnidrive/azure`](https://github.com/glitchr-omnisense/omnidrive-azure) | `azure` | `container` | - | with `public_container` or `public_url` | yes (read-only SAS) | - | azure-oss/storage-blob-flysystem |
| [`omnidrive/dropbox`](https://github.com/glitchr-omnisense/omnidrive-dropbox) | `dropbox` | `refresh_token` (with `app_key`, `app_secret`) or `access_token` | - | with `public_url` | yes (Dropbox's link, four hours at most) | - | spatie/flysystem-dropbox |
| [`omnidrive/gdrive`](https://github.com/glitchr-omnisense/omnidrive-gdrive) | `gdrive` | `refresh_token` (with `client_id`, `client_secret`) or `service_account` | - | with `public_url` | - | yes (shared with anyone who has the link) | masbug/flysystem-google-drive-ext |

The core adds a drive in memory for tests, `Omnidrive\Testing\InMemoryDriveFactory` (`memory`,
league/flysystem-memory): no `local`, public addresses with `public_url`, a visibility per file.
Not provided: OneDrive (the README says why). Each provider's own manual lists its options,
credentials and behaviour.

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP, other storages.
- [Hosts](hosts.md): the storages of Symfony (flysystem-bundle) and Laravel (`config/filesystems.php`)
  as drives, through omnibridge.
- [Drives](drives.md): the contract method by method, `Entry`, `Capabilities`, `Visibility`,
  factories, `Config`, the registry, `FlysystemDrive`.
- Usage: [files](usage-files.md) (read, write, stream, list, move, metadata) and [addresses and
  visibility](usage-addresses.md) (public, temporary, local paths).
- [Configuration reference](configuration.md): every option of every factory.
- [Errors](errors.md): the exceptions, when each is thrown, what to catch.
- [Testing](testing.md): a drive in memory, the conformance suite test by test.
- [The Docker harness](harness.md): every provider and a server of each kind.
- [Writing a provider](providers.md).
- [Verified, and not](verified.md).
