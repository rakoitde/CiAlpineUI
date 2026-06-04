<?php

declare(strict_types=1);

namespace Rakoitde\CiAlpineUI\Exceptions;

use RuntimeException;

/**
 * Base exception for all CiAlpineUI errors.
 *
 * Throw this (or a subclass) for any library-specific error condition so
 * consuming applications can catch CiAlpineUI errors independently of
 * generic PHP exceptions.
 */
class CiAlpineUiException extends RuntimeException
{
}
