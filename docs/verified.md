---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-25 in the [harness](harness.md), the core and its eight providers from their
checkouts, PHP 8.4.26, PHPUnit 11.5.57, Flysystem 3.x as Composer resolves it: 136 tests, 545
assertions, green, 22 skipped (the Dropbox and Google Drive live tests without tokens, and the
visibility test of the drives that keep none).

- The contract (`DriveContractTests`, [Testing](testing.md#conformance-suite-each-test)) on the
  memory and local drives, and live against the harness's servers: MinIO (S3, path style, a
  prefix), OpenSSH's SFTP (atmoz/sftp, a password), Pure-FTPd (passive mode), Apache's mod_dav
  (WebDAV, basic authentication) and Azurite (Azure Blob Storage); against a stand-in of Dropbox's
  HTTP API written from its documentation (`Tests/Fixtures/FakeDropboxApi`), with the refresh
  token's renewal after a 401.
- The core: drives built once by name, an unknown drive or factory said by name, options read as
  environment variables give them (`RegistryTest`); a host's operators as drives, names given
  (`OperatorDrivesTest`); a local operator's root and files, another adapter with no local file
  and no address, the engine's failure kept behind the contract's exception, visibility options as
  the enumeration (`FlysystemDriveTest`); the memory drive's addresses (`InMemoryDriveTest`).
- In plain PHP (`BareTest`, `bin/bare --live`): the registry built by hand, every reachable drive
  used whole, no class of a framework loaded (of Symfony only the HTTP client AsyncAws calls S3
  through).
- Offline per provider: S3's requests (path style, prefix, ACL headers, signatures, AWS's
  virtual-host address); Azure's SAS address and its public ones; Google Drive's token exchange
  and a service account's signed assertion; the options each requires.
- Earlier, as the README states (2026-10-23 and 2026-10-24): a temporary S3 address and an Azurite
  SAS fetched, the plain addresses refused; Nextcloud's WebDAV (`--profile nextcloud`).

The capability table of the [index](index.md#providers) was probed with `capabilities()` on
2026-10-25; every example marked as run was run in the harness that day and its output pasted.

## What is not verified

- Dropbox and Google Drive themselves: no account at hand; their live tests run with an account's
  tokens (`OMNIDRIVE_DROPBOX_*`, `OMNIDRIVE_GDRIVE_*`).
- AWS S3 itself and the other S3 services (Scaleway, OVHcloud, R2): MinIO only; objects' ACLs
  (`OMNIDRIVE_S3_ACL=1` runs that test against a bucket that keeps them).
- Azure Blob Storage itself (Azurite only), an account with a hierarchical namespace.
- SFTP with a private key or the SSH agent, the server's key checked; FTPS; Windows FTP servers;
  vsftpd's STAT (see [Files](usage-files.md#moving-copying-directories)); ownCloud, nginx's WebDAV,
  digest and NTLM authentication.
- The hosts' pages ([Hosts](hosts.md)) were written from omnibridge's code and its `DrivesTest`s,
  not run in an application for this manual.
- The known defects found while writing this manual: a refused S3 request read as "not found", a
  refused Dropbox refresh token read as "not found" ([Errors](errors.md#how-a-failure-becomes-not-found)).

## Responsibility

`omnisense/omnidrive` and its `omnidrive/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the services or the software they reach (Amazon Web
Services, Microsoft, Dropbox, Google, Nextcloud, MinIO, any hosting company) nor by The PHP League,
whose Flysystem they run on. Each service keeps its own terms and its own prices - a bucket's
requests and transfers are billed by its provider. The MIT licence covers this code only. Whoever
installs and runs these packages is responsible for the data they store and for the access they
give to it - a public address or a temporary one serves the file to whoever holds it.
