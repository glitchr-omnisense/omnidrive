<?php

namespace Omnidrive\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/** A host's service locator of storages, as Symfony's is: it lists what it provides. */
final class Operators implements ContainerInterface
{
    /** @param array<string, object> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): mixed
    {
        return $this->services[$id] ?? throw new class($id) extends \InvalidArgumentException implements NotFoundExceptionInterface {
        };
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /** @return array<string, string> */
    public function getProvidedServices(): array
    {
        return array_map(static fn (object $service) => $service::class, $this->services);
    }
}
