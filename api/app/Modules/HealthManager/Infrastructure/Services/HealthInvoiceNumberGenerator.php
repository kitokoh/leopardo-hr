<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Infrastructure\Services;

use App\Modules\HealthManager\Domain\Models\HealthInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Génération du n° de facture de soins — HC-007 (#7791, BC-30).
 *
 * Format `HINV-YYYY-NNNN` : séquence PAR TENANT et par année, posée à
 * l'ÉMISSION de la facture (les brouillons ne consomment pas de numéro),
 * calculée côté serveur (jamais acceptée depuis la requête), zéro-paddée
 * sur 4 chiffres (déborde naturellement sur 5+ au-delà de 9999).
 *
 * Concurrence : à appeler DANS une transaction — `lockForUpdate()` sur les
 * factures numérotées de l'année verrouille le calcul ; la contrainte
 * UNIQUE(company_id, number) reste le filet de sécurité en base. Pattern
 * HealthMrnGenerator (HC-003) : un numéro n'est JAMAIS réutilisé.
 */
final class HealthInvoiceNumberGenerator
{
    public function next(string $companyId): string
    {
        $prefix = 'HINV-'.now()->format('Y').'-';

        /** @var string|null $last */
        $last = HealthInvoice::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            // Tri par longueur PUIS valeur : '10000' > '9999' malgré l'ordre
            // lexicographique.
            ->orderByDesc(DB::raw('char_length(number)'))
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');

        $next = 1;
        if (is_string($last)) {
            $next = (int) substr($last, strlen($prefix)) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
