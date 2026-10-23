<?php

namespace Omnidrive;

use Omnidrive\Exception\InvalidConfigException;

/**
 * The application's drives by name, each built once from its factory and
 * options:
 *
 *   new Registry([new LocalDriveFactory(), new S3DriveFactory()], [
 *       'uploads' => ['factory' => 'local', 'options' => ['root' => __DIR__.'/var/uploads', 'public_url' => '/uploads']],
 *       'archive' => ['factory' => 's3', 'options' => ['bucket' => 'archive', 'region' => 'eu-west-3', 'key' => '...', 'secret' => '...']],
 *   ]);
 *
 *   $registry->get('uploads')->write('avatars/1.png', $bytes);
 */
final class Registry implements Drives
{
    /** @var array<string, DriveFactoryInterface> */
    private array $factories = [];

    /** @var array<string, Drive> */
    private array $drives = [];

    /**
     * @param iterable<DriveFactoryInterface>                                         $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): Drive
    {
        return $this->drives[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? [], $name);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return array<string, mixed> the options a drive is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /**
     * A drive built afresh, its configured options with $overrides over them. Not
     * kept: get() still gives the configured one.
     *
     * @param array<string, mixed> $overrides
     */
    public function create(string $name, array $overrides = []): Drive
    {
        return $this->factory($name)->create(array_replace($this->config[$name]['options'] ?? [], $overrides), $name);
    }

    /** @return array<string, Drive> */
    public function all(): array
    {
        $all = [];
        foreach ($this->names() as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /** @return list<string> the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    private function factory(string $name): DriveFactoryInterface
    {
        $drive = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" drive; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$drive['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" drive; installed: %s.', $drive['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
