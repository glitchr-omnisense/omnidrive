---
title: Configuration reference
order: 40
---

# Configuration reference

Every option below is read from the factories' code at this commit (`populate()` and what `build()`
reads). Options reach a factory through `create($options)` or a `Registry`'s `options`; under a
framework the storages are the framework's own and these options do not apply
([Hosts](hosts.md)). Values may be given as environment variables give them: `"true"`/`"1"` for a
boolean, `"0644"` for a mode, `"a,b"` for a list, `""` for "not set".

## The registry

```php
new Registry(iterable $factories, array $config);
// $config: ['uploads' => ['factory' => 'local', 'options' => ['root' => '...']], ...]
```

A later factory of the same name replaces an earlier one. The drive's name is the configuration's
key.

## Common options

Read by `FlysystemDriveFactory::drive()` for every provider (and the memory drive):

| Option | Type | Default | |
|---|---|---|---|
| `public_url` | string or list | none | the prefix files are served from (`/uploads`, `https://cdn.example.org`), or several (Flysystem shards the paths over them); set, `publicUrls` is on |
| `visibility` | `public` or `private` | the adapter's (Flysystem's: `public` for files on most adapters; S3: `private`) | what a file is written with when the write does not say |
| `directory_visibility` | `public` or `private` | `private` (local, SFTP, FTP); `public` (S3) | the same for a directory |
| `retain_visibility` | bool | `true` (Flysystem's) | whether a copy keeps its source's visibility; `false` for a server that cannot report it |
| `permissions` | map | `{file: {public: 0644, private: 0600}, dir: {public: 0755, private: 0700}}` | local, SFTP, FTP only: the Unix mode of each visibility; octal strings accepted, a mode left out keeps its default |

## local

`new LocalDriveFactory()`.

| Option | Type | Default | |
|---|---|---|---|
| `root` | string | required | the directory behind the drive's paths (made when the drive is built) |
| `lazy_root` | bool | `false` | the root made on the first write instead |
| `lock` | bool | `true` | an exclusive lock (`LOCK_EX`) while a file is written |
| `links` | `disallow` or `skip` | `disallow` | a symbolic link met in a listing is an error, or left out |

## s3

`new S3DriveFactory(?Symfony\Contracts\HttpClient\HttpClientInterface $http = null)` (AsyncAws's own
client when `null`).

| Option | Type | Default | |
|---|---|---|---|
| `bucket` | string | required | |
| `region` | string | `us-east-1` | |
| `endpoint` | string | AWS's | another S3 service (`http://minio:9000`, `https://s3.fr-par.scw.cloud`) |
| `path_style` | bool | `false` | the bucket in the path, not in the host name |
| `key`, `secret` | string | none: AsyncAws's own chain (environment, `~/.aws`, the instance's role) | |
| `session_token` | string | none | |
| `prefix` | string | `''` | every path under it |

## sftp

`new SftpDriveFactory()`.

| Option | Type | Default | |
|---|---|---|---|
| `host`, `username` | string | required | |
| `password` | string | none | |
| `private_key` | string | none | a path to the key, or the key itself |
| `passphrase` | string | none | the key's |
| `use_agent` | bool | `false` | the SSH agent's keys |
| `port` | int | `22` | |
| `root` | string | `/` | |
| `host_fingerprint` | string | none | the server's key checked (`SHA256:...` or the MD5 form phpseclib gives) |
| `timeout` | int | `10` | seconds |
| `max_tries` | int | `4` | connection attempts |

## ftp

`new FtpDriveFactory()`; requires `ext-ftp`.

| Option | Type | Default | |
|---|---|---|---|
| `host`, `username`, `password` | string | required | |
| `port` | int | `21` | |
| `root` | string | `/` | |
| `ssl` | bool | `false` | FTPS (explicit) |
| `passive` | bool | `true` | |
| `ignore_passive_address` | bool | the adapter's | ignore the address a server behind NAT gives for passive mode |
| `timeout` | int | `90` | seconds |
| `utf8` | bool | `false` | |
| `binary` | bool | `true` | binary transfers, else ASCII |
| `system_type` | `unix` or `windows` | asked of the server | |
| `recurse_manually` | bool | `true` | a deep listing walked directory by directory |
| `timestamps_on_unix_listings` | bool | `false` | |

## webdav

`new WebdavDriveFactory()`.

| Option | Type | Default | |
|---|---|---|---|
| `base_uri` | string | required | the collection behind the drive's paths, an absolute address (`InvalidConfigException` otherwise) |
| `username`, `password` | string | none | sent only with a `username` |
| `auth` | `basic`, `digest`, `ntlm`, `any` | `basic` | another value: `InvalidConfigException` |
| `prefix` | string | `''` | every path under it |
| `proxy` | string | none | |
| `manual_copy`, `manual_move` | bool | `false` | a server without COPY or MOVE: read and written again |

## azure

`new AzureDriveFactory()`.

| Option | Type | Default | |
|---|---|---|---|
| `container` | string | required | |
| `connection_string` | string | none | the portal's |
| `account_name`, `account_key` | string | none | instead of a connection string (one or the other is required: `InvalidConfigException`) |
| `endpoint` | string | `https://<account>.blob.core.windows.net` | with an account name |
| `prefix` | string | `''` | |
| `public_container` | bool | `false` | the container's blobs readable anonymously: their own address is public |

## dropbox

`new DropboxDriveFactory(?GuzzleHttp\ClientInterface $http = null)`.

| Option | Type | Default | |
|---|---|---|---|
| `refresh_token` | string | none | with `app_key` and `app_secret` (else `InvalidConfigException`) |
| `app_key`, `app_secret` | string | none | |
| `access_token` | string | none | instead of a refresh token; one or the other is required |
| `prefix` | string | `''` | |
| `team_member_id` | string | none | |
| `namespace_id` | string | none | |
| `chunk_size` | int | `Spatie\Dropbox\Client::MAX_CHUNK_SIZE` (150 MB) | bytes per request of a large upload |

## gdrive

`new GdriveDriveFactory(?GuzzleHttp\ClientInterface $http = null)`.

| Option | Type | Default | |
|---|---|---|---|
| `client_id`, `client_secret`, `refresh_token` | string | none | an OAuth client and an account's refresh token |
| `service_account` | string or array | none | instead: a service account's JSON key - its path, its JSON or the array; one or the other is required |
| `subject` | string | none | with a service account: the Workspace user it acts as |
| `root` | string | the Drive's root | a path of names (looked up when the drive is built), or an ID with `display_paths: false` |
| `shared_drive_id`, `shared_folder_id` | string | none | |
| `display_paths` | bool | `true` | paths are names; `false`: file IDs |
| `permanent_delete` | bool | `false` | `false`: a deleted file goes to the bin |

## memory

`new InMemoryDriveFactory()` (`Omnidrive\Testing`): no option of its own, the common ones above;
requires `league/flysystem-memory` (`InvalidConfigException` naming it otherwise).
