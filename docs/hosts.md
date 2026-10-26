---
title: Hosts
order: 15
---

# Hosts

A framework already configures its storages. Omnidrive does not configure them a second time: the
host gives them as drives, and the application reads them through `Omnidrive\Drives`. One owner per
piece - the framework's configuration names the storages, the bridge turns them into drives,
omnidrive is how they are used.

`Omnidrive\Flysystem\OperatorDrives` is the piece both hosts use: `Drives` over a PSR-11 container
of Flysystem operators, each wrapped once in a `FlysystemDrive` - a local adapter's root read from
it (`localPath()`), its public and temporary addresses as its configuration and its adapter give
them.

## Symfony

[omnibridge/symfony](https://github.com/glitchr-omnisense/omnibridge-symfony) registers
`Omnidrive\Drives` (and `Omnidrive\Flysystem\OperatorDrives`) over a service locator of the services
tagged `flysystem.storage` by their `storage` name: the storages of league/flysystem-bundle's
configuration, by the names `config/packages/flysystem.yaml` gives them.

```yaml
# config/packages/flysystem.yaml - unchanged
flysystem:
    storages:
        local.uploads: { local: { directory: '%kernel.project_dir%/var/storage/uploads' } }
        s3.uploads: { asyncaws: { client: 'async_aws.s3.minio', bucket: 'site', prefix: 'uploads' } }
```

```php
public function __construct(private readonly Drives $drives) {}

$this->drives->get('s3.uploads')->write('avatars/1.png', $bytes);
```

What omnibridge/symfony registers (`config/services.php`), where `Omnidrive\Flysystem\OperatorDrives`
exists:

| Service | |
|---|---|
| `Omnidrive\Flysystem\OperatorDrives` | over `tagged_locator('flysystem.storage', 'storage')`: each service tagged `flysystem.storage`, by its tag's `storage` attribute |
| `Omnidrive\Drives` (public alias) | the same object |

Nothing to configure: no `omnidrive.yaml` exists. `names()` lists the locator's storages,
`has()` asks the locator, `get()` wraps the operator once in a `FlysystemDrive` named by the
storage. A storage that is not a Flysystem operator is an `InvalidConfigException`; an unknown
name too (`No "x" drive; the host has: local.uploads, s3.uploads.`).

What a drive over a host's operator can do is read from the operator
(`Flysystem\Introspection`): `local` when its adapter is league/flysystem-local's (its root is
read from the adapter, for `localPath()`), `publicUrls` when the operator has a public URL
generator, a `public_url` in its configuration or an adapter that generates them, `temporaryUrls`
when it has a temporary URL generator or an adapter that signs. What cannot be read is reported as
not there, never guessed. flysystem-bundle's own options (`public_url`, `visibility`, `directory_visibility`)
are the storage's; omnidrive's per-drive options (`permissions`, `retain_visibility`) do not apply
to a host's storages: set their equivalents in the bundle's configuration.

The application's code is the same as in plain PHP: type-hint `Drives` (or a `Drive` built with a
factory service: `(new Definition(Drive::class))->setFactory([new Reference(Drives::class), 'get'])`,
as omnibridge's omnimap wiring does for its cache). omnibridge/symfony's `Tests/DrivesTest.php`
checks it: the tagged storage `memory.uploads` is the drive of that name, and a file written
through the drive is in the host's own storage.

## Laravel

[omnibridge/laravel](https://github.com/glitchr-omnisense/omnibridge-laravel) binds `Omnidrive\Drives`
over the disks of `config/filesystems.php`: a drive is the disk of that name, or the disk
`omnibridge.storages` maps a name to (`'local.uploads' => 'public'`), each disk's own Flysystem
operator.

```php
// config/omnibridge.php - only the names; the disks stay in config/filesystems.php
return [
    'storages' => [
        'local.uploads' => 'public',      // omnibase's (or the application's) storage name => Laravel's disk
        's3.private' => 's3',
    ],
];
```

```php
use Omnidrive\Drives;

public function __construct(private readonly Drives $drives) {}

$this->drives->get('local.uploads')->write('avatars/1.png', $bytes);   // on the "public" disk
```

`Omnibridge\Laravel\OmnibridgeServiceProvider` binds, where `Omnidrive\Flysystem\OperatorDrives`
exists, the singleton `OperatorDrives` over `Omnibridge\Laravel\Media\DiskStorages` and aliases
`Omnidrive\Drives` to it. `DiskStorages` is a PSR-11 container of operators: `get($name)` is
`Storage::disk($disk)->getDriver()`, the disk being `omnibridge.storages[$name]` or the name
itself; `has($name)` is whether `config('filesystems.disks.<disk>')` exists; its names are those
`omnibridge.storages` maps, then every disk's. A local disk's drive is local (its root read from
the adapter), a bucket's is not. omnibridge/laravel's `Tests/DrivesTest.php` checks it: the names
in that order, a file written through `local.uploads` on the disk behind it, its local path, and
omnibase's `Base\Service\Flysystem` reading the same `Drives` object.

## Another host, or none

A host of one's own gives its storages the same way: `new OperatorDrives($container)` over any
PSR-11 container of Flysystem operators, with the list of names when the container cannot list
them (`new OperatorDrives($container, ['uploads', 'archive'])`; without a list, `names()` asks
`getProvidedServices()` when the container has it, else answers `[]`). With no framework at all,
the `Registry` is `Drives` built from factories ([Installation](installation.md)).

## omnisense/omnibase

omnibase's `Base\Service\Flysystem` (its storages `local.cache`, `local.uploads`,
`local.wysiwyg`...) reads them through `Omnidrive\Drives` since omnibase 4.0: whichever host it runs
on, its files are those drives. Its `FlysystemInterface` is kept, deprecated, for one release; new
code type-hints `Omnidrive\Drives`.
