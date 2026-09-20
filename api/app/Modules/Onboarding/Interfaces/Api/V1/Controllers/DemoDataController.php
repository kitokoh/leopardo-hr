<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\DemoDataRegistry;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Onboarding\Infrastructure\Services\CompanyOnboardingCompletionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #7865 — jeu de données de démonstration à la demande du client.
 *
 * Un tenant fraîchement activé sur une verticale démarre sur un espace VIDE :
 * ce contrôleur permet au responsable d'installer (ou d'écarter) le kit de
 * démonstration de chaque verticale ACTIVE, depuis le dashboard.
 *
 *  - GET  /demo-data                 → kits par verticale active (statut) ;
 *  - POST /demo-data/{code}/import   → installe le kit (idempotent) ;
 *  - POST /demo-data/{code}/dismiss  → « non merci » (idempotent).
 *
 * Fail-closed : code hors verticales actives du tenant → 422 ; verticale
 * active sans kit enregistré (`DemoDataRegistry`) → 422 à l'import. Les
 * seeders eux-mêmes sont idempotents et tenant-scoped (RESTO-107 /
 * TRAVEL-107), mais un import déjà `imported` est un no-op qui NE rejoue
 * PAS le seeder (l'état courant est renvoyé tel quel).
 *
 * État persisté dans `public.companies.metadata.demo_data[code]` (même canal
 * que `setup_interview`), exposé au portail par `/auth/me`
 * (`company.metadata`) — pas de table dédiée : l'état est petit, borné
 * (allowlist de codes) et vit avec la société. Chaque import est audité
 * (`demo_data.imported`, pattern `SolutionActivator`).
 *
 * RBAC : responsable du tenant (`principal`/`rh`), miroir de
 * `SetupInterviewController` — un employé n'installe pas de données de démo.
 */
class DemoDataController extends Controller
{
    private const STATUSES = ['not_imported', 'imported', 'dismissed'];

    public function __construct(
        private readonly SolutionCatalogue $catalogue,
        private readonly DemoDataRegistry $registry,
        private readonly CompanyOnboardingCompletionWriter $writer,
    ) {}

    /**
     * Liste les kits de démonstration : une entrée par solution du catalogue
     * ACTIVE sur le tenant (une verticale sans kit reste listée avec
     * `available: false` — le front sait ne rien proposer).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $company = $this->freshCompany();

        $kits = [];
        foreach ($this->catalogue->codes() as $code) {
            if (! $company->hasFeature($code)) {
                continue;
            }

            $kits[] = ['code' => $code] + $this->kitState($company, $code);
        }

        return response()->json(['data' => ['kits' => $kits]]);
    }

    /**
     * Installe le kit de démonstration de la verticale (idempotent : un kit
     * déjà `imported` renvoie l'état courant SANS rejouer le seeder).
     */
    public function import(Request $request, string $code): JsonResponse
    {
        $actor = $this->authorizeManager($request);

        $company = $this->freshCompany();
        $this->assertActiveVertical($company, $code);

        if (! $this->registry->has($code)) {
            throw ValidationException::withMessages([
                'code' => [__('onboarding.demo_data_kit_unavailable', ['code' => $code])],
            ]);
        }

        if ($this->kitState($company, $code)['status'] === 'imported') {
            return response()->json(['data' => ['code' => $code] + $this->kitState($company, $code)]);
        }

        $this->registry->seed($code, $company);

        $metadata = $company->metadata ?? [];
        $demoData = is_array($metadata['demo_data'] ?? null) ? $metadata['demo_data'] : [];
        $demoData[$code] = [
            'status' => 'imported',
            'imported_at' => now()->toIso8601String(),
            'imported_by' => $actor->id,
        ];
        $metadata['demo_data'] = $demoData;

        $this->writer->persist((string) $company->id, $metadata);

        // Audit de l'import (pattern `SolutionActivator::activate`).
        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $actor->id,
            'action' => 'demo_data.imported',
            'auditable_type' => Company::class,
            'auditable_id' => null,
            'old_values' => ['status' => 'not_imported'],
            'new_values' => [
                'solution' => $code,
                'status' => 'imported',
            ],
        ]);

        return response()->json(['data' => ['code' => $code] + $this->kitState($this->freshCompany(), $code)]);
    }

    /**
     * « Non merci » : le client écarte la proposition de démo (relance douce
     * côté front, jamais bloquante). Idempotent ; sans effet sur un kit déjà
     * importé — l'écartement tardif ne masque pas des données installées.
     */
    public function dismiss(Request $request, string $code): JsonResponse
    {
        $this->authorizeManager($request);

        $company = $this->freshCompany();
        $this->assertActiveVertical($company, $code);

        $metadata = $company->metadata ?? [];
        $demoData = is_array($metadata['demo_data'] ?? null) ? $metadata['demo_data'] : [];
        $entry = is_array($demoData[$code] ?? null) ? $demoData[$code] : [];

        if (! in_array($entry['status'] ?? null, ['imported', 'dismissed'], true)) {
            $entry['status'] = 'dismissed';
            $entry['dismissed_at'] = now()->toIso8601String();
            $demoData[$code] = $entry;
            $metadata['demo_data'] = $demoData;
            $this->writer->persist((string) $company->id, $metadata);
        }

        return response()->json(['data' => ['code' => $code] + $this->kitState($this->freshCompany(), $code)]);
    }

    private function authorizeManager(Request $request): Employee
    {
        /** @var Employee|null $actor */
        $actor = $request->user();

        abort_if(! $actor instanceof Employee, 401);
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        return $actor;
    }

    /**
     * Garde fail-closed : le code doit être une verticale ACTIVE du tenant
     * (allowlist implicite `companies.features` — un code inconnu du
     * catalogue n'est jamais actif, donc refusé par le même chemin).
     */
    private function assertActiveVertical(Company $company, string $code): void
    {
        if (! $company->hasFeature($code)) {
            throw ValidationException::withMessages([
                'code' => [__('onboarding.demo_data_vertical_inactive', ['code' => $code])],
            ]);
        }
    }

    /**
     * État normalisé d'un kit (shape stable, statut allowlisté).
     *
     * @return array<string, mixed>
     */
    private function kitState(Company $company, string $code): array
    {
        $metadata = $company->metadata ?? [];
        $demoData = is_array($metadata['demo_data'] ?? null) ? $metadata['demo_data'] : [];
        $entry = is_array($demoData[$code] ?? null) ? $demoData[$code] : [];

        $status = $entry['status'] ?? 'not_imported';
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'not_imported';
        }

        return [
            'available' => $this->registry->has($code),
            'status' => $status,
            'imported_at' => $entry['imported_at'] ?? null,
            'dismissed_at' => $entry['dismissed_at'] ?? null,
        ];
    }

    /**
     * Relit la société courante depuis la table qualifiée — le modèle résolu
     * par `search_path` (contexte tenant) pointerait vers le mauvais schéma
     * (piège documenté #7322 / `CompanyBrandingController`).
     */
    private function freshCompany(): Company
    {
        $company = currentCompany();

        return Company::query()
            ->from($this->companiesTable())
            ->where('id', $company->id)
            ->firstOrFail();
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }
}
