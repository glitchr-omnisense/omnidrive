<?php

namespace Omnidrive\Exception;

/**
 * A drive that is not configured, a factory that is not installed, an option missing.
 * omnisense/contracts' (an \InvalidArgumentException), under the family's name.
 */
final class InvalidConfigException extends \Omnisense\Contracts\Exception\InvalidConfigException implements OmnidriveException
{
}
