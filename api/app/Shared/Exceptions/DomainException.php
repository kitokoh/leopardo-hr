<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Exceptions\DomainException as BaseDomainException;

/**
 * Base shared domain exception for cross-module errors.
 * Module-specific exceptions should extend their own Domain\Exceptions\*.
 */
abstract class DomainException extends BaseDomainException
{
}
