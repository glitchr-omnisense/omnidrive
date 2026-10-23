---
title: Hosts
order: 3
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

[omnibridge/symfony](https://github.com/glitchr-studio/omnibridge-symfony) registers
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

## Laravel

[omnibridge/laravel](https://github.com/glitchr-studio/omnibridge-laravel) binds `Omnidrive\Drives`
over the disks of `config/filesystems.php`: a drive is the disk of that name, or the disk
`omnibridge.storages` maps a name to (`'local.uploads' => 'public'`), each disk's own Flysystem
operator.

## omniscient/omnibase

omnibase's `Base\Service\Flysystem` (its storages `local.cache`, `local.uploads`,
`local.wysiwyg`...) reads them through `Omnidrive\Drives` since omnibase 4.0: whichever host it runs
on, its files are those drives. Its `FlysystemInterface` is kept, deprecated, for one release; new
code type-hints `Omnidrive\Drives`.
