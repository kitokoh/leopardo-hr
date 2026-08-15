<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                'message' => 'Le fichier CSV doit contenir un en-tete et au moins une ligne de donnees.',
            ], 422);
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $requiredHeaders = ['first_name', 'last_name', 'email'];
        $missing = array_diff($requiredHeaders, $headers);

        if (! empty($missing)) {
            return response()->json([
                'message' => 'Colonnes requises manquantes : '.implode(', ', $missing),
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

        // Issue #3726 : pas de transaction globale — sous PostgreSQL, une
        // violation d'unicité (import concurrent / doublon arrivé entre le
        // check et l'insert) empoisonne toute la transaction et forçait un
        // rollback + 500. L'import est par nature à succès partiel (réponse
        // imported/skipped/errors) : chaque ligne est indépendante et la race
        // check-then-create est rattrapée ligne par ligne (SQLSTATE 23505).
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

                $fillData['password_hash'] = Hash::make(Str::random(32));

                try {
                    $employee = Employee::create($fillData);
                    $employee->company_id = $companyId;
                    $employee->status = $status;
                    $employee->save();
                    $imported++;
                } catch (QueryException $e) {
                    if ($e->getCode() === '23505') {
                        // Race check-then-create (#3726) : le doublon est arrivé
                        // entre le exists() et l'insert (import concurrent) —
                        // ligne skippée, jamais 500.
                        $errors[] = ['line' => $index + 2, 'error' => "Email {$row['email']} existe deja"];
                        $skipped++;

                        continue;
                    }

                    throw $e;
                }
            }
        } catch (\Throwable $e) {
            Log::error('hr.employee_import.failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'import.',
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
