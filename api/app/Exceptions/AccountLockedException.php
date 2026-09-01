<?php

namespace App\Exceptions;

use Illuminate\Support\Carbon;

class AccountLockedException extends DomainException
{
    private Carbon $unlockDate;

    public function __construct(Carbon $until)
    {
        $this->unlockDate = $until;
        parent::__construct(
            "Compte verrouillé jusqu'à ".$until->toIso8601String(),
            423,
            'ACCOUNT_LOCKED'
        );
    }

    /**
     * Date de déverrouillage du compte — exposée au renderer pour la
     * traduction paramétrée (issue #6685), jamais le message brut.
     */
    public function unlockDate(): Carbon
    {
        return $this->unlockDate;
    }
}
