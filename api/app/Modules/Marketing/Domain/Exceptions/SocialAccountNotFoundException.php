<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

class SocialAccountNotFoundException extends DomainException
{
    public static function forCompany(string $companyId): self
    {
        return new self("Aucun compte social connecte pour l'entreprise {$companyId}.", 404);
    }
}
