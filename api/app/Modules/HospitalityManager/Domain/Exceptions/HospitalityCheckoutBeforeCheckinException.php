<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HOSP-004 (#7946), durci #8019 — intervalle invalide à l'édition d'une
 * réservation (`check_out <= check_in`).
 *
 * Remplace l'`abort(422, 'CHECKOUT_BEFORE_CHECKIN')` qui vivait dans le
 * service (fuite HTTP dans la couche domaine). Le rendu HTTP est inchangé :
 * 422 + `error`/`message` = `CHECKOUT_BEFORE_CHECKIN`.
 */
class HospitalityCheckoutBeforeCheckinException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            (string) __('hospitality.checkout_before_checkin'),
            422,
            'CHECKOUT_BEFORE_CHECKIN'
        );
    }
}
