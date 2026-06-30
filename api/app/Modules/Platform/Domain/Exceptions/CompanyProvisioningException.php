<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Exceptions;

use App\Exceptions\DomainException;

class CompanyProvisioningException extends DomainException
{
    public function __construct(string $reason = 'Company provisioning failed.')
    {
        parent::__construct($reason, 500);
    }
}
