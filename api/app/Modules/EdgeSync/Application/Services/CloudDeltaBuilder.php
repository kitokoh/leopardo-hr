<?php

namespace App\Modules\EdgeSync\Application\Services;

use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds a delta payload of Cloud records changed since the last sync.
 * Sent to the Edge node during a pull operation.
 *
 * Only safe, read-only reference data is synced to Edge:
 * - Employees (profile + biometric for local auth)
 * - Departments, Positions, Schedules
 * - Absence types, Leave policies
 *
 * Sensitive financial data (payroll details, salary) stays Cloud-only.
 */
class CloudDeltaBuilder
{
    /**
     * Entities synced from Cloud → Edge.
     * Each entry: table name => [safe columns to include]
     *
     * @var array<string, list<string>>
     */
    protected array $syncableEntities = [
        'employees' => [
            'id', 'company_id', 'first_name', 'last_name', 'email', 'phone',
            'department_id', 'position_id', 'role', 'status',
            'face_encoding', 'biometric_id', 'updated_at',
        ],
        'departments' => [
            'id', 'company_id', 'name', 'code', 'updated_at',
        ],
        'positions' => [
            'id', 'company_id', 'title', 'department_id', 'updated_at',
        ],
        'schedules' => [
            'id', 'company_id', 'name', 'start_time', 'end_time',
            'work_days', 'updated_at',
        ],
        'absence_types' => [
            'id', 'company_id', 'name', 'code', 'paid', 'updated_at',
        ],
    ];

    /**
     * Build delta for a given Edge node since its last sync.
     *
     * @return array{since:string, entities:array<string, list<array<string,mixed>>>}
     */
    public function build(EdgeNode $node): array
    {
        $since = $node->last_sync_at ?? Carbon::createFromTimestamp(0);
        $delta = [
            'since'    => $since->toIso8601String(),
            'entities' => [],
        ];

        foreach ($this->syncableEntities as $table => $columns) {
            try {
                $records = DB::table($table)
                    ->where('company_id', $node->company_id)
                    ->where('updated_at', '>', $since)
                    ->get($columns)
                    ->map(fn ($r) => (array) $r)
                    ->toArray();

                if (! empty($records)) {
                    $delta['entities'][$table] = $records;
                }
            } catch (\Throwable $e) {
                // Table might not exist in all tenant schemas — skip gracefully
                \Illuminate\Support\Facades\Log::warning(
                    "[EdgeSync] CloudDeltaBuilder: could not query {$table}",
                    ['error' => $e->getMessage()]
                );
            }
        }

        return $delta;
    }

    /**
     * Count total records in the delta (for logging/metrics).
     */
    public function countDelta(array $delta): int
    {
        return array_sum(array_map('count', $delta['entities'] ?? []));
    }
}
