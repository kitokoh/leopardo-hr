<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

class SocialPostNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Publication sociale introuvable (id={$id}).", 404);
    }
}
