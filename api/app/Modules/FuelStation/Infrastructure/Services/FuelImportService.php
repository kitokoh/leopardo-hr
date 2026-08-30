<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Import/export sécurisé FuelStation — FUEL-018 (#5812).
 *
 * - CSV validé ligne à ligne ; limites taille (2 Mo) et lignes (5 000) ;
 * - preview (dry-run) : aucune écriture, erreurs ligne par ligne ;
 * - rollback logique : si une ligne est invalide, AUCUNE écriture ;
 * - permissions manager + audit (imported_by, erreurs) ;
 * - imports tenant-scoped, références (station, pompe, compteur)
 *   résolues dans le tenant uniquement.
 */
final class FuelImportService
{
    /**
     * @return array{import: FuelImport, preview: array<int, array<string, mixed>>, applied: bool}
     */
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

    /**
     * @return list<array<string, string>>
     */
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

    /**
     * @param  array<string, string>  $row
     *
     * @return array{valid: bool, data?: array<string, mixed>, errors?: list<string>}
     */
    private function validateRow(Employee $actor, string $importType, array $row): array
    {
        $errors = [];

        switch ($importType) {
            case FuelImport::TYPE_PRODUCTS:
                if (($row['code'] ?? '') === '') {
                    $errors[] = 'code requis';
                } elseif (FuelProduct::query()->where('company_id', $actor->company_id)->where('code', $row['code'])->exists()) {
                    $errors[] = "code '{$row['code']}' déjà existant";
                }
                if (($row['name'] ?? '') === '') {
                    $errors[] = 'name requis';
                }
                if (! in_array($row['status'] ?? '', FuelProduct::STATUSES, true)) {
                    $errors[] = 'status invalide (active|inactive)';
                }

                break;

            case FuelImport::TYPE_EQUIPMENT:
                $station = $this->stationInTenant($actor, $row['station_id'] ?? '');
                if ($station === null) {
                    $errors[] = 'station_id introuvable dans le tenant';
                }
                if (! in_array($row['equipment_type'] ?? '', ['pump', 'tank', 'meter'], true)) {
                    $errors[] = 'equipment_type invalide (pump|tank|meter)';
                }
                if (($row['code'] ?? '') === '') {
                    $errors[] = 'code requis';
                }

                break;

            case FuelImport::TYPE_SHIFTS:
                if (($row['name'] ?? '') === '') {
                    $errors[] = 'name requis';
                }
                if ($row['start_time'] ?? '' === '' || $row['end_time'] ?? '' === '') {
                    $errors[] = 'start_time/end_time requis';
                }
                if ($this->stationInTenant($actor, $row['station_id'] ?? '') === null) {
                    $errors[] = 'station_id introuvable dans le tenant';
                }

                break;

            case FuelImport::TYPE_READINGS:
                $meter = $this->meterInTenant($actor, $row['station_id'] ?? '', $row['pump_code'] ?? '', $row['meter_code'] ?? '');
                if ($meter === null) {
                    $errors[] = 'station/pump/meter introuvables dans le tenant';
                }
                if (! ctype_digit(($row['reading_value_minor'] ?? ''))) {
                    $errors[] = 'reading_value_minor entier requis';
                }
                if (($row['captured_at_utc'] ?? '') === '') {
                    $errors[] = 'captured_at_utc requis';
                }

                break;
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true, 'data' => $row];
    }

    /**
     * @param  array<string, mixed>  $data
     */
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

    /**
     * @param  array<string, mixed>  $data
     */
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
