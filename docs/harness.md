---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` runs this package with every `omnidrive/*` provider installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNIDRIVE_PLUGINS=../..` is in `docker/.env` - and a
server of each kind for the drives to reach: MinIO (S3), OpenSSH (SFTP, atmoz/sftp), Pure-FTPd
(FTP, built from `docker/ftp`), Apache's mod_dav (WebDAV, bytemark/webdav) and Azurite (Azure Blob
Storage). Their accounts are throwaway ones, in `compose.yml` (Azurite's is its documented
development account). A Nextcloud is there too, not started by default (`--profile nextcloud`).
Dropbox and Google Drive have no stand-in server: their live tests and `bare --live` reach them
only with an account's tokens in `.env`.

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnidrive bare
docker compose run --rm omnidrive bare --live
docker compose run --rm omnidrive test
docker compose down                       # the servers stopped, their network removed
```

| Command | |
|---|---|
| `bare` | plain PHP: the registry built by hand, a drive per factory installed; the memory and local drives used whole, the others built but not reached; what PHP loaded (exit 1 if a framework's class is among it) |
| `bare --live` | the same, and the S3, SFTP, FTP, WebDAV and Azure drives used whole against the harness's servers - or yours, named in `.env`; Dropbox and Google Drive with tokens in `.env` |
| `bare --json` | the whole report: every step's answer, every class and file loaded |
| `test` | every package's tests; the live ones (`*LiveTest`, `BareTest`'s remote case) against the servers |
| `composer ...`, `php ...`, `sh` | as they are, in the harness |

"Used whole" is: a file written and read back, its size and type, a 1 MB stream written and read,
a deep listing, a copy and a move, its metadata and MD5 checksum, its public address, a temporary
one signed and fetched (S3), its local path opened (local), everything deleted. An address is
printed without its query string: a signed one's key and signature stay out of the output.

```
$ docker compose run --rm omnidrive bare --live
s3      s3 (Omnidrive\Flysystem\FlysystemDrive): local NO, public addresses yes, temporary addresses yes
  write          yes
  size_type      [46,"text/plain"]
  stream         yes
  list           ["big.bin","hello.txt"]
  copy_move      yes
  checksum       yes
  public_url     http://minio:9000/omnidrive/bare/bare-3376bb/hello.txt
  temporary_url  {"url":"http://minio:9000/omnidrive/bare/bare-3376bb/hello.txt?...","signed":true,"served":true}
  delete         yes
...
Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (...): none
```

(an excerpt; the run of 2026-10-25 gave the same for S3 and used the memory, local, SFTP, FTP,
WebDAV and Azure drives whole too, every step `yes`; Dropbox and Google Drive were built, not
reached: no tokens)

## The services

| Service | Image | Account | The drive's variables |
|---|---|---|---|
| `omnidrive` | `omniscient/omnidrive-harness` (`docker/Dockerfile`: `php:8.4-cli`, the `ftp` extension, Composer 2) | - | mounts the core at `/omnidrive/core`, the providers' checkouts read-only at `/omnidrive/plugins`, the volume `harness` at `/harness` |
| `minio` | `minio/minio:latest` | `omnidrive` / `omnidrive-harness` (root) | `OMNIDRIVE_S3_ENDPOINT` (`http://minio:9000`), `_REGION` (`us-east-1`), `_BUCKET` (`omnidrive`), `_KEY`, `_SECRET`, `_PATH_STYLE` (`1`) |
| `sftp` | `atmoz/sftp:alpine` | `omnidrive` / `omnidrive-harness`, chrooted, writing under `/upload` | `OMNIDRIVE_SFTP_HOST`, `_PORT`, `_USERNAME`, `_PASSWORD`, `_ROOT` (`/upload`) |
| `ftp` | `omniscient/omnidrive-harness-ftp` (`docker/ftp/Dockerfile`: Pure-FTPd, passive ports 30000-30009) | `omnidrive` / `omnidrive-harness`, chrooted | `OMNIDRIVE_FTP_HOST`, `_PORT`, `_USERNAME`, `_PASSWORD`, `_ROOT` |
| `webdav` | `bytemark/webdav:latest` (Apache's mod_dav, basic authentication) | `omnidrive` / `omnidrive-harness` | `OMNIDRIVE_WEBDAV_URI` (`http://webdav/`), `_USERNAME`, `_PASSWORD` |
| `azurite` | `mcr.microsoft.com/azure-storage/azurite:latest` (blob service only) | Azurite's documented development account `devstoreaccount1` | `OMNIDRIVE_AZURE_CONNECTION_STRING`, `_CONTAINER` (`omnidrive`) |
| `nextcloud` | `nextcloud:apache` (SQLite), profile `nextcloud`, not started by default | `omnidrive` / `omnidrive-harness` (admin) | `OMNIDRIVE_WEBDAV_URI=http://nextcloud/remote.php/dav/files/omnidrive/` |

`docker/.env.dist` lists every variable: point them at servers of your own (a Scaleway bucket, a
real SFTP host) and `bare --live` and the live tests use those instead; each test works under a
directory of its own and removes it. Dropbox (`OMNIDRIVE_DROPBOX_REFRESH_TOKEN`, `_APP_KEY`,
`_APP_SECRET` or `_ACCESS_TOKEN`, `_PREFIX`) and Google Drive (`OMNIDRIVE_GDRIVE_CLIENT_ID`,
`_CLIENT_SECRET`, `_REFRESH_TOKEN` or `_SERVICE_ACCOUNT`, `_SHARED_DRIVE_ID`, `_ROOT`) are reached
only with an account's tokens there.

## How it starts

`docker/entrypoint.sh` runs `docker/harness/setup.php` (writes `/harness/composer.json` and
`phpunit.xml`: the core and each provider of `docker/harness/plugins.php` as path repositories
from their checkouts, or git clones of `https://github.com/glitchr-studio/omnidrive-<slug>.git`;
`league/flysystem-memory`, PHPUnit 11 and symfony/http-client; one test suite per package),
installs or updates when `composer.json` changed, then `docker/harness/wait.php` waits up to 30
seconds for each server the variables name to accept connections (a server that never answers is
reported, not waited for again). `bare` builds the drives of `docker/harness/config/drives.php`,
one per factory installed: those with a server or an account named are `live`, the others are
built with placeholder options on `.invalid` hosts and never reached.

## Running a script of one's own

The examples of this manual ran this way, the script in a directory mounted at `/scratch` beside a
`vendor` link to the harness's (`ln -s /harness/vendor scratch/vendor`), so that
`require __DIR__.'/vendor/autoload.php'` works as in an application:

```sh
cd docker
docker compose run --rm -v "$PWD/../scratch:/scratch" omnidrive php /scratch/example.php
docker compose run --rm -v "$PWD/../scratch:/scratch" omnidrive test --testdox /scratch/tests
docker compose down                       # the servers that run started
```

`docker compose run` starts the servers (`depends_on`) and leaves them running after the
command: `docker compose down` stops and removes them and their network; the `harness` volume
(the installation, Composer's cache) stays until `docker compose down -v`. The service images run
as `linux/amd64` on an ARM machine (Docker warns, emulation runs them).

Run on 2026-10-25: `test` 136 tests, 545 assertions, green, 22 skipped - the Dropbox and Google
Drive live tests (no tokens) and the visibility test of the four drives that keep none (S3 without
`OMNIDRIVE_S3_ACL`, WebDAV, Azure, Dropbox's stand-in).
