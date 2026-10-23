<?php

namespace Omnidrive\Testing;

use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Omnidrive\Config;
use Omnidrive\Drive;
use Omnidrive\Exception\InvalidConfigException;
use Omnidrive\Flysystem\FlysystemDriveFactory;

/**
 * A drive in this process's memory, for an application's tests: what is written
 * is read back until the process ends, nothing touches a disk or a network.
 * Requires league/flysystem-memory (suggested).
 *
 *   new Registry([new InMemoryDriveFactory()], ['uploads' => ['factory' => 'memory', 'options' => ['public_url' => '/uploads']]]);
 */
final class InMemoryDriveFactory extends FlysystemDriveFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnidrive.factory_name' => 'memory',
            'omnidrive.factory_title' => 'In memory (tests)',
            'omnidrive.required_options' => [],
        ]);
    }

    protected function build(Config $c, string $name): Drive
    {
        if (!class_exists(InMemoryFilesystemAdapter::class)) {
            throw new InvalidConfigException('The "memory" drive needs league/flysystem-memory: composer require --dev league/flysystem-memory.');
        }

        return $this->drive($name, new InMemoryFilesystemAdapter(), $c);
    }
}
