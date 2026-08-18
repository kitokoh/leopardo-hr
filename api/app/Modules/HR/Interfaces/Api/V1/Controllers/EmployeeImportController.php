<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmployeeImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));

        if (count($lines) < 2) {
            return response()->json([
                'message' => __('errors.CSV_HEADER_REQUIRED'),
            ], 422);
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $requiredHeaders = ['first_name', 'last_name', 'email'];
        $missing = array_diff($requiredHeaders, $headers);

        if (! empty($missing)) {
            return response()->json([
                'message' => __('errors.CSV_MISSING_COLUMNS', ['columns' => implode(', ', $missing)]),
                'required_columns' => $requiredHeaders,
                'found_columns' => $headers,
            ], 422);
        }

        $allowedColumns = [
            'first_name', 'last_name', 'email', 'phone',
            'national_id', 'date_of_birth', 'gender',
            'address_line', 'postal_code', 'nationality',
            'contract_start', 'contract_type', 'status',
        ];

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $companyId = $actor->company_id;

        DB::beginTransaction();

        try {
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $values = str_getcsv($line);
                if (count($values) !== count($headers)) {
                    $errors[] = ['line' => $index + 2, 'error' => 'Nombre de colonnes incorrect'];
                    $skipped++;

                    continue;
                }

                $row = array_combine($headers, $values);
                $row = array_map('trim', $row);

                $rowValidator = Validator::make($row, [
                    'first_name' => 'required|string|max:100',
                    'last_name' => 'required|string|max:100',
                    'email' => 'required|email|max:150',
                    'phone' => 'nullable|string|max:30',
                    'national_id' => 'nullable|string|max:50',
                    'date_of_birth' => 'nullable|date',
                    'gender' => 'nullable|in:M,F',
                    'nationality' => 'nullable|string|size:2',
                    'address_line' => 'nullable|string|max:255',
                    'postal_code' => 'nullable|string|max:20',
                    'contract_start' => 'nullable|date',
                    'contract_type' => 'nullable|in:CDI,CDD,Stage,Interim,Consultant',
                    'status' => 'nullable|in:active,suspended,archived',
                ]);

                if ($rowValidator->fails()) {
                    $errors[] = [
                        'line' => $index + 2,
                        'error' => $rowValidator->errors()->first(),
                    ];
                    $skipped++;

                    continue;
                }

                $exists = Employee::where('company_id', $companyId)
                    ->where('email', $row['email'])
                    ->exists();

                if ($exists) {
                    $errors[] = ['line' => $index + 2, 'error' => "Email {$row['email']} existe deja"];
                    $skipped++;

                    continue;
                }

                $fillData = [];
                foreach ($allowedColumns as $col) {
                    if (isset($row[$col]) && $row[$col] !== '') {
                        $fillData[$col] = $row[$col];
                    }
                }

                // Sensitive fields extracted — not mass-assignable (#3597)
                $status = $fillData['status'] ?? 'active';
                unset($fillData['status']);

                // Issue #4947 : `password_hash` est NOT NULL sans défaut dans le
                // schéma tenant — `Employee::create($fillData)` sans password_hash
                // échouait en 500 (SQLSTATE 23502) avant l'assignation explicite.
                // On persiste en un seul INSERT via forceFill (pattern #3677).
                // Issue #4947 (suite) : PostgreSQL ABORTE la transaction courante
                // dès qu'une requête échoue, même catchée — après un 23505, le
                // `continue` laissait la transaction morte (25P02) et le commit
                // final ne persistait RIEN. Chaque ligne est insérée dans un
                // `DB::transaction` imbriqué = SAVEPOINT Postgres : un conflit
                // n'abîme que la ligne courante, les suivantes restent valides.
                /** @var array<string, mixed> $fillData */
                $passwordHash = Hash::make(Str::random(32));

                try {
                    DB::transaction(function () use ($fillData, $companyId, $status, $passwordHash): void {
                        $employee = new Employee;
                        $employee->forceFill(array_merge($fillData, [
                            'company_id' => $companyId,
                            'status' => $status,
                            'password_hash' => $passwordHash,
                        ]));
                        $employee->save();
                    });
                    $imported++;
                } catch (QueryException $e) {
                    // Issue #3726 : course entre le check exists() (ligne 110) et
                    // le create() — un import concurrent (ou un doublon arrivé
                    // entre les deux) viole l'index unique global employees(email)
                    // (migration enforce_global_unique_email_on_employees).
                    // 23505 = SQLSTATE unique_violation (pattern PartnerService,
                    // PayrollService #3238) : on signale la ligne, on continue —
                    // jamais de 500, jamais de rollback global.
                    if ($e->getCode() === '23505') {
                        $errors[] = [
                            'line' => $index + 2,
                            'error' => "Email {$row['email']} existe deja (doublon concurrent)",
                        ];
                        $skipped++;

                        continue;
                    }

                    throw $e;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('hr.employee_import.failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('errors.CSV_IMPORT_FAILED'),
                'error' => 'EMPLOYEE_IMPORT_FAILED',
            ], 500);
        }

        return response()->json([
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 50),
                'total_lines' => $imported + $skipped,
            ],
        ], $imported > 0 ? 201 : 422);
    }

    public function template(): JsonResponse
    {
        $headers = [
            'first_name', 'last_name', 'email', 'phone',
            'national_id', 'date_of_birth', 'gender',
            'address_line', 'postal_code', 'nationality',
            'contract_start', 'contract_type', 'status',
        ];
        $example = [
            'Jean', 'Dupont', 'jean.dupont@example.com', '+213555000000',
            'NID123456', '1990-01-15', 'M',
            '12 Rue Didouche Mourad', '16000', 'DZ',
            '2026-01-01', 'CDI', 'active',
        ];

        $csv = implode(',', $headers)."\n".implode(',', $example)."\n";

        return response()->json([
            'data' => [
                'content' => $csv,
                'filename' => 'employee_import_template.csv',
                'columns' => array_map(fn (string $h) => [
                    'name' => $h,
                    'required' => in_array($h, ['first_name', 'last_name', 'email']),
                ], $headers),
            ],
        ]);
    }
}
