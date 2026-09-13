<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Onboarding\Application\Services\OnboardingProgressReader;
use Illuminate\Http\JsonResponse;

/**
 * Moteur d'OBSERVATION de l'onboarding — /!\ DÉPRÉCIÉ comme source de progression.
 *
 * #4929 puis #7300 — le contrat canonique de progression est
 * `GET /onboarding-setup/checklist` (table `onboarding_steps`, pilotée par
 * l'utilisateur). Cet endpoint reste servi pour deux usages RÉELS du wizard :
 *   1. `metrics.employees_count` (badge Quick Start) ;
 *   2. l'auto-complétion d'étapes setup dont la condition est déjà vraie
 *      (`CALCULATED_TO_SETUP_MAPPING` côté web).
 *
 * #7300 — il exposait auparavant SA propre progression (8 prédicats calculés,
 * donc mécaniquement différente de la checklist setup). C'est ce qui faisait
 * afficher 38 % au client pendant que l'assistant affichait 100 %. Les champs
 * de progression en tête de réponse sont désormais la progression CANONIQUE
 * (mêmes nombres que `/onboarding-setup/checklist`, via
 * `OnboardingProgressReader`) ; les faits observés restent disponibles sous
 * `observed` et `steps`, explicitement nommés — « adoption observée » n'est pas
 * « onboarding ».
 *
 * Shape :
 *   data: {
 *     // canonique (identique à /onboarding-setup/checklist)
 *     completed_steps, total_steps, progress_percent, progress, go_live_ready,
 *     next_actions,
 *     deprecated: true,
 *     canonical_source: '/onboarding-setup/checklist',
 *     // observation serveur (ne pas confondre avec la progression setup)
 *     observed: { completed_steps, total_steps, progress_percent },
 *     steps: [...],   // détail des prédicats observés (wizard)
 *   }
 *
 * Lecture ouverte à tout utilisateur authentifié du tenant (les données
 * restent scopées à sa société par le middleware tenant) ; les écritures
 * (complete/skip) restent gouvernées par OnboardingStepController.
 */
class OnboardingChecklistController extends Controller
{
    public function __construct(
        private readonly OnboardingProgressReader $progressReader,
    ) {}

    public function __invoke(): JsonResponse
    {
        /** @var Employee|null $actor */
        $actor = request()->user();

        // #3239 — plus de 403 : tout utilisateur authentifié du tenant peut
        // lire sa propre checklist (remplace l'ancien authorize viewAny
        // réservé aux managers). Garde simple : un utilisateur authentifié
        // est forcément un Employee (auth:sanctum + middleware tenant).
        abort_if(! $actor instanceof Employee, 401);

        $company = currentCompany();

        $employeesCount = Employee::query()->where('company_id', $company->id)->count();
        $activeEmployeesCount = Employee::query()->where('company_id', $company->id)->where('status', 'active')->count();
        $biometricReadyCount = Employee::query()
            ->where('company_id', $company->id)
            ->where(function ($query): void {
                $query
                    ->where('biometric_face_enabled', true)
                    ->orWhere('biometric_fingerprint_enabled', true);
            })
            ->count();
        $payrollReadyCount = Employee::query()
            ->where('company_id', $company->id)
            ->where(function ($query): void {
                $query
                    ->where('salary_base', '>', 0)
                    ->orWhere('hourly_rate', '>', 0);
            })
            ->count();
        $kioskCount = AttendanceKiosk::query()->where('company_id', $company->id)->where('status', 'active')->count();
        $geofence = $company->metadata['attendance_geofence'] ?? null;
        $geofenceConfigured = is_array($geofence)
            && isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])
            && (float) $geofence['radius_meters'] > 0;

        // #R15 — required=true pour les étapes essentielles au go-live ;
        // optional pour kiosk/geofence/biometrie (équipements spécifiques).
        $steps = [
            $this->step('company_created', 'Societe creee', true, required: true),
            $this->step('manager_active', 'Manager principal actif', $actor->role === 'manager', required: true),
            $this->step('employees_added', 'Equipe ajoutee', $employeesCount >= 2, required: true, metrics: ['employees_count' => $employeesCount]),
            $this->step('employees_active', 'Comptes employes actives', $activeEmployeesCount >= max(1, $employeesCount), required: true, metrics: ['active_employees_count' => $activeEmployeesCount]),
            $this->step('payroll_ready', 'Bases de paie renseignees', $employeesCount > 0 && $payrollReadyCount >= $employeesCount, required: false, metrics: ['payroll_ready_count' => $payrollReadyCount]),
            $this->step('geofence_configured', 'Zone de pointage configuree', $geofenceConfigured, required: false),
            $this->step('biometrics_ready', 'Biometrie configuree', $biometricReadyCount > 0, required: false, metrics: ['biometric_ready_count' => $biometricReadyCount]),
            $this->step('kiosk_connected', 'Kiosque ou borne connecte', $kioskCount > 0, required: false, metrics: ['kiosk_count' => $kioskCount]),
        ];

        $completed = collect($steps)->where('completed', true)->count();
        $percent = (int) round(($completed / count($steps)) * 100);

        // #R15 — go_live_ready : toutes les étapes requises doivent être complétées.
        $allRequiredDone = collect($steps)
            ->filter(fn (array $s): bool => $s['required'] === true)
            ->every(fn (array $s): bool => $s['completed'] === true);

        $nextActions = collect($steps)
            ->where('completed', false)
            ->take(3)
            ->map(fn (array $step): array => [
                'key' => $step['key'],
                'label' => $step['label'],
            ])
            ->values();

        // #7300 — la progression affichée est celle de la SOURCE DE VÉRITÉ
        // (checklist setup), jamais la nôtre : deux surfaces ne peuvent plus
        // annoncer deux chiffres différents pour le même tenant.
        $canonical = $this->progressReader->read($company->id);

        return new JsonResponse([
            'data' => [
                // ── Progression canonique (identique à /onboarding-setup/checklist)
                'completed_steps' => $canonical['completed_steps'],
                'total_steps' => $canonical['total_steps'],
                'progress_percent' => $canonical['progress_percent'],
                'progress' => $canonical['progress'],
                'go_live_ready' => $canonical['go_live_ready'],
                'next_actions' => $canonical['next_actions'],
                'deprecated' => true,
                'canonical_source' => '/onboarding-setup/checklist',
                // ── Observation serveur (≠ progression setup)
                'observed' => [
                    'completed_steps' => $completed,
                    'total_steps' => count($steps),
                    'progress_percent' => $percent,
                    'go_live_ready' => $allRequiredDone,
                    'next_actions' => $nextActions,
                ],
                // Détail des prédicats observés — consommé par le wizard
                // (employees_count + auto-complétion) : contrat inchangé.
                'steps' => $steps,
            ],
        ]);
    }

    // #R15 — `required` ajouté pour go_live_ready pondéré.
    private function step(string $key, string $label, bool $completed, bool $required = true, array $metrics = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'completed' => $completed,
            'required' => $required,
            'metrics' => (object) $metrics,
        ];
    }
}
