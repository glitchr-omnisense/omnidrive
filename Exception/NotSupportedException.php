<?php

namespace Omnidrive\Exception;

/**
 * The drive does not do that: a public address for a drive that has none, a
 * signed one for a drive that cannot sign. Its Capabilities say so beforehand.
 */
final class NotSupportedException extends \LogicException implements OmnidriveException
{
    public static function operation(string $drive, string $operation, string $why = ''): self
    {
        return new self(\sprintf('The "%s" drive does not give %s%s.', $drive, $operation, '' !== $why ? ': '.$why : ''));
    }
}
