<?php

namespace Omnidrive\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\Config as FlysystemConfig;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Omnidrive\Config;
use Omnidrive\DriveFactory;
use Omnidrive\Model\Capabilities;
use Omnidrive\Visibility;

/**
 * A provider's factory: it builds the league adapter from its options, this
 * class the drive around it. The options every drive takes:
 *
 *   - public_url: where its files are served from, a prefix ("https://cdn.example.org/uploads",
 *     "/uploads") or a list of them (sharded); else the adapter's own (a bucket's);
 *   - visibility, directory_visibility: what a file or a directory is written with by default
 *     ("public", "private");
 *   - retain_visibility: whether a copy keeps the visibility of its source (true, Flysystem's);
 *     false copies with the drive's default visibility, for a server that cannot report a file's;
 *   - permissions (a drive of Unix files: local, SFTP, FTP): the modes each visibility is,
 *     {file: {public: 0644, private: 0600}, dir: {public: 0755, private: 0700}}.
 */
abstract class FlysystemDriveFactory extends DriveFactory
{
    /**
     * @param bool $adapterPublicUrls whether the adapter's own public address is the drive's when no
     *                                "public_url" is given (false for an adapter whose "public" address
     *                                is a signed one that never really expires: Azure's)
     */
    protected function drive(string $name, FilesystemAdapter $adapter, Config $c, ?string $localRoot = null, bool $adapterPublicUrls = true): FlysystemDrive
    {
        $config = [];
        $publicUrl = $c->list('public_url');
        if ([] !== $publicUrl) {
            $config['public_url'] = 1 === \count($publicUrl) ? $publicUrl[0] : $publicUrl;
        }
        foreach (['visibility', 'directory_visibility'] as $key) {
            if (null !== $value = $c->string($key)) {
                $config[$key] = Visibility::of($value)->value;
            }
        }
        if (null !== $c->get('retain_visibility') && '' !== $c->get('retain_visibility')) {
            $config['retain_visibility'] = $c->bool('retain_visibility', true);
        }

        $publicUrls = isset($config['public_url']) || ($adapterPublicUrls && $adapter instanceof PublicUrlGenerator);
        $refused = $publicUrls ? null : new class implements PublicUrlGenerator {
            public function publicUrl(string $path, FlysystemConfig $config): string
            {
                throw UnableToGeneratePublicUrl::noGeneratorConfigured($path);
            }
        };

        return new FlysystemDrive($name, new Filesystem($adapter, $config, null, $refused), $localRoot, new Capabilities(
            null !== $localRoot,
            $publicUrls,
            $adapter instanceof TemporaryUrlGenerator,
        ));
    }

    /** The Unix modes of the "permissions" option, octal strings accepted; the directories' default visibility (Flysystem's: private). */
    protected function unixVisibility(Config $c): PortableVisibilityConverter
    {
        $permissions = $c->get('permissions', []);
        $map = [];
        foreach (['file' => [0644, 0600], 'dir' => [0755, 0700]] as $type => [$public, $private]) {
            /** @var array<string, mixed> $modes */
            $modes = \is_array($permissions) && \is_array($permissions[$type] ?? null) ? $permissions[$type] : [];
            $given = new Config($modes);
            $map[$type] = ['public' => $given->octal('public', $public), 'private' => $given->octal('private', $private)];
        }

        return PortableVisibilityConverter::fromArray($map, Visibility::of($c->string('directory_visibility') ?? 'private')->value);
    }
}
