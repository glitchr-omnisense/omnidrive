---
title: Installation and a first script
order: 1
---

# Installation and a first script

```sh
composer require omniscient/omnidrive omnidrive/local
```

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

In a framework, the drives are those the framework already has: [hosts](hosts.md).
