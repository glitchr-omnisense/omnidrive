<?php

namespace Omnidrive\Model;

/**
 * What a drive can do besides reading and writing, said before it is asked:
 * whether its files are files of this machine (Drive::localPath()), whether it
 * has a public address (Drive::publicUrl()), whether it signs temporary ones
 * (Drive::temporaryUrl()).
 */
final readonly class Capabilities
{
    public function __construct(
        public bool $local = false,
        public bool $publicUrls = false,
        public bool $temporaryUrls = false,
    ) {
    }

    /** @return array{local: bool, publicUrls: bool, temporaryUrls: bool} */
    public function toArray(): array
    {
        return ['local' => $this->local, 'publicUrls' => $this->publicUrls, 'temporaryUrls' => $this->temporaryUrls];
    }
}
