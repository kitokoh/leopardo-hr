<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TenantContextService;
use App\Core\Auth\Interfaces\Api\V1\Requests\SelectTenantContextRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IAM-L2 (#9092) — sélection du contexte tenant.
 *
 * ## Le défaut corrigé
 *
 * Une personne membre de deux entreprises obtenait un contexte **arbitraire** :
 * `AuthService::findEmployeeInTenantSchemas()` retournait le premier employé
 * dont l'e-mail correspondait, dans l'ordre de `public.companies`. Aucun
 * endpoint ne permettait de choisir — prouvé par
 * `test_there_is_no_endpoint_to_select_a_tenant_context`.
 *
 * ## Le contrat
 *
 * - `GET  /api/v1/auth/contexts` → les entreprises de la personne ;
 * - `POST /api/v1/auth/context` `{company_id}` → un jeton **borné** à
 *   l'entreprise choisie.
 *
 * ## Sécurité
 *
 * L'appartenance est vérifiée **avant** toute réémission : une entreprise à
 * laquelle la personne n'appartient pas rend `403`, jamais un jeton. Le jeton
 * réémis porte les mêmes abilities que celles posées au login
 * (`tenant_schema:`, `tenant_company:`, `tenant_employee:`) — aucune
 * permission n'est élargie par la sélection.
 */
class TenantContextController extends Controller
{
    public function __construct(
        private readonly TenantContextService $tenantContextService,
    ) {}

    /**
     * Liste les contextes tenant de la personne connectée.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $contexts = $this->tenantContextService->listContextsForEmail((string) $employee->email);

        return new JsonResponse(['data' => $contexts]);
    }

    /**
     * Sélectionne explicitement un contexte tenant et réémet un jeton borné.
     */
    public function store(SelectTenantContextRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $companyId = (string) $request->validated('company_id');

        $context = $this->tenantContextService->resolveContextForEmail((string) $employee->email, $companyId);

        // Appartenance NON prouvée → 403. C'est le test critique du lot :
        // aucune réémission de jeton pour une entreprise non possédée.
        if ($context === null) {
            return new JsonResponse(['error' => 'TENANT_CONTEXT_FORBIDDEN'], 403);
        }

        $tenantEmployee = $context['employee'];
        $company = $context['company'];
        $schema = $context['schema'];

        // #9344 (audit sécurité 2026-11-10, F2) — durée centralisée : 12 h.
        $expiresAt = app(\App\Core\Auth\Infrastructure\Services\TokenExpiryService::class)->forInteractive();

        // Mêmes abilities que celles posées au login (AuthService:214-220) :
        // la sélection ne fait que les REBORNER sur l'entreprise choisie.
        $abilities = [
            '*',
            'tenant_schema:'.$schema,
            'tenant_email:'.$tenantEmployee->email,
            'tenant_company:'.$company->id,
            'tenant_employee:'.$tenantEmployee->id,
        ];

        // Nom déterministe et lisible : l'utilisateur distingue ses jetons par
        // entreprise dans la liste des jetons personnels. On n'hérite PAS du
        // nom du jeton courant — `currentAccessToken()` peut être un
        // `TransientToken` (tests) sans nom, et son PHPDoc le déclare à tort
        // comme `PersonalAccessToken`.
        $tokenName = 'tenant:'.$company->id;

        $tokenResult = $tenantEmployee->createToken($tokenName, $abilities, $expiresAt);

        return new JsonResponse([
            'data' => [
                'token' => $tokenResult->plainTextToken,
                'token_type' => 'Bearer',
                'token_expires_at' => $expiresAt?->toIso8601String(),
                'tenant_schema' => $schema,
                'company_id' => (string) $company->id,
                'company_name' => (string) $company->name,
                'role' => (string) $tenantEmployee->role,
                'manager_role' => $tenantEmployee->manager_role,
            ],
        ]);
    }
}
