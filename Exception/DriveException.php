<?php

namespace Omnidrive\Exception;

/**
 * A drive could not do what it was asked: the operation (one of OPERATIONS),
 * the drive and the path are kept; the engine's own exception is the previous
 * one.
 */
class DriveException extends \RuntimeException implements OmnidriveException
{
    /** @var array<string, string> each operation, as the message says it */
    public const OPERATIONS = [
        'read' => 'read',
        'write' => 'write',
        'delete' => 'delete',
        'move' => 'move',
        'copy' => 'copy',
        'list' => 'list',
        'exists' => 'check whether there is',
        'directory' => 'make the directory',
        'metadata' => 'read the metadata of',
        'checksum' => 'compute the checksum of',
        'visibility' => 'read or set the visibility of',
        'public_url' => 'give the public address of',
        'temporary_url' => 'sign a temporary address for',
    ];

    final public function __construct(
        public readonly string $operation,
        public readonly string $drive,
        public readonly string $path,
        string $reason = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('The "%s" drive could not %s "%s"%s', $drive, self::OPERATIONS[$operation] ?? $operation, $path, '' !== $reason ? ': '.$reason : '.'), 0, $previous);
    }

    public static function of(string $operation, string $drive, string $path, ?\Throwable $previous = null): static
    {
        $reason = null === $previous ? '' : self::reason($previous);

        return new static($operation, $drive, $path, $reason, $previous);
    }

    /** Every distinct message of the chain, outermost first, joined with " - ": what the engine was told by its server among them. */
    private static function reason(\Throwable $e): string
    {
        $messages = [];
        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if ('' !== trim($cause->getMessage())) {
                $messages[] = trim($cause->getMessage());
            }
        }

        return implode(' - ', array_unique($messages));
    }
}
