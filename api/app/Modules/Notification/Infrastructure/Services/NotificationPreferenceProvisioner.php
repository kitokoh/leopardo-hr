<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
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
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $this->resyncAfterUniqueViolation($employee, $exception);
        }
    }

    /**
     * Recharge la ligne réellement présente en base puis l'aligne sur les
     * valeurs par défaut de l'employé.
     *
     * QA onboarding 2026-09-14 : cette branche levait auparavant un
     * \RuntimeException « Préférence de notification introuvable … après
     * violation d'unicité » qui ÉCRASAIT l'exception d'origine. Vu en dev au
     * démarrage du conteneur : le message désignait le provisioner alors que la
     * base renvoyait une erreur transitoire du pooler, et la cause réelle était
     * perdue pour l'exploitant. L'erreur SQL d'origine est désormais conservée
     * comme « previous » et le contexte est journalisé.
     */
    private function resyncAfterUniqueViolation(Employee $employee, QueryException $uniqueViolation): void
    {
        $existing = NotificationPreference::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->first();

        if (! $existing instanceof NotificationPreference) {
            Log::error('notification.preference.conflict_without_row', [
                'employee_id' => (string) $employee->id,
                'company_id' => (string) $employee->company_id,
                'sqlstate' => (string) $uniqueViolation->getCode(),
            ]);

            throw new \RuntimeException(sprintf(
                'Préférence de notification introuvable pour employee_id=%s après violation d’unicité (%s).',
                (string) $employee->id,
                (string) $uniqueViolation->getMessage()
            ), 0, $uniqueViolation);
        }

        $this->applyDefaults($existing, $employee);
        $existing->save();
    }

    /**
     * Vrai UNIQUEMENT pour une vraie violation d'unicité.
     *
     * QA onboarding 2026-09-14 : `23000` est la classe SQLSTATE GÉNÉRIQUE des
     * violations d'intégrité — elle couvre aussi les clés étrangères, les NOT
     * NULL et les CHECK. La traiter comme « la ligne existe déjà » faisait
     * absorber n'importe quelle erreur d'intégrité, puis échouer sur une
     * relecture forcément vide (d'où le message trompeur observé au démarrage
     * du conteneur en dev). Seule une vraie violation d'unicité est absorbable ;
     * tout le reste remonte tel quel.
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $message = $exception->getMessage();

        if ($sqlState === '23505') {
            return true;
        }

        if ($sqlState === '23000') {
            // MySQL/MariaDB (suites de tests) : « Duplicate entry ... for key ... ».
            return str_contains($message, 'Duplicate entry');
        }

        return false;
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
