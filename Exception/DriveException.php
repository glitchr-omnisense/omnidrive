<?php

namespace Omnidrive\Exception;

use Omnisense\Contracts\Http\Redaction;

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

    /**
     * The failure of an operation, the engine's own exception under it. Its
     * reason holds no credential (an address's user part and the parameters
     * named as credentials masked: a pre-signed URL's signature, a WebDAV
     * server's password), and no trace of the chain keeps its calls'
     * arguments (the contents written, the client's options and keys).
     */
    public static function of(string $operation, string $drive, string $path, ?\Throwable $previous = null): static
    {
        $reason = null === $previous ? '' : self::reason($previous);

        return Redaction::withoutArguments(new static($operation, $drive, $path, $reason, $previous));
    }

    /** Every distinct message of the chain, outermost first, joined with " - ": what the engine was told by its server among them, credentials masked. */
    private static function reason(\Throwable $e): string
    {
        $messages = [];
        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if ('' !== trim($cause->getMessage())) {
                $messages[] = Redaction::text(trim($cause->getMessage()));
            }
        }

        return implode(' - ', array_unique($messages));
    }
}
