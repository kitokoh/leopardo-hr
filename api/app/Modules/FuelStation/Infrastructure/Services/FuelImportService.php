<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * FUEL-018 (#5812) — Import CSV contrôlé (équipements/produits/shifts/
 * relevés historiques).
 *
 * - Validation ligne par ligne (erreurs collectées avec n° de ligne) ;
 * - Limites : 5 000 lignes max, champs ≤ 500 caractères ;
 * - Preview sans effet de bord ; import transactionnel avec rollback logique
 *   (aucune ligne insérée si une ligne est invalide) ;
 * - Audit : trace `fuel_imports` (type, lignes, statut, auteur).
 */
final class FuelImportService
{
    public const MAX_ROWS = 5000;

    public const MAX_FIELD_LENGTH = 500;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{valid: bool, total: int, errors: array<int, array{line: int, message: string}>}
     */
    public function preview(Employee $actor, string $type, array $rows): array
    {
        if (count($rows) > self::MAX_ROWS) {
            return [
                'valid' => false,
                'total' => count($rows),
                'errors' => [['line' => 0, 'message' => 'Trop de lignes : maximum '.self::MAX_ROWS.'.']],
            ];
        }

        $errors = [];
        $valid = true;

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $messages = $this->validateRow($actor, $type, $row);

            if ($messages !== []) {
                $valid = false;
                foreach ($messages as $message) {
                    $errors[] = ['line' => $line, 'message' => $message];
                }
            }
        }

        return ['valid' => $valid, 'total' => count($rows), 'errors' => $errors];
    }

    /**
     * Import transactionnel : aucune insertion si une ligne est invalide.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, type: string, audit_id: int}
     */
    public function import(Employee $actor, string $type, array $rows): array
    {
        $preview = $this->preview($actor, $type, $rows);

        if (! $preview['valid']) {
            throw new RuntimeException('Import refusé : '.count($preview['errors']).' erreur(s) — corriger puis réessayer.');
        }

        return DB::transaction(function () use ($actor, $type, $rows): array {
            $imported = 0;

            foreach ($rows as $row) {
                $this->insertRow($actor, $type, $row);
                $imported++;
            }

            /** @var FuelImport $audit */
            $audit = FuelImport::query()->create([
                'company_id' => $actor->company_id,
                'type' => $type,
                'rows_total' => count($rows),
                'rows_imported' => $imported,
                'status' => 'completed',
                'imported_by_user_id' => (int) $actor->id,
            ]);

            return ['imported' => $imported, 'type' => $type, 'audit_id' => (int) $audit->id];
        });
    }

    /**
     * @return list<string>
     */
    private function validateRow(Employee $actor, string $type, array $row): array
    {
        $messages = [];

        foreach ($row as $key => $value) {
            if (is_string($value) && mb_strlen($value) > self::MAX_FIELD_LENGTH) {
                $messages[] = "Champ '{$key}' trop long (> ".self::MAX_FIELD_LENGTH.' caractères).';
            }
        }

        if ($type === 'products') {
            if (empty($row['code']) || empty($row['name'])) {
                $messages[] = 'code et name sont requis.';
            }
            $unit = (string) ($row['unit_code'] ?? '');
            if ($unit !== '' && ! in_array($unit, ['l', 'kg', 'u', 'pce'], true)) {
                $messages[] = "unit_code invalide ({$unit}).";
            }
        } elseif ($type === 'shifts') {
            if (empty($row['name']) || empty($row['start_time']) || empty($row['end_time'])) {
                $messages[] = 'name, start_time et end_time sont requis.';
            }
            if (! empty($row['station_id']) && $this->stationOutsideTenant($actor, (int) $row['station_id'])) {
                $messages[] = 'station_id hors tenant.';
            }
        } elseif ($type === 'readings') {
            if (empty($row['station_id']) || empty($row['pump_id']) || empty($row['meter_id'])) {
                $messages[] = 'station_id, pump_id et meter_id sont requis.';
            }
            if (! isset($row['reading_value_minor']) || (int) $row['reading_value_minor'] < 0) {
                $messages[] = 'reading_value_minor requis et >= 0.';
            }
            if ($this->stationOutsideTenant($actor, (int) ($row['station_id'] ?? 0))) {
                $messages[] = 'station_id hors tenant.';
            }
        } else {
            $messages[] = "Type d'import inconnu ({$type}).";
        }

        return $messages;
    }

    private function insertRow(Employee $actor, string $type, array $row): void
    {
        if ($type === 'products') {
            FuelProduct::query()->create([
                'company_id' => $actor->company_id,
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'unit_code' => (string) ($row['unit_code'] ?? 'u'),
                'status' => (string) ($row['status'] ?? 'active'),
                'metadata' => $row['metadata'] ?? null,
            ]);
        } elseif ($type === 'shifts') {
            FuelShift::query()->create([
                'company_id' => $actor->company_id,
                'station_id' => (int) $row['station_id'],
                'name' => (string) $row['name'],
                'start_time' => (string) $row['start_time'],
                'end_time' => (string) $row['end_time'],
                'status' => (string) ($row['status'] ?? 'active'),
                'notes' => $row['notes'] ?? null,
                'created_by' => (int) $actor->id,
            ]);
        } elseif ($type === 'readings') {
            FuelMeterReading::query()->create([
                'company_id' => $actor->company_id,
                'station_id' => (int) $row['station_id'],
                'pump_id' => (int) $row['pump_id'],
                'meter_id' => (int) $row['meter_id'],
                'reading_value_minor' => (int) $row['reading_value_minor'],
                'reading_unit' => (string) ($row['reading_unit'] ?? 'centiliter'),
                'captured_at_utc' => $row['captured_at_utc'] ?? now()->toDateTimeString(),
                'captured_at_station_local' => $row['captured_at_station_local'] ?? null,
                'timezone' => $row['timezone'] ?? 'UTC',
                'captured_by_employee_id' => (int) $actor->id,
                'shift_id' => isset($row['shift_id']) ? (int) $row['shift_id'] : null,
                'source_code' => 'import',
            ]);
        }
    }

    private function stationOutsideTenant(Employee $actor, int $stationId): bool
    {
        $table = 'fuel_stations';

        if (! Schema::hasTable($table)) {
            return true;
        }

        return ! \Illuminate\Support\Facades\DB::table($table)
            ->where('id', $stationId)
            ->where('company_id', $actor->company_id)
            ->exists();
    }
}
