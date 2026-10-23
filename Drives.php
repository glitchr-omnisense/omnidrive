<?php

namespace Omnidrive;

use Omnidrive\Exception\InvalidConfigException;

/**
 * The application's drives by name. Registry builds them from factories and
 * options; a host gives them from its own storages (Flysystem\OperatorDrives:
 * Symfony's flysystem-bundle storages, Laravel's disks).
 */
interface Drives
{
    /** @throws InvalidConfigException when no drive has that name */
    public function get(string $name): Drive;

    public function has(string $name): bool;

    /** @return list<string> the drives' names, as far as they are known */
    public function names(): array;
}
