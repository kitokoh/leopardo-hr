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
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;

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
            FuelImport::ENTITY_READINGS => 'Import de relevés non supporté — utiliser l\'API de relevés idempotente (FUEL-004)',
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
        if (($row['code'] ?? '') === '') {
            return 'code requis';
        }

        return ($row['station_id'] ?? '') === '' || ! is_numeric($row['station_id'])
            ? 'station_id requis (numérique)'
            : null;
    }

    /** @param  array<string, mixed>  $row */
    private function validateTank(array $row): ?string
    {
        if (($row['code'] ?? '') === '' || ($row['product_type'] ?? '') === '') {
            return 'code et product_type requis';
        }

        if (($row['station_id'] ?? '') === '' || ! is_numeric($row['station_id'])) {
            return 'station_id requis (numérique)';
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

        return ($row['station_id'] ?? '') === '' || ! is_numeric($row['station_id'])
            ? 'station_id requis (numérique)'
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

    public function importCsv(
        Employee $actor,
        string $importType,
        UploadedFile $file,
        bool $dryRun,
    ): array {
        if (! in_array($importType, FuelImport::TYPES, true)) {
            throw new \InvalidArgumentException('Type d\'import inconnu.');
        }

        if ($file->getSize() > FuelImport::MAX_FILE_BYTES) {
            throw new \InvalidArgumentException('Fichier trop volumineux (max 2 Mo).');
        }

        $rows = $this->parseCsv($file);

        if (count($rows) > FuelImport::MAX_LINES) {
            throw new \InvalidArgumentException('Trop de lignes (max 5 000).');
        }

        $results = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $lineNumber => $row) {
            $lineNo = $lineNumber + 2; // +2 : en-tête + base 1
            $validation = $this->validateRow($actor, $importType, $row);

            if ($validation['valid']) {
                $valid[] = ['line' => $lineNo, 'data' => $validation['data']];
                $results[] = ['line' => $lineNo, 'status' => 'ok'];
            } else {
                $errors[] = ['line' => $lineNo, 'errors' => $validation['errors']];
                $results[] = ['line' => $lineNo, 'status' => 'error', 'errors' => $validation['errors']];
            }
        }

        if ($errors !== []) {
            /** @var FuelImport $import */
            $import = FuelImport::query()->create([
                'company_id' => $actor->company_id,
                'import_type' => $importType,
                'filename' => $file->getClientOriginalName(),
                'status' => FuelImport::STATUS_FAILED,
                'total_lines' => count($rows),
                'valid_lines' => count($valid),
                'error_lines' => count($errors),
                'errors' => $errors,
                'imported_by' => $actor->id,
            ]);

            return ['import' => $import, 'preview' => $results, 'applied' => false];
        }

        if ($dryRun) {
            /** @var FuelImport $import */
            $import = FuelImport::query()->create([
                'company_id' => $actor->company_id,
                'import_type' => $importType,
                'filename' => $file->getClientOriginalName(),
                'status' => FuelImport::STATUS_VALIDATED,
                'total_lines' => count($rows),
                'valid_lines' => count($valid),
                'error_lines' => 0,
                'errors' => null,
                'imported_by' => $actor->id,
            ]);

            return ['import' => $import, 'preview' => $results, 'applied' => false];
        }

        DB::transaction(function () use ($actor, $importType, $valid): void {
            foreach ($valid as $entry) {
                $this->applyRow($actor, $importType, $entry['data']);
            }
        });

        /** @var FuelImport $import */
        $import = FuelImport::query()->create([
            'company_id' => $actor->company_id,
            'import_type' => $importType,
            'filename' => $file->getClientOriginalName(),
            'status' => FuelImport::STATUS_COMPLETED,
            'total_lines' => count($rows),
            'valid_lines' => count($valid),
            'error_lines' => 0,
            'errors' => null,
            'imported_by' => $actor->id,
        ]);

        return ['import' => $import, 'preview' => $results, 'applied' => true];
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath() ?: '', 'r');

        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier CSV.');
        }

        $header = fgetcsv($handle, 0, ',', '"');

        if ($header === false || $header === [null]) {
            fclose($handle);

            throw new \RuntimeException('CSV vide ou en-tête manquant.');
        }

        $normalizedHeader = array_map(static fn (string $h): string => trim($h), $header);

        $rows = [];

        while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
            if ($line === [null] || trim(implode('', $line)) === '') {
                continue;
            }

            $assoc = [];

            foreach ($normalizedHeader as $index => $column) {
                $assoc[$column] = trim((string) ($line[$index] ?? ''));
            }

            $rows[] = $assoc;
        }

        fclose($handle);

        return $rows;
    }

    private function applyRow(Employee $actor, string $importType, array $data): void
    {
        switch ($importType) {
            case FuelImport::TYPE_PRODUCTS:
                FuelProduct::query()->create([
                    'company_id' => $actor->company_id,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'unit_code' => $data['unit_code'] ?? 'l',
                    'status' => $data['status'],
                ]);

                break;

            case FuelImport::TYPE_EQUIPMENT:
                $this->applyEquipment($actor, $data);

                break;

            case FuelImport::TYPE_SHIFTS:
                $station = $this->stationInTenant($actor, (string) $data['station_id']);

                if ($station instanceof FuelStation) {
                    FuelShift::query()->create([
                        'company_id' => $actor->company_id,
                        'station_id' => $station->id,
                        'name' => $data['name'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'status' => $data['status'] ?? FuelShift::STATUS_ACTIVE,
                        'created_by' => $actor->id,
                    ]);
                }

                break;

            case FuelImport::TYPE_READINGS:
                $meter = $this->meterInTenant($actor, (string) $data['station_id'], (string) $data['pump_code'], (string) $data['meter_code']);

                if ($meter instanceof FuelMeterRegister) {
                    FuelMeterReading::query()->create([
                        'company_id' => $actor->company_id,
                        'station_id' => $meter->station_id,
                        'pump_id' => $meter->pump_id,
                        'meter_id' => $meter->id,
                        'reading_value_minor' => (int) $data['reading_value_minor'],
                        'reading_unit' => $meter->unit_code,
                        'captured_at_utc' => $data['captured_at_utc'],
                        'captured_at_station_local' => $data['captured_at_utc'],
                        'source_code' => FuelMeterReading::SOURCE_IMPORT,
                        'idempotency_key' => "import:{$data['idempotency_key']}",
                        'status' => FuelMeterReading::STATUS_SUBMITTED,
                    ]);
                }

                break;
        }
    }

    private function applyEquipment(Employee $actor, array $data): void
    {
        $station = $this->stationInTenant($actor, (string) $data['station_id']);

        if (! $station instanceof FuelStation) {
            return;
        }

        $type = $data['equipment_type'];

        if ($type === 'pump') {
            FuelPump::query()->create([
                'company_id' => $actor->company_id,
                'station_id' => $station->id,
                'code' => $data['code'],
                'product_types' => array_values(array_filter(array_map('trim', explode('|', (string) ($data['product_types'] ?? ''))))),
                'status' => $data['status'] ?? FuelPump::STATUS_ACTIVE,
            ]);

            return;
        }

        if ($type === 'tank') {
            FuelTank::query()->create([
                'company_id' => $actor->company_id,
                'station_id' => $station->id,
                'code' => $data['code'],
                'product_type' => $data['product_type'] ?? '',
                'capacity_minor' => (int) ($data['capacity_minor'] ?? 0),
                'current_level_minor' => isset($data['current_level_minor']) && $data['current_level_minor'] !== '' ? (int) $data['current_level_minor'] : null,
                'status' => $data['status'] ?? FuelTank::STATUS_ACTIVE,
            ]);

            return;
        }

        if ($type === 'meter') {
            $pump = FuelPump::query()
                ->where('company_id', $actor->company_id)
                ->where('station_id', $station->id)
                ->where('code', $data['pump_code'] ?? '')
                ->first();

            if ($pump instanceof FuelPump) {
                FuelMeterRegister::query()->create([
                    'company_id' => $actor->company_id,
                    'station_id' => $station->id,
                    'pump_id' => $pump->id,
                    'meter_code' => $data['code'],
                    'meter_type' => $data['meter_type'] ?? FuelMeterRegister::TYPE_MECHANICAL,
                    'product_code' => $data['product_code'] ?? '',
                    'unit_code' => $data['unit_code'] ?? 'l',
                    'precision_scale' => isset($data['precision_scale']) && $data['precision_scale'] !== '' ? (int) $data['precision_scale'] : 2,
                    'status' => $data['status'] ?? FuelMeterRegister::STATUS_ACTIVE,
                ]);
            }
        }
    }


    private function stationInTenant(Employee $actor, string $stationId): ?FuelStation
    {
        if (! ctype_digit($stationId)) {
            return null;
        }

        /** @var FuelStation|null $station */
        $station = FuelStation::query()
            ->where('company_id', $actor->company_id)
            ->find((int) $stationId);

        return $station;
    }


    private function meterInTenant(Employee $actor, string $stationId, string $pumpCode, string $meterCode): ?FuelMeterRegister
    {
        $station = $this->stationInTenant($actor, $stationId);

        if (! $station instanceof FuelStation) {
            return null;
        }

        $pump = FuelPump::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->where('code', $pumpCode)
            ->first();

        if (! $pump instanceof FuelPump) {
            return null;
        }

        /** @var FuelMeterRegister|null $meter */
        $meter = FuelMeterRegister::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->where('pump_id', $pump->id)
            ->where('meter_code', $meterCode)
            ->first();

        return $meter;
    }
}