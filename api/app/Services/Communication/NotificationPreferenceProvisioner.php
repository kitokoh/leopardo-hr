<?php

namespace App\Services\Communication;

use App\Models\Employee;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Schema;

class NotificationPreferenceProvisioner
{
    public function ensureForEmployee(Employee $employee): NotificationPreference
    {
        $preference = NotificationPreference::query()->firstOrNew([
            'employee_id' => $employee->id,
        ]);

        $this->applyDefaults($preference, $employee);

        if ($preference->exists === false || $preference->isDirty()) {
            $preference->save();
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

                    $preference = NotificationPreference::query()->firstOrNew([
                        'employee_id' => $employee->id,
                    ]);
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

                    $preference->save();
                    $stats[$wasExisting ? 'updated' : 'created']++;
                }
            });

        return $stats;
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
