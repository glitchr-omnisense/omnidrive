---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omnidrive omnidrive/local
```

## Requirements

- PHP 8.2 or later.
- `omnisense/omnidrive` requires `league/flysystem` (^3.0) and `psr/container` (^1.1 or ^2.0),
  nothing else: no framework, no HTTP client. Its test drive needs `league/flysystem-memory`
  (suggested), its conformance suite `phpunit/phpunit`.
- Each provider requires its Flysystem adapter and what that adapter needs: `omnidrive/ftp` the
  `ftp` extension, `omnidrive/s3` AsyncAws (`async-aws/simple-s3`, which calls S3 through
  `symfony/http-client`), `omnidrive/webdav` sabre/dav (cURL), `omnidrive/dropbox` Guzzle,
  `omnidrive/gdrive` Google's `google/apiclient` and Guzzle. Their manuals list them.

## A first script

A whole script, in plain PHP - no framework, no container:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnidrive\Exception\FileNotFoundException;
use Omnidrive\Local\LocalDriveFactory;
use Omnidrive\Registry;
use Omnidrive\Visibility;

$drives = new Registry([new LocalDriveFactory()], [
    'uploads' => ['factory' => 'local', 'options' => ['root' => __DIR__.'/var/uploads', 'public_url' => '/uploads']],
]);
$uploads = $drives->get('uploads');

$uploads->write('notes/hello.txt', 'Hello.', ['visibility' => Visibility::Private]);
echo $uploads->read('notes/hello.txt'), "\n";                       // Hello.
echo $uploads->mimeType('notes/hello.txt'), "\n";                   // text/plain
echo $uploads->publicUrl('notes/hello.txt'), "\n";                  // /uploads/notes/hello.txt
echo $uploads->localPath('notes/hello.txt'), "\n";                  // .../var/uploads/notes/hello.txt

foreach ($uploads->list('notes') as $entry) {
    echo $entry->path, ' ', $entry->size, " bytes\n";               // notes/hello.txt 6 bytes
}

try {
    $uploads->read('notes/missing.txt');
} catch (FileNotFoundException $e) {
    echo $e->getMessage(), "\n";                                    // The "uploads" drive could not read "notes/missing.txt": ...
}

$uploads->deleteDirectory('notes');
```

```
$ php first.php
Hello.
text/plain
/uploads/notes/hello.txt
/scratch/var/uploads/notes/hello.txt
notes/hello.txt 6 bytes
The "uploads" drive could not read "notes/missing.txt": Unable to read file from location: notes/missing.txt. file_get_contents(/scratch/var/uploads/notes/missing.txt): Failed to open stream: No such file or directory
```

(run on 2026-10-25 in the [harness](harness.md), PHP 8.4, the script in `/scratch`)

The pieces: a **factory** per provider package (`LocalDriveFactory`), the **registry** built from
the factories and each drive's options by name, and the **drive** it gives. The message of a
`DriveException` names the drive, the operation and the path, then what the engine was told
(here PHP's own warning). Nothing is created before the first write: the local drive makes its
root when it is built (unless `lazy_root`), the remote ones connect on their first operation.

## Other storages

Another storage is another provider and another entry; the code that reads and writes does not
change:

```sh
composer require omnidrive/s3 omnidrive/sftp omnidrive/ftp
```

```php
$drives = new Registry([new LocalDriveFactory(), new S3DriveFactory(), new SftpDriveFactory(), new FtpDriveFactory()], [
    'uploads' => ['factory' => 's3', 'options' => ['bucket' => 'uploads', 'endpoint' => 'http://minio:9000', 'path_style' => true, 'key' => getenv('S3_KEY'), 'secret' => getenv('S3_SECRET')]],
    'backup' => ['factory' => 'sftp', 'options' => ['host' => 'backup.example.org', 'username' => 'site', 'private_key' => '/run/secrets/id_ed25519', 'root' => '/srv/backup']],
    'legacy' => ['factory' => 'ftp', 'options' => ['host' => 'ftp.example.org', 'username' => 'site', 'password' => getenv('FTP_PASSWORD'), 'root' => '/www']],
]);
```

A drive connects on its first operation, not when it is built. Options come as configuration and
environment variables give them: `"true"`, `"0644"`, `"a,b"` are read as a boolean, a mode, a list.

Each drive's options are in [Configuration](configuration.md) and in its provider's manual.

## In a framework

In a framework, the drives are those the framework already has: [hosts](hosts.md). omnibridge/symfony
gives the storages of league/flysystem-bundle as `Omnidrive\Drives`, omnibridge/laravel the disks of
`config/filesystems.php`; neither needs an omnidrive configuration of its own.
