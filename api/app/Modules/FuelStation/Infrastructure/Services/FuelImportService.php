<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Import/export sécurisé FuelStation (FUEL-018, issue #5812).
 *
 * Import : preview → commit/cancel. Le preview ne touche JAMAIS les tables
 * cibles ; le commit est idempotent (claim atomique previewed → committing,
 * rejeu d'un commit déjà fait → état existant) ; en cas d'erreur ligne, la
 * session passe `failed` et RIEN n'est persisté (rollback logique : les
 * inserts sont effectués dans une transaction, annulée au premier échec).
 * Limites : taille de fichier et nombre de lignes bornés côté Request.
 *
 * Export : CSV contrôlé généré depuis les snapshots de reporting (FUEL-017),
 * borné en lignes, sans PII, URL signée (contrôleur).
 */
final class FuelImportService
{
    /**
     * Analyse un CSV uploadé : validation ligne par ligne, aucun effet.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $headers
     * @return array{import: FuelImport, errors: list<array<string, mixed>>}
     */
    public function preview(Employee $actor, string $entityType, string $filename, array $rows, array $headers): array
    {
        $errors = [];
        $validRows = [];
        $total = count($rows);

        foreach ($rows as $index => $row) {
            $line = $index + 2; // en-tête = ligne 1
            $error = $this->validateRow($entityType, $row, $headers);

            if ($error !== null) {
                $errors[] = ['line' => $line, 'error' => $error];
            } else {
                $validRows[] = $row;
            }
        }

        $import = FuelImport::query()->create([
            'company_id' => $actor->company_id,
            'entity_type' => $entityType,
            'filename' => $filename,
            'status' => FuelImport::STATUS_PREVIEWED,
            'total_rows' => $total,
            'valid_rows' => count($validRows),
            'error_rows' => count($errors),
            'columns' => $headers,
            'preview_data' => array_slice($validRows, 0, 50),
            'errors' => array_slice($errors, 0, 100),
            'raw_rows' => $validRows,
            'created_by' => $actor->id,
        ]);

        return ['import' => $import, 'errors' => $errors];
    }

    /**
     * Commit d'un import previewed : persiste les lignes validées dans une
     * transaction (rollback logique au premier échec). Idempotent.
     */
    public function commit(FuelImport $import, Employee $actor): FuelImport
    {
        if ($import->status === FuelImport::STATUS_COMMITTED) {
            return $import->refresh();
        }

        if ($import->status !== FuelImport::STATUS_PREVIEWED) {
            abort(422, 'IMPORT_NOT_PREVIEWED');
        }

        $claimed = DB::table('fuel_imports')
            ->where('id', $import->id)
            ->where('status', FuelImport::STATUS_PREVIEWED)
            ->update([
                'status' => FuelImport::STATUS_COMMITTING,
                'committed_by' => $actor->id,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return $import->refresh();
        }

        $rows = is_array($import->raw_rows) ? $import->raw_rows : [];
        $created = 0;

        try {
            DB::transaction(function () use ($import, $rows, &$created): void {
                foreach ($rows as $row) {
                    if (is_array($row) && $this->isStringKeyed($row)) {
                        $this->persistRow($import->entity_type, (string) $import->company_id, $row);
                        $created++;
                    }
                }
            });

            $import->update([
                'status' => FuelImport::STATUS_COMMITTED,
                'committed_at' => Carbon::now('UTC'),
                'result' => ['created' => $created],
            ]);
        } catch (\Throwable $e) {
            // Rollback logique : la transaction a déjà tout annulé.
            $import->update([
                'status' => FuelImport::STATUS_FAILED,
                'result' => ['error' => substr($e->getMessage(), 0, 500)],
            ]);

            throw $e;
        }

        return $import->refresh();
    }

    public function cancel(FuelImport $import, Employee $actor): FuelImport
    {
        if (! in_array($import->status, [FuelImport::STATUS_PREVIEWED, FuelImport::STATUS_COMMITTING], true)) {
            abort(422, 'IMPORT_NOT_CANCELLABLE');
        }

        $import->update([
            'status' => FuelImport::STATUS_CANCELLED,
            'cancelled_by' => $actor->id,
            'cancelled_at' => Carbon::now('UTC'),
        ]);

        return $import->refresh();
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $row
     */
    private function validateRow(string $entityType, array $row, array $headers): ?string
    {
        if (count($row) !== count($headers)) {
            return 'Nombre de colonnes invalide';
        }

        return match ($entityType) {
            FuelImport::ENTITY_PRODUCTS => $this->validateProduct($row),
            FuelImport::ENTITY_PUMPS => $this->validatePump($row),
            FuelImport::ENTITY_TANKS => $this->validateTank($row),
            FuelImport::ENTITY_SHIFTS => $this->validateShift($row),
            FuelImport::ENTITY_READINGS => $this->validateReading($row),
            default => 'Type d\'import inconnu',
        };
    }

    /** @param  array<string, mixed>  $row */
    private function validateProduct(array $row): ?string
    {
        return ($row['code'] ?? '') === '' || ($row['name'] ?? '') === ''
            ? 'code et name requis'
            : null;
    }

    /** @param  array<string, mixed>  $row */
    private function validatePump(array $row): ?string
    {
        return ($row['code'] ?? '') === ''
            ? 'code requis'
            : null;
    }

    /** @param  array<string, mixed>  $row */
    private function validateTank(array $row): ?string
    {
        if (($row['code'] ?? '') === '' || ($row['product_type'] ?? '') === '') {
            return 'code et product_type requis';
        }

        return isset($row['capacity_minor']) && ! is_numeric($row['capacity_minor'])
            ? 'capacity_minor doit être numérique'
            : null;
    }

    /** @param  array<string, mixed>  $row */
    private function validateShift(array $row): ?string
    {
        if (($row['name'] ?? '') === '' || ($row['start_time'] ?? '') === '' || ($row['end_time'] ?? '') === '') {
            return 'name, start_time et end_time requis';
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    private function validateReading(array $row): ?string
    {
        if (($row['meter_id'] ?? '') === '' || ($row['reading_value_minor'] ?? '') === '') {
            return 'meter_id et reading_value_minor requis';
        }

        return isset($row['reading_value_minor']) && ! is_numeric($row['reading_value_minor'])
            ? 'reading_value_minor doit être numérique'
            : null;
    }

    /**
     * @param  array<mixed>  $row
     */
    private function persistRow(string $entityType, string $companyId, array $row): void
    {
        match ($entityType) {
            FuelImport::ENTITY_PRODUCTS => FuelProduct::query()->create([
                'company_id' => $companyId,
                'code' => $this->str($row['code'] ?? ''),
                'name' => $this->str($row['name'] ?? ''),
                'unit_code' => $this->str($row['unit_code'] ?? 'l'),
                'status' => FuelProduct::STATUS_ACTIVE,
            ]),
            FuelImport::ENTITY_PUMPS => FuelPump::query()->create([
                'company_id' => $companyId,
                'station_id' => $this->int($row['station_id'] ?? null),
                'code' => $this->str($row['code'] ?? ''),
                'product_types' => $this->str($row['product_types'] ?? '') !== ''
                    ? explode(',', $this->str($row['product_types']))
                    : [],
                'status' => FuelPump::STATUS_ACTIVE,
            ]),
            FuelImport::ENTITY_TANKS => FuelTank::query()->create([
                'company_id' => $companyId,
                'station_id' => $this->int($row['station_id'] ?? null),
                'code' => $this->str($row['code'] ?? ''),
                'product_type' => $this->str($row['product_type'] ?? ''),
                'capacity_minor' => $this->int($row['capacity_minor'] ?? 0),
                'current_level_minor' => $this->int($row['current_level_minor'] ?? 0),
                'status' => FuelTank::STATUS_ACTIVE,
            ]),
            FuelImport::ENTITY_SHIFTS => FuelShift::query()->create([
                'company_id' => $companyId,
                'station_id' => $this->int($row['station_id'] ?? null),
                'name' => $this->str($row['name'] ?? ''),
                'start_time' => $this->str($row['start_time'] ?? ''),
                'end_time' => $this->str($row['end_time'] ?? ''),
                'status' => FuelShift::STATUS_ACTIVE,
            ]),
            FuelImport::ENTITY_READINGS => throw new \RuntimeException(
                'Import de relevés non supporté — utiliser l\'API de relevés idempotente (FUEL-004).'
            ),
            default => null,
        };
    }

    /** @param  array<mixed>  $row */
    private function isStringKeyed(array $row): bool
    {
        foreach (array_keys($row) as $key) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    private function str(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
