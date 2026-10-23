# omniscient/omnidrive

One contract for reading and writing files wherever they are kept - a directory of this machine, an
S3 bucket, an SFTP (SSH) or FTP server - by the name the application gives each storage.

```php
use Omnidrive\Local\LocalDriveFactory;
use Omnidrive\Registry;
use Omnidrive\S3\S3DriveFactory;

$drives = new Registry([new LocalDriveFactory(), new S3DriveFactory()], [
    'uploads' => ['factory' => 'local', 'options' => ['root' => __DIR__.'/var/uploads', 'public_url' => '/uploads']],
    'archive' => ['factory' => 's3', 'options' => ['bucket' => 'archive', 'region' => 'eu-west-3', 'key' => getenv('S3_KEY'), 'secret' => getenv('S3_SECRET')]],
]);

$drives->get('uploads')->write('avatars/1.png', $bytes);
$drives->get('uploads')->publicUrl('avatars/1.png');             // /uploads/avatars/1.png
$drives->get('archive')->temporaryUrl('2026/report.pdf', new DateTimeImmutable('+1 hour'));
foreach ($drives->get('archive')->list('2026', deep: true) as $entry) {
    echo $entry->path, ' ', $entry->size, "\n";
}
```

No bundle, no container: a factory per provider package, the registry built by hand.
[docs/installation.md](docs/installation.md) opens on a whole script that runs as it is.

## The contract, and Flysystem behind it

An application type-hints `Omnidrive\Drive` (one storage) and `Omnidrive\Drives` (the storages by
name), and nothing else:

| `Drive` | |
|---|---|
| `read`, `readStream`, `write`, `writeStream` | the bytes, as a string or a stream; a write replaces, missing directories are made |
| `exists`, `directoryExists`, `delete`, `deleteDirectory`, `createDirectory` | a missing file or directory deleted is no error |
| `list($path, $deep)` | lazily, `Entry` objects: path, directory or file, size, last change, visibility |
| `move`, `copy` | within the drive |
| `metadata`, `size`, `mimeType`, `lastModified`, `checksum` | |
| `visibility`, `setVisibility` | `Visibility::Public` or `Private` |
| `publicUrl`, `temporaryUrl($path, $expiresAt)` | the public address, a signed one that expires |
| `localPath` | the file of this machine behind the path, null for a remote drive |
| `capabilities()` | said beforehand: `local`, `publicUrls`, `temporaryUrls` |

What fails is an `Omnidrive\Exception\DriveException` - its `operation`, `drive` and `path` kept,
`FileNotFoundException` when the file is not there; what a drive cannot do (a temporary address
from an FTP server) is a `NotSupportedException`, and its `Capabilities` say so first.

[league/flysystem](https://flysystem.thephpleague.com) is the engine: every drive is a
`Omnidrive\Flysystem\FlysystemDrive` over a Flysystem operator, and each provider maps its options
onto the league's own adapter - no adapter is rewritten here, none is forked. Flysystem appears in
no signature of the contract: an application that keeps to `Drive` and `Drives` would not change
if the engine did. Its exceptions are kept as the contract's `previous` one. This package follows
Flysystem's current major (`^3.0`); a new major is taken when it ships.

## Providers

| Package | Drive | Engine |
|---|---|---|
| [`omnidrive/local`](https://github.com/glitchr-studio/omnidrive-local) | a directory of this machine: its root, Unix modes, links refused or skipped | league/flysystem-local |
| [`omnidrive/s3`](https://github.com/glitchr-studio/omnidrive-s3) | an S3 bucket - AWS, MinIO, Scaleway, OVHcloud, Cloudflare R2...: region or endpoint, keys, prefix, public and signed addresses | league/flysystem-async-aws-s3 (AsyncAws) |
| [`omnidrive/sftp`](https://github.com/glitchr-studio/omnidrive-sftp) | a directory of an SFTP server, over SSH: password, private key or agent, the server's key checked | league/flysystem-sftp-v3 (phpseclib 3) |
| [`omnidrive/ftp`](https://github.com/glitchr-studio/omnidrive-ftp) | a directory of an FTP or FTPS server: passive mode, a server behind NAT | league/flysystem-ftp (ext-ftp) |

Every drive also takes `public_url` (a prefix, or a list of them), `visibility`,
`directory_visibility` and `retain_visibility`; the local, SFTP and FTP drives `permissions` (the
Unix mode of each visibility). `Omnidrive\Testing\InMemoryDriveFactory` (league/flysystem-memory)
gives an application's tests a drive in memory, and `Omnidrive\Testing\DriveContractTests` the
tests every drive passes - for a drive of one's own too.

## Hosts

A framework already has its storages; it gives them as drives rather than configuring them twice.
`Omnidrive\Flysystem\OperatorDrives` is `Drives` over a PSR-11 container of Flysystem operators:

- **Symfony**: [omnibridge/symfony](https://github.com/glitchr-studio/omnibridge-symfony) registers
  `Omnidrive\Drives` from the storages of league/flysystem-bundle's configuration (the services
  tagged `flysystem.storage`), by their names;
- **Laravel**: [omnibridge/laravel](https://github.com/glitchr-studio/omnibridge-laravel) registers
  it from the disks of `config/filesystems.php`.

[docs/hosts.md](docs/hosts.md) has both, and how omniscient/omnibase reads its storages through it.

## Documentation

- [Installation and a first script](docs/installation.md)
- [Drives: the contract, the options, writing a provider](docs/drives.md)
- [Hosts: Symfony, Laravel](docs/hosts.md)
- [The Docker harness: bare PHP, the servers it runs](docs/harness.md)

## Docker: every provider, and a server of each kind

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnidrive bare           # plain PHP: memory and local drives used whole, what PHP loaded
docker compose run --rm omnidrive bare --live    # and S3 (MinIO), SFTP (OpenSSH) and FTP (Pure-FTPd) used whole
docker compose run --rm omnidrive test           # every package's tests, the live ones against those servers
docker compose down
```

## Verified, and not

- Verified (2026-10-23, `docker compose run --rm omnidrive test` in `docker/`, PHP 8.4, Flysystem
  3.x as Composer resolves it): the contract's tests (`DriveContractTests`: write, replace, read,
  streams of 300 KB, missing files, shallow and deep listings, move, copy, directories, metadata,
  MD5 checksums, visibility, addresses, local paths) on the memory and local drives, and live on
  MinIO (S3, path style), OpenSSH's SFTP (atmoz/sftp, password) and Pure-FTPd (passive mode); a
  temporary S3 address signed and fetched, the file served, the bucket's plain address refused. In
  plain PHP (`BareTest`, `bin/bare --live`): the registry built by hand, every drive used whole,
  no class of a framework loaded - of Symfony only the HTTP client AsyncAws calls S3 through.
  The S3 requests offline (MockHttpClient): path style, prefix, ACL headers, signatures; PHPStan at
  its highest level on each package installed alone.
- Not verified: AWS S3 itself and the other S3 services (Scaleway, OVHcloud, R2) - MinIO only; an
  object's ACL (MinIO answers 501 to it; an AWS bucket made since April 2023 refuses ACLs unless
  its Object Ownership allows them: `OMNIDRIVE_S3_ACL=1` runs that test against one that does);
  SFTP with a private key or the SSH agent, and the server's key checked (password only); FTPS;
  vsftpd: its STAT answers nothing for a file below a subdirectory (seen with vsftpd 3.0.5), so
  Flysystem cannot read such a file's visibility, and a copy - which keeps its source's visibility
  by default - fails there: give such a drive `retain_visibility: false`; Windows FTP servers.

## Responsibility

`omniscient/omnidrive` and its `omnidrive/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the services or the software they reach (Amazon Web
Services, MinIO, any hosting company) nor by The PHP League, whose Flysystem they run on. Each
service keeps its own terms and its own prices - a bucket's requests and transfers are billed by its
provider. The MIT licence covers this code only.

Whoever installs and runs these packages is responsible for the data they store and for the access
they give to it - a public address or a temporary one serves the file to whoever holds it. The
authors publish them free of charge, make no commercial use of them, and accept no liability for
how they are used (see [LICENSE](LICENSE)).

License: MIT.
