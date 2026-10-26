---
title: Errors
order: 50
---

# Errors

Every exception of the family implements `Omnidrive\Exception\OmnidriveException` (an interface
extending `\Throwable`):

| Exception | Extends | Thrown when |
|---|---|---|
| `DriveException` | `\RuntimeException` | an operation failed: the engine's `FilesystemException` (a server refused, did not answer, a permission, a path climbing out of the root), a `writeStream()` given something that is not a stream |
| `FileNotFoundException` | `DriveException` | the file asked for is not there (read, stream, size, type, date, checksum, visibility, move, copy) |
| `NotSupportedException` | `\LogicException` | the drive does not do that: a public address without one, a temporary address from a drive that signs none, a checksum from an engine that computes none |
| `InvalidConfigException` | `\InvalidArgumentException` | an unknown drive or factory, a required option missing, an option's value refused (WebDAV's `auth`, Azure's credentials, Dropbox's or Google's token without its client, a service account's file unreadable) |

`DriveException` keeps what was asked:

| Property | |
|---|---|
| `operation` | one of `DriveException::OPERATIONS`' keys: `read`, `write`, `delete`, `move`, `copy`, `list`, `exists`, `directory`, `metadata`, `checksum`, `visibility`, `public_url`, `temporary_url` |
| `drive` | the drive's name |
| `path` | the path asked (the source, for a move or a copy) |
| `getPrevious()` | the engine's exception (Flysystem's `UnableToReadFile`...), itself holding the server's |

Its message is `The "<drive>" drive could not <operation> "<path>": <reason>`, the reason every
distinct message of the chain joined with ` - `: what the engine was told by its server.

```php
use Omnidrive\Exception\DriveException;
use Omnidrive\Exception\FileNotFoundException;

try {
    $pdf = $drives->get('invoices')->read("2026/$number.pdf");
} catch (FileNotFoundException) {
    throw new NotFoundHttpException();                     // the application's 404
} catch (DriveException $e) {
    $logger->error($e->getMessage(), ['operation' => $e->operation, 'drive' => $e->drive]);
    throw $e;                                              // the storage is down: a 5xx
}
```

## How a failure becomes "not found"

A failed operation on a file is asked again with `exists()`: when the file is not there, the
failure is a `FileNotFoundException`, else a `DriveException`. Whatever makes `exists()` answer
`false` therefore makes the failure "not found":

> [!WARNING]
> Known defect: on S3, a request refused with HTTP 403 (wrong keys, no permission) makes
> `exists()` answer `false` and `read()` throw `FileNotFoundException`, where a refused request
> is expected to be a `DriveException` and `exists()` to fail (seen in the harness with a
> recorded 403: `The "s3" drive could not read "a.txt": ... HTTP 403 returned`, a
> `FileNotFoundException`). AWS itself answers 403 for a missing object to a caller without
> `s3:ListBucket`, so the two cannot always be told apart on S3.

> [!WARNING]
> Known defect: on Dropbox, a refresh token Dropbox refuses makes every read a
> `FileNotFoundException` with no reason and `exists()` answer `false` (spatie's client also
> emits PHP warnings, its error body having no `error_summary`), where a refused credential is
> expected to be a `DriveException` naming it.

Operations that need no file (a write, a listing, a directory made) are `DriveException`s.
`exists()` and `directoryExists()` throw a `DriveException` of operation `exists` only when the
engine itself fails to answer.

## What is not an exception

- Deleting a file or a directory that is not there.
- `metadata()` of a file whose visibility or media type the drive cannot read: `null` in the
  `Entry`.
- `localPath()` on a remote drive: `null`.

## Outside the family

PHP's `ValueError` from `Visibility::of()` with a name other than `public` or `private` (a write's
`visibility` option of another value). Flysystem's exceptions never reach the application through
`Drive`, but a host that uses `FlysystemDrive::operator()` gets Flysystem's own.
