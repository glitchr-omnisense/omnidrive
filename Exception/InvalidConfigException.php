<?php

namespace Omnidrive\Exception;

/** A drive that is not configured, a factory that is not installed, an option missing. */
final class InvalidConfigException extends \InvalidArgumentException implements OmnidriveException
{
}
