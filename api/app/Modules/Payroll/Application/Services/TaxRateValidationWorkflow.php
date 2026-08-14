<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Events\SocialContributionApproved;
use App\Events\SocialContributionRejected;
use App\Events\SocialContributionSubmittedForValidation;
use App\Events\TaxSlabApproved;
use App\Events\TaxSlabRejected;
use App\Events\TaxSlabSubmittedForValidation;
use App\Modules\Payroll\Domain\Exceptions\TaxRateValidationException;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * ADMIN-PAIE (#1813) — workflow de validation des modifications de taux
 * légaux (barèmes fiscaux + cotisations sociales) :
 *
 *   draft → (submit) → pending_validation → (approve) → active, l'ancienne
 *   ligne active étant passée en superseded — ou → (reject) → draft avec
 *   motif obligatoire.
 *
 * Chaque transition écrit une entrée append-only dans `tax_rate_change_log`
 * et dispatche l'événement associé (notification). Seule une ligne
 * `active` est utilisée par le moteur de paie (AbstractCountryRules).
 */
class TaxRateValidationWorkflow
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_VALIDATION = 'pending_validation';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    /**
     * Trace la création d'un brouillon (draft) par un RH/comptable dans le
     * log immuable. Ne change pas le statut (déjà `draft` à la création).
     */
    public function recordCreation(Model $record, Employee $actor): void
    {
        $this->assertSupportedModel($record);
        $this->log(
            $record,
            TaxRateChangeLog::ACTION_CREATED,
            $actor->id,
            'employee',
            null,
            $this->snapshot($record),
            null,
        );
    }

    /**
     * Soumission par un RH/comptable : draft → pending_validation.
     */
    public function submit(Model $record, Employee $actor): Model
    {
        $this->assertSupportedModel($record);
        $this->assertStatus($record, self::STATUS_DRAFT, 'submit');

        $previous = $this->snapshot($record);

        $record->forceFill([
            'status' => self::STATUS_PENDING_VALIDATION,
            'submitted_by' => $actor->id,
            'rejection_reason' => null,
        ])->save();

        $this->log(
            $record,
            TaxRateChangeLog::ACTION_SUBMITTED,
            $actor->id,
            'employee',
            $previous,
            $this->snapshot($record),
            null,
        );

        $this->dispatchSubmitted($record);

        return $record->refresh();
    }

    /**
     * Approbation par un platform_admin : pending_validation → active.
     * La ligne active précédente (même périmètre pays + tranche/code) passe
     * en `superseded` pour préserver l'historique de calcul.
     */
    public function approve(Model $record, SuperAdmin $actor): Model
    {
        $this->assertSupportedModel($record);
        $this->assertStatus($record, self::STATUS_PENDING_VALIDATION, 'approve');

        $previous = $this->snapshot($record);

        $this->supersedePredecessor($record, $actor->id);

        $record->forceFill([
            'status' => self::STATUS_ACTIVE,
            'validated_by' => $actor->id,
            'validated_at' => Carbon::now(),
            'rejection_reason' => null,
        ])->save();

        $this->log(
            $record,
            TaxRateChangeLog::ACTION_APPROVED,
            $actor->id,
            'super_admin',
            $previous,
            $this->snapshot($record),
            null,
        );

        $this->dispatchApproved($record);

        return $record->refresh();
    }

    /**
     * Rejet par un platform_admin : pending_validation → draft avec motif
     * obligatoire (tracé dans le log immuable).
     */
    public function reject(Model $record, SuperAdmin $actor, string $reason): Model
    {
        $this->assertSupportedModel($record);
        $this->assertStatus($record, self::STATUS_PENDING_VALIDATION, 'reject');

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new TaxRateValidationException('A rejection reason is required.');
        }

        $previous = $this->snapshot($record);

        $record->forceFill([
            'status' => self::STATUS_DRAFT,
            'rejection_reason' => $trimmedReason,
        ])->save();

        $this->log(
            $record,
            TaxRateChangeLog::ACTION_REJECTED,
            $actor->id,
            'super_admin',
            $previous,
            $this->snapshot($record),
            $trimmedReason,
        );

        $this->dispatchRejected($record, $trimmedReason);

        return $record->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(Model $record): array
    {
        $this->assertSupportedModel($record);

        /** @var list<TaxRateChangeLog> $entries */
        $entries = TaxRateChangeLog::query()
            ->forRecord($record->getTable(), $this->recordId($record))
            ->orderByDesc('id')
            ->get()
            ->all();

        /** @var list<array<string, mixed>> $result */
        $result = array_map(
            static fn (TaxRateChangeLog $entry): array => $entry->toArray(),
            $entries,
        );

        return $result;
    }

    private function supersedePredecessor(Model $record, int $actorId): void
    {
        if ($record instanceof TaxSlab) {
            $predecessors = TaxSlab::query()
                ->where('company_id', $record->company_id)
                ->forCountry($record->country_code)
                ->where('min_amount', $record->min_amount)
                ->where('id', '!=', $record->id)
                ->active()
                ->get();

            foreach ($predecessors as $predecessor) {
                $before = $this->snapshot($predecessor);
                $predecessor->forceFill(['status' => self::STATUS_SUPERSEDED])->save();
                $this->log(
                    $predecessor,
                    TaxRateChangeLog::ACTION_SUPERSEDED,
                    $actorId,
                    'super_admin',
                    $before,
                    $this->snapshot($predecessor),
                    'Remplacée par le barème #'.$record->id,
                );
            }

            return;
        }

        if ($record instanceof SocialContribution) {
            $predecessors = SocialContribution::query()
                ->where('company_id', $record->company_id)
                ->forCountry($record->country_code)
                ->where('code', $record->code)
                ->where('id', '!=', $record->id)
                ->active()
                ->get();

            foreach ($predecessors as $predecessor) {
                $before = $this->snapshot($predecessor);
                $predecessor->forceFill(['status' => self::STATUS_SUPERSEDED])->save();
                $this->log(
                    $predecessor,
                    TaxRateChangeLog::ACTION_SUPERSEDED,
                    $actorId,
                    'super_admin',
                    $before,
                    $this->snapshot($predecessor),
                    'Remplacée par la cotisation #'.$record->id,
                );
            }
        }
    }

    private function assertSupportedModel(Model $record): void
    {
        if (! $record instanceof TaxSlab && ! $record instanceof SocialContribution) {
            throw new TaxRateValidationException(sprintf(
                'Unsupported model "%s" for tax rate validation workflow.',
                $record::class,
            ));
        }
    }

    private function assertStatus(Model $record, string $expected, string $action): void
    {
        /** @var string $current */
        $current = $record->getAttribute('status');

        if ($current !== $expected) {
            throw new TaxRateValidationException(sprintf(
                'Cannot %s a record in status "%s" (expected "%s").',
                $action,
                $current,
                $expected,
            ));
        }
    }

    private function recordId(Model $record): int
    {
        $key = $record->getKey();

        return is_numeric($key) ? (int) $key : 0;
    }

    /**
     * @param  array<string, mixed>|null  $previousValue
     * @param  array<string, mixed>  $newValue
     */
    private function log(
        Model $record,
        string $action,
        int $actorId,
        string $actorRole,
        ?array $previousValue,
        array $newValue,
        ?string $reason,
    ): void {
        TaxRateChangeLog::query()->create([
            'table_name' => $record->getTable(),
            'record_id' => $this->recordId($record),
            'action' => $action,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $record): array
    {
        $snapshot = [];

        foreach ($record->getAttributes() as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if ($value instanceof Carbon) {
                $snapshot[$key] = $value->toIso8601String();
            } else {
                $snapshot[$key] = $value;
            }
        }

        return $snapshot;
    }

    private function dispatchSubmitted(Model $record): void
    {
        if ($record instanceof TaxSlab) {
            TaxSlabSubmittedForValidation::dispatch($record, (int) $record->submitted_by);
        } elseif ($record instanceof SocialContribution) {
            SocialContributionSubmittedForValidation::dispatch($record, (int) $record->submitted_by);
        }
    }

    private function dispatchApproved(Model $record): void
    {
        if ($record instanceof TaxSlab) {
            TaxSlabApproved::dispatch($record, (int) $record->submitted_by, (int) $record->validated_by);
        } elseif ($record instanceof SocialContribution) {
            SocialContributionApproved::dispatch($record, (int) $record->submitted_by, (int) $record->validated_by);
        }
    }

    private function dispatchRejected(Model $record, string $reason): void
    {
        if ($record instanceof TaxSlab) {
            TaxSlabRejected::dispatch($record, (int) $record->submitted_by, $reason);
        } elseif ($record instanceof SocialContribution) {
            SocialContributionRejected::dispatch($record, (int) $record->submitted_by, $reason);
        }
    }
}
