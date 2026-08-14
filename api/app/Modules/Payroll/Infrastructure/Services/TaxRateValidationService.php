<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ADMIN-PAIE (issue #1813) — workflow de validation des modifications de taux
 * légaux (barèmes fiscaux `tax_slabs` / cotisations sociales
 * `social_contributions`) : double signature + audit trail immuable.
 *
 * Flux :
 *   draft → submit (RH/comptable) → pending_validation → approve (platform
 *   admin) → active (+ ancienne version → superseded) | reject (motif) → draft.
 *
 * Chaque transition écrit une ligne append-only dans `tax_rate_change_log`
 * (table verrouillée en UPDATE/DELETE par un trigger PostgreSQL) — l'historique
 * est inaltérable, y compris rétroactivement.
 */
class TaxRateValidationService
{
    private const TABLE_LABELS = [
        'tax_slabs' => 'tax_slabs',
        'social_contributions' => 'social_contributions',
    ];

    /**
     * Soumission pour validation (RH/comptable) : draft → pending_validation.
     */
    public function submit(TaxSlab|SocialContribution $rate, Employee $actor): TaxSlab|SocialContribution
    {
        $this->assertSupported($rate);

        if ($rate->status !== TaxSlab::STATUS_DRAFT) {
            throw new RuntimeException('Seule une ligne brouillon (draft) peut être soumise pour validation.');
        }

        return DB::transaction(function () use ($rate, $actor): TaxSlab|SocialContribution {
            $previous = $this->snapshot($rate);

            $rate->update([
                'status' => TaxSlab::STATUS_PENDING,
                'submitted_by' => $actor->id,
                'rejection_reason' => null,
            ]);

            $this->log($rate, TaxRateChangeLog::ACTION_SUBMITTED, $actor->id, $actor->isManager() ? 'manager' : 'employee', $previous);

            return $rate->refresh();
        });
    }

    /**
     * Trace la création d'une ligne (action 'created' dans le log immuable).
     */
    public function recordCreated(TaxSlab|SocialContribution $rate, Employee $actor): void
    {
        $this->assertSupported($rate);

        $this->log($rate, TaxRateChangeLog::ACTION_CREATED, $actor->id, $actor->isManager() ? 'manager' : 'employee', []);
    }

    /**
     * Approbation (platform admin) : pending_validation → active. L'ancienne
     * version active (même pays / même scope / effective_from antérieure) est
     * marquée `superseded` pour ne plus jamais participer aux calculs.
     *
     * @param  int  $adminId  id du SuperAdmin connecté
     */
    public function approve(TaxSlab|SocialContribution $rate, int $adminId): TaxSlab|SocialContribution
    {
        $this->assertSupported($rate);

        if ($rate->status !== TaxSlab::STATUS_PENDING) {
            throw new RuntimeException('Seule une ligne en attente de validation peut être approuvée.');
        }

        return DB::transaction(function () use ($rate, $adminId): TaxSlab|SocialContribution {
            $previous = $this->snapshot($rate);

            $rate->update([
                'status' => TaxSlab::STATUS_ACTIVE,
                'validated_by' => $adminId,
                'validated_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->supersedeOlderVersions($rate, $adminId);
            $this->log($rate, TaxRateChangeLog::ACTION_APPROVED, $adminId, 'platform_admin', $previous);

            return $rate->refresh();
        });
    }

    /**
     * Rejet (platform admin) : pending_validation → draft, motif obligatoire.
     */
    public function reject(TaxSlab|SocialContribution $rate, int $adminId, string $reason): TaxSlab|SocialContribution
    {
        $this->assertSupported($rate);

        if ($rate->status !== TaxSlab::STATUS_PENDING) {
            throw new RuntimeException('Seule une ligne en attente de validation peut être rejetée.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Un motif de rejet est obligatoire (audit trail).');
        }

        return DB::transaction(function () use ($rate, $adminId, $reason): TaxSlab|SocialContribution {
            $previous = $this->snapshot($rate);

            $rate->update([
                'status' => TaxSlab::STATUS_DRAFT,
                'submitted_by' => null,
                'rejection_reason' => $reason,
            ]);

            $this->log($rate, TaxRateChangeLog::ACTION_REJECTED, $adminId, 'platform_admin', $previous, $reason);

            return $rate->refresh();
        });
    }

    /**
     * Historique immuable d'une ligne (ordonné du plus récent au plus ancien).
     */
    public function history(TaxSlab|SocialContribution $rate): Collection
    {
        $this->assertSupported($rate);

        /** @var Collection<int, TaxRateChangeLog> $entries */
        $entries = TaxRateChangeLog::query()
            ->where('table_name', $rate->getTable())
            ->where('record_id', $rate->id)
            ->orderByDesc('id')
            ->get();

        return $entries;
    }

    /**
     * Marque `superseded` les lignes ACTIVES antérieures du même périmètre
     * (pays + scope entreprise + code pour les cotisations) dont la date
     * d'effet est STRICTEMENT antérieure à celle de la ligne approuvée. Les
     * tranches d'un même barème partagent la même effective_from → elles ne
     * sont pas touchées (seule une NOUVELLE version datée les supplante).
     */
    private function supersedeOlderVersions(TaxSlab|SocialContribution $rate, int $adminId): void
    {
        $query = $rate->newQuery()
            ->forCountry($rate->country_code)
            ->where('effective_from', '<', $rate->effective_from)
            ->active();

        if ($rate->company_id === null) {
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $rate->company_id);
        }

        if ($rate instanceof SocialContribution) {
            $query->where('code', $rate->code);
        }

        $older = $query->get();

        foreach ($older as $old) {
            $oldPrevious = $this->snapshot($old);
            $old->update(['status' => TaxSlab::STATUS_SUPERSEDED]);
            $this->log($old, TaxRateChangeLog::ACTION_SUPERSEDED, $adminId, 'platform_admin', $oldPrevious, null, $rate);
        }
    }

    private function log(
        TaxSlab|SocialContribution $rate,
        string $action,
        ?int $actorId,
        string $actorRole,
        array $previous,
        ?string $reason = null,
        TaxSlab|SocialContribution|null $supersedingRate = null
    ): void {
        TaxRateChangeLog::create([
            'table_name' => $rate->getTable(),
            'record_id' => $rate->id,
            'action' => $action,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'previous_value' => $previous,
            'new_value' => $this->snapshot($rate),
            'reason' => $reason ?? ($supersedingRate !== null
                ? "Remplacée par la ligne #{$supersedingRate->id} (effective depuis {$supersedingRate->effective_from->toDateString()})"
                : null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TaxSlab|SocialContribution $rate): array
    {
        return [
            'company_id' => $rate->company_id,
            'country_code' => $rate->country_code,
            'rate' => (float) $rate->rate,
            'cap' => $rate instanceof SocialContribution ? $rate->cap : null,
            'min_amount' => $rate instanceof TaxSlab ? $rate->min_amount : null,
            'max_amount' => $rate instanceof TaxSlab ? $rate->max_amount : null,
            'fixed_deduction' => $rate instanceof TaxSlab ? $rate->fixed_deduction : null,
            'effective_from' => $rate->effective_from?->toDateString(),
            'effective_to' => $rate->effective_to?->toDateString(),
            'status' => $rate->status,
        ];
    }

    private function assertSupported(TaxSlab|SocialContribution $rate): void
    {
        if (! isset(self::TABLE_LABELS[$rate->getTable()])) {
            throw new RuntimeException('Modèle non supporté par le workflow de validation des taux.');
        }
    }
}
