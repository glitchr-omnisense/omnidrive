---
title: Usage policies
order: 55
---

# Usage policies

Each provider package declares what its service's terms allow in a `policy.json` (omnisense/contracts'
schema), named in its `composer.json` (`extra.omnisense.policy`) and exported with every other
omni family's by `vendor/bin/omnisense-policies`. Read on 2026-11-10 at the sources each file names.

| Provider | Third party | Limits declared | Rights, key |
|---|---|---|---|
| omnidrive/local | none: a directory of the site's machine | none (`[]`) | the site's own files; no key |
| omnidrive/sftp, ftp, webdav | none of the package's: a server the site names (its own, or a rented one whose operator's terms the site adds) | none (`[]`) | the server's account |
| omnidrive/s3 | AWS S3, or the S3-compatible store the endpoint names | unknown (`null`): AWS publishes a performance figure per prefix (3,500 writes, 5,500 reads a second), not a quota; in the notes | the AWS Customer Agreement and Service Terms (`contract`); access key |
| omnidrive/azure | Azure Blob Storage | unknown (`null`): scalability targets per blob (3,000 requests a second for a block blob), not quotas; in the notes | the Microsoft Customer Agreement (`contract`); the account's key or SAS |
| omnidrive/dropbox | Dropbox | unknown (`null`): Dropbox does not publish them, a 429 says Retry-After | the DBX Platform Developer Terms: user data as the users instruct, the branding guide; production approval past 500 users |
| omnidrive/gdrive | Google Drive | 1,000,000 quota units a minute per project, 325,000 per user, each operation's cost in units (5 to 200, a lower bound) | Google's API terms and User Data Policy: user data for user-facing features, never transferred |

No drive counts its calls against these limits: they are data for the site (a page of the terms it
runs under, the export), and a site that wants them counted wraps the calls in omnisense/contracts'
`PolicyGuard` with a limiter of its own.

## Writes are never doubled

omnidrive retries nothing itself. Where an engine's client does, the policy's notes say what a replay
could repeat, and none doubles a file:

| Provider | Retries | Writes |
|---|---|---|
| local, ftp, webdav | none | a write replaces the file; a delete, a move, a copy sent once |
| sftp | the connection only (`maxTries`), before any request | each write, delete, move or copy sent once |
| s3 | async-aws, after a 429, a 5xx or a transport error, any method | PutObject, DeleteObject, CopyObject of a key are idempotent; a replayed CreateMultipartUpload leaves an incomplete upload (give the bucket an AbortIncompleteMultipartUpload rule), never a second object |
| azure | azure-oss' Guzzle middleware, after a 408, 429, 5xx or a timeout, any method | Put Blob, Put Block and Put Block List idempotent; a replayed delete answers 404 |
| dropbox | none (no chunk retry, plain Guzzle) | uploads in `overwrite` mode, idempotent |
| gdrive | none (google/apiclient's `retries` unset) | a `files.create` is not idempotent (two files may share a name): sent once |

## Failures keep no credential

A `DriveException` made from an engine's failure (`DriveException::of()`) masks, in the reason it
quotes, an address's user part and every parameter named as a credential (a pre-signed URL's
`X-Amz-Signature`, a `password=`), through omnisense/contracts' `Redaction`; no trace of the chain
keeps its calls' arguments (the contents written, a client's keys). The engine's own exception stays
under it (`getPrevious()`).
