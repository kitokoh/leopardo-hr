<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * L'entreprise impose le mode de pointage "photo obligatoire" et l'employe
 * a tente de pointer (arrivee ou depart) sans fournir de photo.
 */
class PunchPhotoRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A photo is required to punch in or out for this company.', 422);
    }

    public function errorCode(): string
    {
        return 'PUNCH_PHOTO_REQUIRED';
    }
}
