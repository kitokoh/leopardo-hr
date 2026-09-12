<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class NotificationPreferenceProvisioner
{
    public function ensureForEmployee(Employee $employee): NotificationPreference
    {
        $preference = $this->findExisting($employee);

        $this->applyDefaults($preference, $employee);

        if ($preference->exists === false || $preference->isDirty()) {
            $this->persist($preference, $employee);
        }

        return $preference;
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function backfill(?string $companyId = null, bool $dryRun = false): array
    {
        if (! Schema::hasTable('notification_preferences')) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        Employee::query()
            ->with('company')
            ->where('status', 'active')
            ->when($companyId !== null && $companyId !== '', fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('id')
            ->chunkById(200, function ($employees) use (&$stats, $dryRun): void {
                foreach ($employees as $employee) {
                    if (! $employee instanceof Employee || ! $employee->company_id) {
                        $stats['skipped']++;

                        continue;
                    }

                    $preference = $this->findExisting($employee);
                    $wasExisting = $preference->exists;
                    $this->applyDefaults($preference, $employee);

                    if ($dryRun) {
                        $stats[$wasExisting ? ($preference->isDirty() ? 'updated' : 'skipped') : 'created']++;

                        continue;
                    }

                    if ($wasExisting && $preference->isDirty() === false) {
                        $stats['skipped']++;

                        continue;
                    }

                    $this->persist($preference, $employee);
                    $stats[$wasExisting ? 'updated' : 'created']++;
                }
            });

        return $stats;
    }

    /**
     * Retrouve la préférence existante par `employee_id`.
     *
     * Le backfill tourne en console (sans contexte société) et la contrainte
     * d'unicité `notification_preferences_employee_id_unique` est globale, pas
     * par société : on interroge donc sans le scope `company` pour ne jamais
     * rater une ligne existante et provoquer un INSERT en doublon (#7227).
     */
    private function findExisting(Employee $employee): NotificationPreference
    {
        $existing = NotificationPreference::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing instanceof NotificationPreference) {
            return $existing;
        }

        return new NotificationPreference(['employee_id' => $employee->id]);
    }

    /**
     * Enregistre la préférence en absorbant la violation d'unicité
     * `employee_id` (ligne créée hors scope ou par un déploiement concurrent) :
     * on recharge alors la ligne réelle et on la met à jour au lieu d'échouer.
     */
    private function persist(NotificationPreference $preference, Employee $employee): void
    {
        try {
            $preference->save();

            return;
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $existing = NotificationPreference::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->first();

        if (! $existing instanceof NotificationPreference) {
            throw new \RuntimeException(sprintf(
                'Préférence de notification introuvable pour employee_id=%s après violation d’unicité.',
                (string) $employee->id
            ));
        }

        $this->applyDefaults($existing, $employee);
        $existing->save();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23505', '23000'], true);
    }

    private function applyDefaults(NotificationPreference $preference, Employee $employee): void
    {
        $defaults = $this->defaultsFor($employee);

        if ($preference->exists === false) {
            $preference->fill(array_merge([
                'company_id' => (string) $employee->company_id,
                'app_enabled' => true,
                'email_enabled' => true,
                'push_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false,
            ], $defaults));

            return;
        }

        if ((string) $preference->company_id !== (string) $employee->company_id) {
            $preference->company_id = (string) $employee->company_id;
        }

        foreach (['locale', 'timezone', 'categories', 'quiet_hours'] as $field) {
            if ($preference->{$field} === null) {
                $preference->{$field} = $defaults[$field];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsFor(Employee $employee): array
    {
        return [
            'locale' => $employee->preferred_language ?: 'fr',
            'timezone' => $employee->company?->timezone ?: config('app.timezone', 'UTC'),
            'categories' => [
                'hr' => true,
                'payroll' => true,
                'attendance' => true,
                'security' => true,
                'system' => true,
                'marketing' => false,
            ],
            'quiet_hours' => [
                'enabled' => false,
                'start' => null,
                'end' => null,
            ],
        ];
    }
}
