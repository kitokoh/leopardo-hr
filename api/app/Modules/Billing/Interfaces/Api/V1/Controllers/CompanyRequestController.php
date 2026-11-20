<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CompanyRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        $requests = $user->companyRequests()
            ->latest()
            ->get()
            ->map(fn (CompanyRequest $r) => [
                'id' => $r->id,
                'company_name' => $r->company_name,
                'sector' => $r->sector,
                'country' => $r->country,
                'city' => $r->city,
                'email' => $r->email,
                'status' => $r->status,
                'admin_notes' => $r->admin_notes,
                'reviewed_at' => $r->reviewed_at?->toIso8601String(),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return new JsonResponse(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        // Les bornes de validation doivent refléter le schéma réel, sinon une
        // valeur acceptée ici explose en 500 au moment de l'INSERT :
        //   company_requests.company_name  -> varchar(100)
        //   company_requests.country       -> char(2)  (code ISO-3166 alpha-2)
        //   company_requests.phone         -> varchar(255)
        // `country` était validé en `max:100` : "Algeria" (7 car.) passait la
        // validation puis déclenchait SQLSTATE[22001] value too long for
        // type character(2). Le contrôleur d'intégration, lui, tronque déjà
        // à 2 caractères — c'est bien `store()` qui divergeait.
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
            'sector' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($validated['country'])) {
            $validated['country'] = strtoupper($validated['country']);
        }

        $user = $this->resolveRequestUser($request);

        $pending = $user->companyRequests()->where('status', 'pending')->count();
        if ($pending >= 3) {
            return new JsonResponse([
                'error' => 'TOO_MANY_PENDING_REQUESTS',
                'message' => __('errors.TOO_MANY_PENDING_REQUESTS'),
            ], 422);
        }

        $payload = $validated;
        /** @var Employee $employee */
        $employee = $request->user();
        if ($employee instanceof Employee) {
            $payload['employee_id'] = $employee->id;
        }

        if (Schema::hasColumn('company_requests', 'manager_name')) {
            $payload['manager_name'] = $user->fullName();
        }

        if (Schema::hasColumn('company_requests', 'manager_phone') && ! empty($validated['phone'])) {
            $payload['manager_phone'] = $validated['phone'];
        }

        if (Schema::hasColumn('company_requests', 'notes') && ! empty($validated['description'])) {
            $payload['notes'] = $validated['description'];
        }

        $companyRequest = $user->companyRequests()->create($payload);

        return new JsonResponse([
            'data' => [
                'id' => $companyRequest->id,
                'company_name' => $companyRequest->company_name,
                'status' => $companyRequest->status,
                'created_at' => $companyRequest->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        $companyRequest = $user->companyRequests()->findOrFail($id);

        return new JsonResponse([
            'data' => [
                'id' => $companyRequest->id,
                'company_name' => $companyRequest->company_name,
                'sector' => $companyRequest->sector,
                'country' => $companyRequest->country,
                'city' => $companyRequest->city,
                'email' => $companyRequest->email,
                'phone' => $companyRequest->phone,
                'description' => $companyRequest->description,
                'status' => $companyRequest->status,
                'admin_notes' => $companyRequest->admin_notes,
                'reviewed_at' => $companyRequest->reviewed_at?->toIso8601String(),
                'created_at' => $companyRequest->created_at?->toIso8601String(),
            ],
        ]);
    }

    private function resolveRequestUser(Request $request): User
    {
        $user = $request->user('user_api');
        if ($user instanceof User) {
            return $user;
        }

        /** @var Employee $employee */
        $employee = $request->user();
        if ($employee instanceof Employee) {
            try {
                // #4978 : savepoint — le 23505 attendu (course firstOrCreate)
                // est rollbacké localement au lieu d'abandonner la transaction.
                $user = DB::transaction(fn (): User => User::firstOrCreate(
                    ['email' => $employee->email],
                    [
                        'first_name' => $employee->first_name,
                        'last_name' => $employee->last_name,
                        'password_hash' => $employee->password_hash ?: Hash::make(str()->random(32)),
                        'provider' => 'employee',
                        'preferred_language' => $employee->preferred_language ?? 'fr',
                    ]
                ));
            } catch (QueryException $e) {
                // Issue #3811 : firstOrCreate n'est PAS atomique — deux requêtes
                // concurrentes sur le même email (employé et user plateforme)
                // violent l'index unique users.email → 23505. Le gagnant a créé
                // la ligne : on la récupère (jamais de 500, jamais de doublon).
                // Pattern 23505, cf. PartnerService #3238.
                if ($e->getCode() === '23505') {
                    Log::warning("User firstOrCreate race on email {$employee->email} — fetching winner row.");

                    $user = User::where('email', $employee->email)->firstOrFail();
                } else {
                    throw $e;
                }
            }

            // Issue #3597 : status non mass-assignable — assignation explicite.
            $user->status = $employee->status ?? 'active';
            $user->save();

            return $user;
        }

        abort(401);
    }
}
