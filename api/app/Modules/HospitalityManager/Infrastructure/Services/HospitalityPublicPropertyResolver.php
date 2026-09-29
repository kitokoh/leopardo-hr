<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use App\Shared\Services\PublicCommerce\TrackingSecretService;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * HOSP-006 (#7948, spec §6) — Résolution des ressources PUBLIQUES de la
 * vitrine /stay pour les endpoints sans auth `/public/hospitality/*`.
 *
 * Mêmes garde-fous fail-closed que `RestaurantPublicBranchResolver`
 * (RESTO-902 #7747, pattern validé) :
 *  - établissement `is_public = true`, actif, `public_slug` non nul ;
 *  - société ni suspendue ni expirée, verticale `hospitality` activée ;
 *  - toute condition manquante → 404 uniforme (jamais un 403 qui révélerait
 *    l'existence de la ressource — anti-énumération).
 *
 * La résolution traverse les tenants du schéma partagé (`shared_tenants`),
 * puis le callback s'exécute DANS le tenant de la ressource : le scope
 * BelongsToCompany s'applique — aucun accès inter-tenant possible.
 *
 * BOS-050 (#8208, tranche 5) : l'invariant société (statut
 * suspended/expired + feature `hospitality`) et la bascule tenant sont
 * délégués au socle mutualisé {@see PublicTenantResolver}
 * (`assertAccessible()` + `withinTenant()` — marqueur
 * `tenant_scope_required` restauré en `finally`, imbrication sûre) ; la
 * résolution BORNÉE par ressource (établissement par slug, réservation par
 * référence + secret) reste portée ici, comme prévu par le socle. La
 * vérification du code de suivi est déléguée à {@see TrackingSecretService}
 * (comparaison timing-safe, fail-closed — sémantique identique au
 * `hash('sha256')` + `hash_equals` historique).
 */
final class HospitalityPublicPropertyResolver
{
    public function __construct(
        private readonly PublicTenantResolver $publicTenants,
        private readonly TrackingSecretService $trackingSecrets,
    ) {}

    /**
     * Exécute `$callback(HospitalityProperty $property)` dans le tenant de
     * l'établissement public résolu par `$slug`. 404 fail-closed si le slug
     * est inconnu, dépublié, inactif, ou si la société est
     * suspendue/expirée/sans flag verticale.
     *
     * @template T
     *
     * @param  Closure(HospitalityProperty): T  $callback
     * @return T
     */
    public function within(string $slug, Closure $callback): mixed
    {
        $row = DB::table($this->tenantTable('hospitality_properties').' as p')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->where('p.public_slug', $slug)
            ->where('p.is_public', true)
            ->where('p.status', HospitalityProperty::STATUS_ACTIVE)
            ->whereNotIn('c.status', ['suspended', 'expired'])
            ->select(['p.id', 'p.company_id'])
            ->first();

        if ($row === null) {
            abort(404);
        }

        /** @var array<string, mixed> $propertyRow */
        $propertyRow = get_object_vars($row);
        $companyId = $propertyRow['company_id'] ?? null;
        $propertyId = $propertyRow['id'] ?? null;

        if (! is_scalar($companyId) || ! is_numeric($propertyId)) {
            abort(404);
        }

        /** @var Company|null $company */
        $company = Company::query()->find((string) $companyId);

        if (! $company instanceof Company) {
            abort(404);
        }

        $company = $this->publicTenants->assertAccessible($company, 'hospitality');

        return $this->publicTenants->withinTenant($company, function () use ($propertyId, $callback): mixed {
            /** @var HospitalityProperty|null $property */
            $property = HospitalityProperty::query()
                ->where('id', (int) $propertyId)
                ->where('is_public', true)
                ->where('status', HospitalityProperty::STATUS_ACTIVE)
                ->first();

            if (! $property instanceof HospitalityProperty) {
                abort(404);
            }

            return $callback($property);
        });
    }

    /**
     * Exécute `$callback(HospitalityReservation $reservation)` dans le tenant
     * de la réservation identifiée par `$reference` + `$plainCode` — suivi et
     * annulation sans compte.
     *
     * Anti-énumération (spec §6) : la référence est unique PAR TENANT, la
     * recherche est donc globale et le code de suivi est vérifié par
     * comparaison de hash (`hash_equals`, timing-safe) sur CHAQUE candidat —
     * référence inconnue ET code invalide produisent le MÊME 404, sans
     * révéler laquelle des deux conditions a échoué.
     *
     * @template T
     *
     * @param  Closure(HospitalityReservation): T  $callback
     * @return T
     */
    public function withinReservation(string $reference, string $plainCode, Closure $callback): mixed
    {
        if ($plainCode === '') {
            abort(404);
        }

        $candidates = DB::table($this->tenantTable('hospitality_reservations'))
            ->where('reference', $reference)
            ->select(['id', 'company_id', 'tracking_code_hash'])
            ->get();

        foreach ($candidates as $candidate) {
            /** @var array<string, mixed> $row */
            $row = get_object_vars($candidate);
            $storedHash = $row['tracking_code_hash'] ?? null;

            if (! is_string($storedHash) || ! $this->trackingSecrets->matches($plainCode, $storedHash)) {
                continue;
            }

            $companyId = $row['company_id'] ?? null;
            $reservationId = $row['id'] ?? null;

            if (! is_scalar($companyId) || ! is_numeric($reservationId)) {
                continue;
            }

            /** @var Company|null $company */
            $company = Company::query()->find((string) $companyId);

            if (! $company instanceof Company) {
                abort(404);
            }

            $company = $this->publicTenants->assertAccessible($company, 'hospitality');

            return $this->publicTenants->withinTenant($company, function () use ($reservationId, $callback): mixed {
                /** @var HospitalityReservation|null $reservation */
                $reservation = HospitalityReservation::query()
                    ->where('id', (int) $reservationId)
                    ->first();

                if (! $reservation instanceof HospitalityReservation) {
                    abort(404);
                }

                return $callback($reservation);
            });
        }

        abort(404);
    }

    private function tenantTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }
}
