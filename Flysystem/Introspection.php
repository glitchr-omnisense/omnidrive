<?php

namespace Omnidrive\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Omnidrive\Model\Capabilities;

/**
 * What an operator a host gives can do, read from it: Flysystem keeps its adapter,
 * its configuration and its URL generators private, and says nothing of a local
 * root. The one place that looks inside Flysystem's objects; what it cannot read
 * it reports as not there (no public address, no local root), never guessed.
 *
 * @internal
 */
final class Introspection
{
    /** @var class-string league/flysystem-local's adapter, which the core does not require */
    private const LOCAL_ADAPTER = 'League\Flysystem\Local\LocalFilesystemAdapter';

    public static function adapter(FilesystemOperator $operator): ?FilesystemAdapter
    {
        $adapter = self::property($operator, Filesystem::class, 'adapter');

        return $adapter instanceof FilesystemAdapter ? $adapter : null;
    }

    /** The directory behind a local adapter's paths; null for any other adapter. */
    public static function localRoot(FilesystemOperator $operator): ?string
    {
        $adapter = self::adapter($operator);
        if (null === $adapter || !is_a($adapter, self::LOCAL_ADAPTER)) {
            return null;
        }
        $prefixer = self::property($adapter, self::LOCAL_ADAPTER, 'prefixer');

        return $prefixer instanceof PathPrefixer ? rtrim($prefixer->prefixPath(''), '/') : null;
    }

    public static function capabilities(FilesystemOperator $operator, bool $local): Capabilities
    {
        $adapter = self::adapter($operator);
        $config = self::property($operator, Filesystem::class, 'config');
        $publicUrl = $config instanceof Config ? $config->get('public_url') : null;

        return new Capabilities(
            $local,
            null !== self::property($operator, Filesystem::class, 'publicUrlGenerator') || (null !== $publicUrl && '' !== $publicUrl && [] !== $publicUrl) || $adapter instanceof PublicUrlGenerator,
            null !== self::property($operator, Filesystem::class, 'temporaryUrlGenerator') || $adapter instanceof TemporaryUrlGenerator,
        );
    }

    /** @param class-string $class */
    private static function property(object $object, string $class, string $property): mixed
    {
        if (!$object instanceof $class || !property_exists($class, $property)) {
            return null;
        }
        $reflection = new \ReflectionProperty($class, $property);

        return $reflection->isInitialized($object) ? $reflection->getValue($object) : null;
    }
}
