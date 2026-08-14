<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmitted;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Issue #1813 — Workflow de validation des modifications de taux légaux.
 *
 * Protège `tax_slabs` et `social_contributions` (utilisées directement dans
 * les bulletins) contre les changements accidentels ou non autorisés :
 *
 *   draft → pending_validation → active (l'ancienne ligne active → superseded)
 *                                ↘ rejected → draft (motif tracé)
 *
 * - Seules les lignes `status = active` sont utilisées dans les calculs
 *   (filtre ajouté dans AbstractCountryRules::resolveTaxSlabsFromDatabase et
 *   resolveContribution).
 * - Chaque transition écrit une entrée immuable dans `tax_rate_change_log`
 *   (append-only, état avant/après en JSONB).
 * - Approbation réservée au platform_admin ; soumission réservée au
 *   comptable/principal du tenant.
 */
class TaxRateValidationService
{
    public function __construct() {}

    /**
     * Soumet une ligne draft pour validation (draft → pending_validation).
     *
     * @param  TaxSlab|SocialContribution  $model
     *
     * @throws \DomainException si la ligne n'est pas en draft
     */
    public function submit(Model $model, Employee $actor): Model
    {
        $this->assertTable($model);

        if ($model->status !== TaxSlab::STATUS_DRAFT) {
            throw new \DomainException(sprintf(
                'Seule une ligne en brouillon peut être soumise (statut actuel : %s).',
                (string) $model->status,
            ));
        }

        $previous = TaxRateChangeLog::snapshot($model);
        $model->forceFill([
            'status' => TaxSlab::STATUS_PENDING,
            'submitted_by' => $actor->id,
            'rejection_reason' => null,
        ])->save();

        $this->log($model, TaxRateChangeLog::ACTION_SUBMITTED, (int) $actor->id, 'employee', $previous);

        TaxRateSubmitted::dispatch($model, $actor);

        return $model;
    }

    /**
     * Approuve une ligne pending (pending_validation → active).
     * L'ancienne ligne active de même identité passe en superseded.
     *
     * @param  TaxSlab|SocialContribution  $model
     *
     * @throws \DomainException si la ligne n'est pas en attente
     */
    public function approve(Model $model, SuperAdmin $actor): Model
    {
        $this->assertTable($model);

        if ($model->status !== TaxSlab::STATUS_PENDING) {
            throw new \DomainException(sprintf(
                'Seule une ligne en attente de validation peut être approuvée (statut actuel : %s).',
                (string) $model->status,
            ));
        }

        $previous = TaxRateChangeLog::snapshot($model);

        // L'ancienne ligne active de même identité devient superseded.
        foreach ($this->activeIdenticalRows($model) as $oldRow) {
            $oldPrevious = TaxRateChangeLog::snapshot($oldRow);
            $oldRow->forceFill([
                'status' => TaxSlab::STATUS_SUPERSEDED,
                'validated_by' => $actor->id,
                'validated_at' => now(),
            ])->save();

            $this->log($oldRow, TaxRateChangeLog::ACTION_SUPERSEDED, (int) $actor->id, 'platform_admin', $oldPrevious);
        }

        $model->forceFill([
            'status' => TaxSlab::STATUS_ACTIVE,
            'validated_by' => $actor->id,
            'validated_at' => now(),
        ])->save();

        $this->log($model, TaxRateChangeLog::ACTION_APPROVED, (int) $actor->id, 'platform_admin', $previous);

        TaxRateApproved::dispatch($model, $actor);

        return $model;
    }

    /**
     * Rejette une ligne pending (pending_validation → draft) avec motif.
     *
     * @param  TaxSlab|SocialContribution  $model
     *
     * @throws \DomainException si la ligne n'est pas en attente ou sans motif
     */
    public function reject(Model $model, SuperAdmin $actor, string $reason): Model
    {
        $this->assertTable($model);

        if ($model->status !== TaxSlab::STATUS_PENDING) {
            throw new \DomainException(sprintf(
                'Seule une ligne en attente de validation peut être rejetée (statut actuel : %s).',
                (string) $model->status,
            ));
        }

        if (trim($reason) === '') {
            throw new \DomainException('Un motif de rejet est obligatoire.');
        }

        $previous = TaxRateChangeLog::snapshot($model);
        $model->forceFill([
            'status' => TaxSlab::STATUS_DRAFT,
            'validated_by' => $actor->id,
            'validated_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        $this->log($model, TaxRateChangeLog::ACTION_REJECTED, (int) $actor->id, 'platform_admin', $previous, trim($reason));

        TaxRateRejected::dispatch($model, $actor, trim($reason));

        return $model;
    }

    /**
     * Historique immuable d'une ligne (append-only), du plus récent au plus ancien.
     *
     * @return Collection<int, TaxRateChangeLog>
     */
    public function history(Model $model): Collection
    {
        $this->assertTable($model);

        return TaxRateChangeLog::query()
            ->where('table_name', $this->tableName($model))
            ->where('record_id', $model->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Journalise la création d'une ligne draft (trace l'état initial).
     *
     * @param  TaxSlab|SocialContribution  $model
     */
    public function logCreated(Model $model, Employee $actor): void
    {
        $this->assertTable($model);

        $this->log($model, TaxRateChangeLog::ACTION_CREATED, (int) $actor->id, 'employee');
    }

    /**
     * @param  TaxSlab|SocialContribution  $model
     * @return \Illuminate\Database\Eloquent\Collection<int, TaxSlab|SocialContribution>
     */
    private function activeIdenticalRows(Model $model): \Illuminate\Database\Eloquent\Collection
    {
        $query = $model::query()
            ->where('status', TaxSlab::STATUS_ACTIVE)
            ->where('country_code', $model->country_code)
            ->where('company_id', $model->company_id);

        if ($model instanceof TaxSlab) {
            $query->where('min_amount', $model->min_amount)
                ->where('max_amount', $model->max_amount);
        }

        if ($model instanceof SocialContribution) {
            $query->where('code', $model->code);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, TaxSlab|SocialContribution> $rows */
        $rows = $query->whereKeyNot($model->getKey())->get();

        return $rows;
    }

    private function tableName(Model $model): string
    {
        if ($model instanceof TaxSlab) {
            return TaxRateChangeLog::TABLE_TAX_SLABS;
        }

        if ($model instanceof SocialContribution) {
            return TaxRateChangeLog::TABLE_SOCIAL_CONTRIBUTIONS;
        }

        throw new \InvalidArgumentException('Type de ligne non supporté par le workflow de validation.');
    }

    private function assertTable(Model $model): void
    {
        if (! $model instanceof TaxSlab && ! $model instanceof SocialContribution) {
            throw new \InvalidArgumentException('Type de ligne non supporté par le workflow de validation.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $previous
     */
    private function log(
        Model $model,
        string $action,
        int $actorId,
        string $actorRole,
        ?array $previous = null,
        ?string $reason = null,
    ): void {
        TaxRateChangeLog::create([
            'table_name' => $this->tableName($model),
            'record_id' => (int) $model->getKey(),
            'action' => $action,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'previous_value' => $previous,
            'new_value' => TaxRateChangeLog::snapshot($model),
            'reason' => $reason,
        ]);
    }
}
