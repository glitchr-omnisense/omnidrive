<?php

namespace Omnidrive\Flysystem;

use League\Flysystem\FilesystemOperator;
use Omnidrive\Drive;
use Omnidrive\Drives;
use Omnidrive\Exception\InvalidConfigException;
use Psr\Container\ContainerInterface;

/**
 * The drives a host already has, as Flysystem operators in a PSR-11 container
 * by name: Symfony's flysystem-bundle storages (a service locator of the
 * services tagged flysystem.storage), Laravel's disks. Each becomes a
 * FlysystemDrive the first time it is asked for.
 *
 * Its names are those given, else those a service locator provides
 * (getProvidedServices()); a plain container cannot list its own.
 */
final class OperatorDrives implements Drives
{
    /** @var array<string, Drive> */
    private array $drives = [];

    /** @param list<string>|null $names */
    public function __construct(private readonly ContainerInterface $operators, private readonly ?array $names = null)
    {
    }

    public function get(string $name): Drive
    {
        if (isset($this->drives[$name])) {
            return $this->drives[$name];
        }
        if (!$this->has($name)) {
            throw new InvalidConfigException(\sprintf('No "%s" drive; the host has: %s.', $name, implode(', ', $this->names()) ?: 'none it can list'));
        }
        $operator = $this->operators->get($name);
        if (!$operator instanceof FilesystemOperator) {
            throw new InvalidConfigException(\sprintf('The host\'s "%s" storage is a %s, not a Flysystem operator.', $name, get_debug_type($operator)));
        }

        return $this->drives[$name] = FlysystemDrive::of($name, $operator);
    }

    public function has(string $name): bool
    {
        return null !== $this->names ? \in_array($name, $this->names, true) : $this->operators->has($name);
    }

    public function names(): array
    {
        if (null !== $this->names) {
            return $this->names;
        }
        if (method_exists($this->operators, 'getProvidedServices')) {
            $provided = $this->operators->getProvidedServices();

            return \is_array($provided) ? array_map('strval', array_keys($provided)) : [];
        }

        return [];
    }
}
