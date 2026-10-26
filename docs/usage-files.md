---
title: Files
order: 31
---

# Files

Reading, writing, streaming, listing, moving and measuring files: the same calls on every drive.
The script below runs on a local drive; put another drive's options in the registry and it runs
unchanged (what a drive cannot do is in [Addresses and visibility](usage-addresses.md) and in each
provider's manual).

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnidrive\Exception\FileNotFoundException;
use Omnidrive\Local\LocalDriveFactory;
use Omnidrive\Registry;
use Omnidrive\Visibility;

$drives = new Registry([new LocalDriveFactory()], [
    'documents' => ['factory' => 'local', 'options' => ['root' => sys_get_temp_dir().'/documents', 'visibility' => 'private']],
]);
$documents = $drives->get('documents');

// Strings and streams; a write makes the directories it needs and replaces the file.
$documents->write('2026/invoices/F-0001.txt', "Invoice F-0001\nTotal: 120.00 EUR\n");
$documents->write('2026/invoices/F-0001.txt', "Invoice F-0001\nTotal: 125.00 EUR\n");
$in = fopen('php://temp', 'w+b');
fwrite($in, str_repeat('x', 200_000));
rewind($in);
$documents->writeStream('2026/scans/F-0001.bin', $in, ['visibility' => Visibility::Public]);
fclose($in);
$out = $documents->readStream('2026/scans/F-0001.bin');
echo strlen(stream_get_contents($out)), " bytes streamed back\n";
fclose($out);

// Listings, lazily: shallow, then deep.
foreach ($documents->list('2026') as $entry) {
    echo $entry->directory ? 'dir  ' : 'file ', $entry->path, "\n";
}
foreach ($documents->list('2026', deep: true) as $entry) {
    if ($entry->isFile()) {
        printf("%-28s %7d %s %s\n", $entry->path, $entry->size, $entry->visibility?->value, $entry->lastModified?->format('Y-m-d'));
    }
}

// Copy, move, directories.
$documents->copy('2026/invoices/F-0001.txt', '2026/archive/F-0001.txt');
$documents->move('2026/scans/F-0001.bin', '2026/archive/F-0001.bin');
$documents->createDirectory('2027');
var_dump($documents->exists('2026/scans/F-0001.bin'), $documents->directoryExists('2027'), $documents->exists('2027'));

// Metadata, one call each, or all at once.
$path = '2026/archive/F-0001.txt';
echo $documents->size($path), ' ', $documents->mimeType($path), ' ', $documents->visibility($path)->value, "\n";
echo $documents->checksum($path), ' ', $documents->checksum($path, 'sha1'), "\n";
echo json_encode($documents->metadata($path)->toArray()), "\n";
$documents->setVisibility($path, Visibility::Public);
echo decoct(fileperms($documents->localPath($path)) & 0777), "\n";

try {
    $documents->size('2026/missing.txt');
} catch (FileNotFoundException $e) {
    echo $e->operation, ' | ', $e->drive, ' | ', $e->path, "\n";
}

$documents->deleteDirectory('2026');
$documents->deleteDirectory('2027');
$documents->delete('2026/never-there.txt');   // no error
echo count([...$documents->list('')]), " entries left\n";
```

```
200000 bytes streamed back
dir  2026/scans
dir  2026/invoices
2026/scans/F-0001.bin         200000 public 2026-10-25
2026/invoices/F-0001.txt          33 private 2026-10-25
bool(false)
bool(true)
bool(false)
33 text/plain private
46770a01e06c03739d3e2322d1ad2f8c 309ff63a492625f6faafe49cef8c93221a5f70cf
{"path":"2026\/archive\/F-0001.txt","directory":false,"size":33,"lastModified":"2026-10-25T23:51:14+00:00","visibility":"private","mimeType":"text\/plain"}
644
metadata | documents | 2026/missing.txt
0 entries left
```

(run on 2026-10-25 in the [harness](harness.md), PHP 8.4)

## Writing

`write($path, $contents, $options)` and `writeStream($path, $stream, $options)` replace the file
when it is there and make the directories the path needs. The options:

| Option | |
|---|---|
| `visibility` | `Visibility::Public`, `Visibility::Private`, or `public` / `private`: the file's; the drive's `visibility` option when not given (the drive above writes `private` unless told) |
| `directory_visibility` | the same, for the directories the write makes |
| the engine's own | passed to Flysystem's `Config` as they are: `mimetype` (most adapters), S3's `ContentType`, `CacheControl`... |

A stream is read from its current position; rewind it first. Some engines close it once sent
(Azure's and Dropbox's HTTP clients): close it only when `is_resource()` is still true.

## Reading

`read()` gives the whole contents as a string, `readStream()` a resource the caller closes - for
large files. A file that is not there is a `FileNotFoundException`, whose `operation`, `drive` and
`path` say what was asked ([Errors](errors.md)). `exists()` asks for a file (a directory is not one),
`directoryExists()` for a directory.

## Listing

`list($path, $deep)` yields `Entry` objects lazily: each is fetched as the loop asks, so a large
bucket is not read in one go - and a failure may come in the middle of the loop, as a
`DriveException` of operation `list`. Entries come in the storage's order (sort them yourself),
paths relative to the drive's root. A shallow listing gives the directory's files and
directories; `deep: true` every file and directory below. What an entry carries depends on the
drive: a local directory's listing has sizes, dates and visibilities, a bucket's no directory
dates, most no media type.

## Moving, copying, directories

`copy($from, $to)` and `move($from, $to)` work within one drive; between two drives, read from one
and write to the other (`readStream()` then `writeStream()`). A copy keeps its source's visibility
unless the drive's `retain_visibility` is `false` (needed for a server that cannot report a
file's, as vsftpd for files below a subdirectory). `createDirectory()` makes a directory with its
parents; `deleteDirectory()` removes one with everything under it. Deleting a file or a directory
that is not there is no error.

## Metadata and checksums

`size()`, `mimeType()`, `lastModified()` (a `DateTimeImmutable` in UTC) and `visibility()` are one
call each; `metadata()` asks all four and returns an `Entry` (a visibility or a type the drive
cannot read is `null` there, where the single call would throw). `checksum($path, $algorithm)` is a
hex digest, MD5 by default; the engine reads the file and hashes it with any algorithm PHP's
`hash()` knows, and the adapters give a checksum of their own under a name of theirs: `etag` on S3 (the object's
ETag), `dropbox` on Dropbox (its `content_hash`).

## Paths

A path is relative to the drive's root and `/`-separated; a leading `/` is the same path.
Flysystem normalises it (whitespace, `./`) and refuses one that climbs out of the root (`..`), as a
`DriveException` whose previous exception is Flysystem's `PathTraversalDetected`.
