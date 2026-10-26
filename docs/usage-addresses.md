---
title: Addresses and visibility
order: 32
---

# Addresses and visibility

Besides reading and writing, a drive may give three things, each said beforehand by
`capabilities()`: a public address (`publicUrls`), a signed address that expires
(`temporaryUrls`), and the file of this machine behind a path (`local`). The script below runs in
the [harness](harness.md) against its MinIO (S3) and OpenSSH (SFTP) servers.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use AsyncAws\SimpleS3\SimpleS3Client;
use Omnidrive\Exception\NotSupportedException;
use Omnidrive\Local\LocalDriveFactory;
use Omnidrive\Registry;
use Omnidrive\S3\S3DriveFactory;
use Omnidrive\Sftp\SftpDriveFactory;

$minio = ['region' => 'us-east-1', 'endpoint' => 'http://minio:9000', 'path_style' => true, 'key' => 'omnidrive', 'secret' => 'omnidrive-harness'];
$drives = new Registry([new LocalDriveFactory(), new S3DriveFactory(), new SftpDriveFactory()], [
    'public' => ['factory' => 'local', 'options' => ['root' => sys_get_temp_dir().'/public/media', 'public_url' => 'https://cdn.example.org/media']],
    'private' => ['factory' => 's3', 'options' => ['bucket' => 'invoices', 'prefix' => 'site'] + $minio],
    'backup' => ['factory' => 'sftp', 'options' => ['host' => 'sftp', 'username' => 'omnidrive', 'password' => 'omnidrive-harness', 'root' => '/upload']],
]);
// The harness's MinIO starts empty.
try {
    (new SimpleS3Client(['region' => 'us-east-1', 'endpoint' => 'http://minio:9000', 'pathStyleEndpoint' => 'true', 'accessKeyId' => 'omnidrive', 'accessKeySecret' => 'omnidrive-harness']))->createBucket(['Bucket' => 'invoices'])->resolve();
} catch (Throwable) {
}

foreach ($drives->names() as $name) {
    echo $name, ' ', json_encode($drives->get($name)->capabilities()->toArray()), "\n";
}

$drives->get('public')->write('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
echo $drives->get('public')->publicUrl('logo.svg'), "\n";
echo $drives->get('public')->localPath('logo.svg'), "\n";

$invoices = $drives->get('private');
$invoices->write('2026/F-0001.pdf', '%PDF-1.4 ...');
echo $invoices->publicUrl('2026/F-0001.pdf'), "\n";
$link = $invoices->temporaryUrl('2026/F-0001.pdf', new DateTimeImmutable('+10 minutes'));
echo strtok($link, '?'), "?...\n";
echo file_get_contents($link), "\n";                                  // served while the signature lasts
echo @file_get_contents(strtok($link, '?')) === false ? "the plain address is refused\n" : "served\n";
var_dump($invoices->localPath('2026/F-0001.pdf'));

try {
    $drives->get('backup')->temporaryUrl('dump.sql', new DateTimeImmutable('+10 minutes'));
} catch (NotSupportedException $e) {
    echo $e->getMessage(), "\n";
}
$invoices->deleteDirectory('2026');
$drives->get('public')->delete('logo.svg');
```

```
public {"local":true,"publicUrls":true,"temporaryUrls":false}
private {"local":false,"publicUrls":true,"temporaryUrls":true}
backup {"local":false,"publicUrls":false,"temporaryUrls":false}
https://cdn.example.org/media/logo.svg
/tmp/public/media/logo.svg
http://minio:9000/invoices/site/2026/F-0001.pdf
http://minio:9000/invoices/site/2026/F-0001.pdf?...
%PDF-1.4 ...
the plain address is refused
NULL
The "backup" drive does not give temporary addresses: its adapter signs none.
```

(run on 2026-10-25 in the harness, the S3 drive on MinIO with path-style addresses, the SFTP one on
atmoz/sftp)

## Public addresses

`publicUrl($path)` is where anyone fetches the file: the drive's `public_url` option followed by
the path, or the address the adapter gives (an S3 bucket's own, a WebDAV file's), whether the file
is there or not and whether it is public or not - the address is not a permission. With a list of
prefixes (`public_url: [https://a.cdn.example.org, https://b.cdn.example.org]`) Flysystem shards
the paths over them, a path always on the same one. A drive with neither throws
`NotSupportedException` (`The "backup" drive does not give public addresses: no "public_url"
configured and its adapter gives none.`).

The Azure drive is the exception to "the adapter's own": for a private container that address is
a SAS valid a thousand years, so the drive gives a public address only with `public_container`
(the container is readable anonymously) or `public_url`.

## Temporary addresses

`temporaryUrl($path, $expiresAt)` signs an address that serves the file until `$expiresAt`, to
whoever holds it: S3's presigned GET (the lifetime asked, as the service accepts it), Azure's read-only SAS (signed with the account key), Dropbox's temporary
link (four hours at most: a later `$expiresAt` is a `DriveException`). The other drives throw
`NotSupportedException`. Keep a temporary address out of logs: it is a credential until it
expires.

## Local paths

`localPath($path)` is the file's path on this machine for a local drive (whether the file is there
or not), `null` for every other: hand it to a library that only opens files (an image processor,
`ZipArchive`), and fall back to `readStream()` and a temporary file when it is `null`.

## Visibility per drive

A visibility is `Visibility::Public` or `Visibility::Private`. What it means depends on the drive:

| Drive | Public | Private | Kept per file |
|---|---|---|---|
| local, SFTP, FTP | the file's Unix mode for `public` (`0644`, directories `0755`) | `0600`, `0700` | yes; the modes are the `permissions` option |
| S3 | the object's ACL `public-read` | `private` | where the bucket keeps ACLs (a bucket made on AWS since April 2023 refuses them unless its Object Ownership allows them; MinIO answers 501) |
| Google Drive | shared with anyone who has the link, as a reader | not shared | yes |
| WebDAV, Azure, Dropbox | - | - | no: a write's visibility is accepted and ignored, `visibility()` and `setVisibility()` are a `DriveException` |
| memory | as given | as given | yes |

The drive's `visibility` and `directory_visibility` options are what a write uses when it does
not say; `setVisibility()` changes one file's. `metadata()` gives `visibility: null` where the
drive keeps none.
