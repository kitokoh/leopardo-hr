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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1813 — Workflow de validation des modifications de taux légaux.
 *
 * Protège `tax_slabs` et `social_contributions` (utilisées directement dans
 * les bulletins) contre les changements accidentels ou non autorisés :
 *
 *   draft → pending_validation → active (l'ancienne ligne active → fenêtre
 *                                  d'effet fermée, effective_to = nouveau
 *                                  effective_from − 1 jour)
 *                                ↘ rejected → draft (motif tracé)
 *
 * Issue #1923 (revue lead) :
 * - Chaque transition s'exécute dans UNE transaction (statut + supersession
 *   + audit ne peuvent plus être désynchronisés) ;
 * - Le rejet ne tamponne PLUS `validated_by`/`validated_at` (seule
 *   l'approbation valide) ;
 * - La rétroactivité (PA2-ARCH-004) : l'ancienne ligne reste `active` avec
 *   sa fenêtre FERMÉE au lieu d'un flip `superseded` — un recalcul historique
 *   (`asOf()`) résout encore l'ancien taux pour les périodes antérieures ;
 * - Les messages sont localisés (catalogue `payroll`, 4 langues).
 *
 * - Seules les lignes `status = active` ET effectives à la date calculée
 *   sont utilisées dans les calculs (AbstractCountryRules::resolveTaxSlabsFromDatabase
 *   et resolveContribution).
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
            throw new \DomainException(__('payroll.rate_submit_draft_only', [
                'status' => (string) $model->status,
            ]));
        }

        // Issue #1923 : statut + audit dans la même transaction — plus de
        // demi-transition possible en cas d'erreur entre les deux requêtes.
        DB::transaction(function () use ($model, $actor): void {
            $previous = TaxRateChangeLog::snapshot($model);
            $model->forceFill([
                'status' => TaxSlab::STATUS_PENDING,
                'submitted_by' => $actor->id,
                'rejection_reason' => null,
            ])->save();

            $this->log($model, TaxRateChangeLog::ACTION_SUBMITTED, (int) $actor->id, 'employee', $previous);
        });

        // Notification après commit (listener synchrone best-effort : un
        // échec d'email ne doit jamais faire échouer la transition).
        TaxRateSubmitted::dispatch($model, $actor);

        return $model;
    }

    /**
     * Approuve une ligne pending (pending_validation → active).
     * L'ancienne ligne active de même identité voit sa fenêtre d'effet
     * fermée (effective_to = nouveau effective_from − 1 jour) au lieu d'un
     * flip `superseded` : la rétroactivité PA2-ARCH-004 reste possible.
     *
     * @param  TaxSlab|SocialContribution  $model
     *
     * @throws \DomainException si la ligne n'est pas en attente
     */
    public function approve(Model $model, SuperAdmin $actor): Model
    {
        $this->assertTable($model);

        if ($model->status !== TaxSlab::STATUS_PENDING) {
            throw new \DomainException(__('payroll.rate_approve_pending_only', [
                'status' => (string) $model->status,
            ]));
        }

        DB::transaction(function () use ($model, $actor): void {
            $previous = TaxRateChangeLog::snapshot($model);

            // Les lignes actives de même identité dont la fenêtre CHEVAUCHE la
            // nouvelle sont fermées (les lignes déjà closes restent intactes).
            foreach ($this->activeIdenticalRows($model) as $oldRow) {
                $oldPrevious = TaxRateChangeLog::snapshot($oldRow);
                $this->closeOldRowWindow($oldRow, $model);

                $this->log($oldRow, TaxRateChangeLog::ACTION_SUPERSEDED, (int) $actor->id, 'platform_admin', $oldPrevious);
            }

            $model->forceFill([
                'status' => TaxSlab::STATUS_ACTIVE,
                'validated_by' => $actor->id,
                'validated_at' => now(),
            ])->save();

            $this->log($model, TaxRateChangeLog::ACTION_APPROVED, (int) $actor->id, 'platform_admin', $previous);
        });

        TaxRateApproved::dispatch($model, $actor);

        return $model;
    }

    /**
     * Rejette une ligne pending (pending_validation → draft) avec motif.
     *
     * Issue #1923 : le rejet ne tamponne PAS `validated_by`/`validated_at` —
     * seule l'approbation signifie « validé ». La ligne rejetée repart en
     * draft avec le motif, prête à être corrigée puis re-soumise.
     *
     * @param  TaxSlab|SocialContribution  $model
     *
     * @throws \DomainException si la ligne n'est pas en attente ou sans motif
     */
    public function reject(Model $model, SuperAdmin $actor, string $reason): Model
    {
        $this->assertTable($model);

        if ($model->status !== TaxSlab::STATUS_PENDING) {
            throw new \DomainException(__('payroll.rate_reject_pending_only', [
                'status' => (string) $model->status,
            ]));
        }

        if (trim($reason) === '') {
            throw new \DomainException(__('payroll.rate_reject_reason_required'));
        }

        DB::transaction(function () use ($model, $actor, $reason): void {
            $previous = TaxRateChangeLog::snapshot($model);
            $model->forceFill([
                'status' => TaxSlab::STATUS_DRAFT,
                'rejection_reason' => trim($reason),
            ])->save();

            $this->log($model, TaxRateChangeLog::ACTION_REJECTED, (int) $actor->id, 'platform_admin', $previous, trim($reason));
        });

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
     * Issue #1923 — audit du CRUD admin national (company_id IS NULL).
     *
     * Les lignes nationales du référentiel légal ne passent pas par le
     * workflow draft→validation : le platform_admin les crée/modifie/
     * supprime directement. Chaque mutation est tracée dans
     * `tax_rate_change_log` (action created/updated/deleted/reset,
     * actor_role = platform_admin) pour préserver l'audit trail immuable.
     *
     * @param  TaxSlab|SocialContribution  $model
     */
    public function logAdminCreated(Model $model, SuperAdmin $actor): void
    {
        $this->assertTable($model);

        $this->log($model, TaxRateChangeLog::ACTION_CREATED, (int) $actor->id, 'platform_admin');
    }

    /**
     * @param  TaxSlab|SocialContribution  $model
     * @param  array<string, mixed>  $previous  snapshot AVANT la mise à jour
     */
    public function logAdminUpdated(Model $model, SuperAdmin $actor, array $previous): void
    {
        $this->assertTable($model);

        $this->log($model, TaxRateChangeLog::ACTION_UPDATED, (int) $actor->id, 'platform_admin', $previous);
    }

    /**
     * @param  TaxSlab|SocialContribution  $model
     * @param  array<string, mixed>  $snapshot  état de la ligne au moment de la suppression
     */
    public function logAdminDeleted(Model $model, SuperAdmin $actor, array $snapshot): void
    {
        $this->assertTable($model);

        $this->log($model, TaxRateChangeLog::ACTION_DELETED, (int) $actor->id, 'platform_admin', $snapshot, null, $snapshot);
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

        // Issue #1923 — ne fermer QUE les lignes dont la fenêtre d'effet
        // chevauche la nouvelle (une ligne déjà close avant le nouveau
        // effective_from doit rester intacte : la refermer l'ÉTENDRAIT).
        $query->where(function ($q) use ($model): void {
            $q->whereNull('effective_to')->orWhere('effective_to', '>=', $model->effective_from);
        });

        if ($model->effective_to !== null) {
            $query->where('effective_from', '<=', $model->effective_to);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, TaxSlab|SocialContribution> $rows */
        $rows = $query->whereKeyNot($model->getKey())->get();

        return $rows;
    }

    /**
     * Issue #1923 (PA2-ARCH-004) — ferme la fenêtre d'effet de l'ancienne
     * ligne active au lieu de flipper son statut :
     *
     * - cas nominal : `effective_to = nouveau effective_from − 1 jour`, la
     *   ligne reste `active` → `active() + effective(asOf)` résout encore
     *   l'ancien taux pour les périodes antérieures au changement ;
     * - cas dégénéré (nouvelle ligne qui démarre avant/le même jour que
     *   l'ancienne) : une fenêtre fermée serait inversée (effective_to <
     *   effective_from) — l'ancienne ligne est entièrement remplacée et
     *   passe `superseded` (elle ne matchera jamais `effective()`).
     *
     * @param  TaxSlab|SocialContribution  $oldRow
     * @param  TaxSlab|SocialContribution  $newRow
     */
    private function closeOldRowWindow(Model $oldRow, Model $newRow): void
    {
        $newEffectiveFrom = Carbon::parse($newRow->effective_from);
        $closureDate = $newEffectiveFrom->copy()->subDay();

        if (Carbon::parse($oldRow->effective_from)->greaterThan($closureDate)) {
            $oldRow->forceFill(['status' => TaxSlab::STATUS_SUPERSEDED])->save();

            return;
        }

        $oldRow->forceFill(['effective_to' => $closureDate->toDateString()])->save();
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
     * @param  array<string, mixed>|null  $newValue
     */
    private function log(
        Model $model,
        string $action,
        int $actorId,
        string $actorRole,
        ?array $previous = null,
        ?string $reason = null,
        ?array $newValue = null,
    ): void {
        TaxRateChangeLog::create([
            'table_name' => $this->tableName($model),
            'record_id' => (int) $model->getKey(),
            'action' => $action,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'previous_value' => $previous,
            'new_value' => $newValue ?? TaxRateChangeLog::snapshot($model),
            'reason' => $reason,
        ]);
    }
}
