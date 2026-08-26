<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\UserEmployeeLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * #5540 — Onboarding personnel multi-statuts.
 *
 * Gère les demandes d'intégration : un utilisateur personnel demande
 * à rejoindre une entreprise existante (type='integration').
 *
 * Côté utilisateur :
 *   POST /user/company-integration-requests
 *   GET  /user/company-integration-requests
 *
 * Côté manager :
 *   GET  /company-integration-requests        (liste des demandes pour son tenant)
 *   POST /company-integration-requests/{id}/accept
 *   POST /company-integration-requests/{id}/reject
 */
class CompanyIntegrationRequestController extends Controller
{
    // ── USER-SIDE ──────────────────────────────────────────────────────────

    /**
     * Soumet une demande d'intégration (rejoindre une entreprise existante).
     *
     * Payload :
     *   - target_company_id  UUID de l'entreprise cible (obligatoire)
     *   - target_company_name Nom de l'entreprise (informatif, stocké pour lisibilité)
     *   - message           Message facultatif pour le manager
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_company_id'   => ['required', 'string', 'uuid'],
            'target_company_name' => ['required', 'string', 'max:200'],
            'message'             => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');

        // Anti-doublon : empêche plusieurs demandes en attente pour la même entreprise
        $existing = $user->companyRequests()
            ->where('type', 'integration')
            ->where('target_company_id', $validated['target_company_id'])
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($existing) {
            return new JsonResponse([
                'error'   => 'INTEGRATION_REQUEST_ALREADY_PENDING',
                'message' => __('errors.INTEGRATION_REQUEST_ALREADY_PENDING'),
            ], 409);
        }

        // Limite globale de demandes en attente
        $pending = $user->companyRequests()
            ->where('type', 'integration')
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pending >= 5) {
            return new JsonResponse([
                'error'   => 'TOO_MANY_PENDING_REQUESTS',
                'message' => __('errors.TOO_MANY_PENDING_REQUESTS'),
            ], 422);
        }

        $payload = [
            'type'              => 'integration',
            'target_company_id' => $validated['target_company_id'],
            'company_name'      => $validated['target_company_name'],
            'email'             => $user->email,
            'status'            => 'pending',
        ];

        if (Schema::hasColumn('company_requests', 'notes') && ! empty($validated['message'])) {
            $payload['notes'] = $validated['message'];
        }

        $companyRequest = $user->companyRequests()->create($payload);

        return new JsonResponse([
            'data' => [
                'id'                  => $companyRequest->id,
                'type'                => 'integration',
                'target_company_id'   => $validated['target_company_id'],
                'target_company_name' => $validated['target_company_name'],
                'status'              => $companyRequest->status,
                'created_at'          => $companyRequest->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Liste les demandes d'intégration de l'utilisateur courant.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

        $requests = $user->companyRequests()
            ->where('type', 'integration')
            ->latest()
            ->get()
            ->map(fn (CompanyRequest $r) => [
                'id'                  => $r->id,
                'target_company_id'   => $r->target_company_id,
                'target_company_name' => $r->company_name,
                'status'              => $r->status,
                'admin_notes'         => $r->admin_notes,
                'reviewed_at'         => $r->reviewed_at?->toIso8601String(),
                'created_at'          => $r->created_at?->toIso8601String(),
            ]);

        return new JsonResponse(['data' => $requests]);
    }

    // ── MANAGER-SIDE ───────────────────────────────────────────────────────

    /**
     * Liste les demandes d'intégration en attente pour le tenant du manager.
     */
    public function managerIndex(Request $request): JsonResponse
    {
        /** @var Employee $manager */
        $manager = $request->user();

        if (! $manager->isManager()) {
            return new JsonResponse([
                'error'   => 'FORBIDDEN',
                'message' => __('errors.MANAGER_ONLY_ACTION'),
            ], 403);
        }

        $requests = CompanyRequest::query()
            ->where('type', 'integration')
            ->where('target_company_id', $manager->company_id)
            ->whereIn('status', ['pending', 'processing'])
            ->with('user:id,first_name,last_name,email,avatar_url,personal_statuses')
            ->latest()
            ->get()
            ->map(fn (CompanyRequest $r) => [
                'id'         => $r->id,
                'status'     => $r->status,
                'message'    => $r->notes ?? null,
                'created_at' => $r->created_at?->toIso8601String(),
                'user'       => $r->user ? [
                    'id'               => $r->user->id,
                    'full_name'        => $r->user->fullName(),
                    'email'            => $r->user->email,
                    'avatar_url'       => $r->user->avatar_url,
                    'personal_statuses' => $r->user->personal_statuses ?? [],
                ] : null,
            ]);

        return new JsonResponse(['data' => $requests]);
    }

    /**
     * Le manager accepte la demande d'intégration.
     *
     * Crée un lien user-employé pour l'employé désigné par `employee_id`.
     * Le manager doit identifier la fiche employé (existante) à lier.
     * Si l'employé n'existe pas encore, il doit d'abord créer la fiche
     * via le flux RH standard avant d'accepter la demande.
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        /** @var Employee $manager */
        $manager = $request->user();

        if (! $manager->isManager()) {
            return new JsonResponse([
                'error'   => 'FORBIDDEN',
                'message' => __('errors.MANAGER_ONLY_ACTION'),
            ], 403);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var CompanyRequest|null $companyRequest */
        $companyRequest = CompanyRequest::query()
            ->where('id', $id)
            ->where('type', 'integration')
            ->where('target_company_id', $manager->company_id)
            ->whereIn('status', ['pending', 'processing'])
            ->with('user')
            ->first();

        if (! $companyRequest || ! $companyRequest->user) {
            return new JsonResponse([
                'error'   => 'NOT_FOUND',
                'message' => __('errors.INTEGRATION_REQUEST_NOT_FOUND'),
            ], 404);
        }

        $user      = $companyRequest->user;
        $companyId = $manager->company_id;

        // Vérifier que l'employé appartient bien au tenant courant
        $employee = Employee::query()
            ->where('id', $validated['employee_id'])
            ->where('company_id', $companyId)
            ->first();

        if (! $employee) {
            return new JsonResponse([
                'error'   => 'EMPLOYEE_NOT_FOUND',
                'message' => __('errors.EMPLOYEE_NOT_FOUND_IN_COMPANY'),
            ], 404);
        }

        // Anti-doublon : vérifier qu'un lien n'existe pas déjà
        $existingLink = UserEmployeeLink::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        if ($existingLink) {
            // Idempotent : clôt la demande si le lien est déjà établi
            $companyRequest->update([
                'status'              => 'approved',
                'admin_notes'         => $validated['admin_notes'] ?? 'Lien déjà existant.',
                'reviewed_at'         => now(),
                'approved_company_id' => $companyId,
            ]);

            return new JsonResponse([
                'data' => ['link_id' => $existingLink->id, 'status' => 'already_linked'],
            ]);
        }

        DB::transaction(function () use ($user, $employee, $companyId, $companyRequest, $validated): void {
            // Créer le lien user ↔ employé
            UserEmployeeLink::create([
                'user_id'     => $user->id,
                'employee_id' => $employee->id,
                'company_id'  => $companyId,
                'status'      => 'active',
                'linked_at'   => now(),
            ]);

            // Clôturer la demande
            $companyRequest->update([
                'status'              => 'approved',
                'admin_notes'         => $validated['admin_notes'] ?? null,
                'reviewed_at'         => now(),
                'approved_company_id' => $companyId,
            ]);
        });

        Log::info("Integration request #{$id} accepted: user {$user->id} linked to employee {$employee->id} in company {$companyId}.");

        return new JsonResponse([
            'data' => [
                'status'      => 'accepted',
                'employee_id' => $employee->id,
            ],
        ]);
    }

    /**
     * Le manager rejette la demande d'intégration.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        /** @var Employee $manager */
        $manager = $request->user();

        if (! $manager->isManager()) {
            return new JsonResponse([
                'error'   => 'FORBIDDEN',
                'message' => __('errors.MANAGER_ONLY_ACTION'),
            ], 403);
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var CompanyRequest|null $companyRequest */
        $companyRequest = CompanyRequest::query()
            ->where('id', $id)
            ->where('type', 'integration')
            ->where('target_company_id', $manager->company_id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if (! $companyRequest) {
            return new JsonResponse([
                'error'   => 'NOT_FOUND',
                'message' => __('errors.INTEGRATION_REQUEST_NOT_FOUND'),
            ], 404);
        }

        $companyRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        return new JsonResponse(['data' => ['status' => 'rejected']]);
    }
}
