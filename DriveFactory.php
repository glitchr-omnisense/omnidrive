<?php

namespace Omnidrive;

/**
 * The family's way, at its simplest: a drive's factory fills a Config - its name
 * ("omnidrive.factory_name"), its title ("omnidrive.factory_title"), the options
 * it needs ("omnidrive.required_options") and the defaults of the others - then
 * builds the drive from it.
 *
 * Nothing here connects: a drive reaches its server on its first operation.
 */
abstract class DriveFactory implements DriveFactoryInterface
{
    public function getName(): string
    {
        return (string) $this->createConfig()->string('omnidrive.factory_name');
    }

    public function create(array $options = [], ?string $name = null): Drive
    {
        $config = $this->createConfig($options);
        $config->validateNotEmpty($config->list('omnidrive.required_options'));

        return $this->build($config, $name ?? $this->getName());
    }

    /** @param array<string, mixed> $options */
    public function createConfig(array $options = []): Config
    {
        $config = new Config($options);
        $this->populate($config);

        return $config;
    }

    /** The factory's name and title, its required options, the defaults of the others. */
    abstract protected function populate(Config $c): void;

    /** The drive, from a Config that holds everything it needs. */
    abstract protected function build(Config $c, string $name): Drive;
}
