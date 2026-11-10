<?php

namespace Omnidrive\Exception;

use Omnisense\Contracts\Exception\ExceptionInterface;

/**
 * Every exception Omnidrive throws. It extends omnisense/contracts' interface,
 * so that one catch holds the failures of every omni family.
 */
interface OmnidriveException extends ExceptionInterface
{
}
