<?php

namespace App\Core\Auth\Domain\Exceptions;

use App\Exceptions\DomainException as BaseDomainException;

/**
 * Base domain exception for Auth module.
 * All Auth-specific domain exceptions extend this class.
 */
abstract class DomainException extends BaseDomainException
{
}
