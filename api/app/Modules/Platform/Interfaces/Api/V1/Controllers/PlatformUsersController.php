<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cockpit super-admin — liste réelle des utilisateurs plateforme (issue #2184).
 *
 * Agrégat de `shared_tenants.employees` (schéma partagé) joint à
 * `public.companies` pour exposer company_id/company_name — même pattern de
 * lecture cross-tenant que PlatformAdminDashboardController. Alimente la vue
 * Users du SPA admin (remplacement des données mock) et permet l'impersonation
 * réelle (POST /platform/impersonations a besoin de company_id + employee_id).
 */
class PlatformUsersController extends Controller
{
    private const TENANT_SCHEMA = 'shared_tenants';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $search = trim((string) $request->input('search', ''));

        try {
            $query = DB::table(self::TENANT_SCHEMA.'.employees')
                ->leftJoin('public.companies', 'public.companies.id', '=', self::TENANT_SCHEMA.'.employees.company_id')
                ->select(
                    self::TENANT_SCHEMA.'.employees.id as employee_id',
                    self::TENANT_SCHEMA.'.employees.first_name',
                    self::TENANT_SCHEMA.'.employees.last_name',
                    self::TENANT_SCHEMA.'.employees.email',
                    self::TENANT_SCHEMA.'.employees.status',
                    self::TENANT_SCHEMA.'.employees.created_at',
                    'public.companies.id as company_id',
                    'public.companies.name as company_name'
                )
                ->orderByDesc(self::TENANT_SCHEMA.'.employees.created_at');

            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $q->where(self::TENANT_SCHEMA.'.employees.email', 'ilike', "%{$search}%")
                        ->orWhere(self::TENANT_SCHEMA.'.employees.first_name', 'ilike', "%{$search}%")
                        ->orWhere(self::TENANT_SCHEMA.'.employees.last_name', 'ilike', "%{$search}%")
                        ->orWhere('public.companies.name', 'ilike', "%{$search}%");
                });
            }

            $users = $query->paginate($perPage);
        } catch (\Throwable) {
            return response()->json(['data' => [], 'meta' => [
                'current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0,
            ]]);
        }

        /** @var list<object{employee_id: int, first_name: ?string, last_name: ?string, email: ?string, status: ?string, created_at: mixed, company_id: ?string, company_name: ?string}> $rows */
        $rows = $users->items();

        $items = array_map(static fn (object $row): array => [
            'id' => (int) $row->employee_id,
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            'email' => $row->email,
            'status' => $row->status ?? 'active',
            'company_id' => $row->company_id,
            'company_name' => $row->company_name,
            'created_at' => $row->created_at,
        ], $rows);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
