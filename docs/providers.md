---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a factory: it builds the league's adapter from its options, and
`Flysystem\FlysystemDriveFactory::drive()` the drive around it. The WebDAV factory, simplified
(the real one also splits `base_uri` into the client's origin and the adapter's prefix, and maps
`auth`):

```php
final class WebdavDriveFactory extends FlysystemDriveFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnidrive.factory_name' => 'webdav',
            'omnidrive.factory_title' => 'A WebDAV server',
            'omnidrive.required_options' => ['base_uri'],
        ]);
    }

    protected function build(Config $c, string $name): Drive
    {
        return $this->drive($name, new WebDAVAdapter(new Client(['baseUri' => $c->string('base_uri')])), $c);
    }
}
```

Its tests use `Omnidrive\Testing\DriveContractTests` and give the drive; each test works under a
directory of its own, removed afterwards:

```php
final class WebdavLiveTest extends TestCase
{
    use DriveContractTests;

    protected function drive(): Drive
    {
        return (new WebdavDriveFactory())->create(['base_uri' => getenv('WEBDAV_URI')]);
    }
}
```

## A whole provider

A factory of one's own and its contract test, both in one file here; this ran green in the
[harness](harness.md) on 2026-10-25 (the suite's 9 tests, [Testing](testing.md#a-drive-in-memory)):

```php
<?php

use League\Flysystem\Local\LocalFilesystemAdapter;
use Omnidrive\Config;
use Omnidrive\Drive;
use Omnidrive\Flysystem\FlysystemDriveFactory;
use Omnidrive\Testing\DriveContractTests;
use PHPUnit\Framework\TestCase;

/** A provider of one's own: a directory under the system's temporary one, named by an option. */
final class ScratchDriveFactory extends FlysystemDriveFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnidrive.factory_name' => 'scratch',
            'omnidrive.factory_title' => 'A scratch directory',
            'omnidrive.required_options' => ['folder'],
        ]);
    }

    protected function build(Config $c, string $name): Drive
    {
        $root = sys_get_temp_dir().'/'.basename((string) $c->string('folder'));

        return $this->drive($name, new LocalFilesystemAdapter($root, $this->unixVisibility($c)), $c, $root);
    }
}

final class ScratchContractTest extends TestCase
{
    use DriveContractTests;

    protected function drive(): Drive
    {
        return (new ScratchDriveFactory())->create(['folder' => 'scratch-contract', 'public_url' => '/scratch']);
    }
}
```

## What a provider decides

- **Its name and its required options** (`populate()`): the factory name is what a configuration's
  `factory` names; a missing required option is the `InvalidConfigException` `create()` raises.
  Credentials that come in alternatives (a refresh token or an access token) are checked in
  `build()`, with an `InvalidConfigException` that names both.
- **Nothing connects in `build()`.** The adapter is built; the server is reached on the first
  operation. HTTP clients come through the factory's constructor (S3: Symfony's contracts; Dropbox,
  Google Drive: Guzzle), never created inside when one is given.
- **Its capabilities**, through `drive()`: pass the local root when its paths are files of this
  machine; pass `$adapterPublicUrls: false` when the adapter's own public address would be a
  credential (Azure's SAS of a thousand years); temporary addresses come from the adapter
  (`TemporaryUrlGenerator`).
- **Where the adapter parts from Flysystem's contract**, a subclass of it fixes that, as
  `omnidrive/dropbox`'s `DropboxAdapter` does (a missing file deleted is no error, a visibility not
  kept is reported as such, a checksum is the file's own).
- **Unix modes**: `unixVisibility($c)` gives the adapter the `permissions` option.

## Checklist

- [ ] `Tests/<Name>DriveTest.php`: what is particular (options mapped onto the adapter, offline,
  with a mock client when the service is HTTP);
- [ ] `Tests/<Name>LiveTest.php` using `DriveContractTests` against a server (the harness's, or one
  named by `OMNIDRIVE_<NAME>_*` variables; skipped without them), `keepsVisibility()` when the
  service keeps none;
- [ ] the provider listed in the core's `docker/harness/plugins.php` (and a server in
  `docker/compose.yml`, with its variables in `.env.dist`, when one can run in a container);
- [ ] `docs/index.md`: the service, credentials, every option, capabilities, behaviour, errors,
  verified and not;
- [ ] the core's README, `suggest` and the providers table of this manual.
