<?php

namespace Omnidrive;

/** Builds a drive from its options (a root, a bucket and its keys, a server...). */
interface DriveFactoryInterface
{
    /** The name drives are configured with: "local", "s3", "sftp", "ftp"... */
    public function getName(): string;

    /**
     * @param array<string, mixed> $options
     * @param string|null          $name    the drive's name (Drive::name()); the factory's when null
     */
    public function create(array $options = [], ?string $name = null): Drive;
}
