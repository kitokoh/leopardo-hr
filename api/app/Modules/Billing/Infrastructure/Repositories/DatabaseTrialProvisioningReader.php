<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Repositories;

use App\Modules\Billing\Application\Contracts\TrialProvisioningReader;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation du contrat `TrialProvisioningReader` (#7962).
 *
 * C'est ici — et seulement ici — que la façade `DB` a le droit d'apparaître :
 * Infrastructure est la couche autorisée à connaître le stockage.
 */
class DatabaseTrialProvisioningReader implements TrialProvisioningReader
{
    public function findByToken(string $token): ?object
    {
        return DB::table('trial_provisionings')
            ->where('provisioning_token', $token)
            ->first();
    }
}
