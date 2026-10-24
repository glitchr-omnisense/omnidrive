---
title: The Docker harness
order: 4
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
