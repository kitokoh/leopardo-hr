<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * #8717 — garde d'architecture : frontieres de couches du BC Identite.
 *
 * L'audit de readiness (`docs/audits/readiness-identite-paiements.md`, ecart
 * I-3) constate qu'un seul test d'architecture existe
 * (`QueueTenantContextArchitectureTest`) et qu'aucun ne verrouille les
 * frontieres `Core/Auth`, `Core/Tenant` <-> `Modules/*`.
 *
 * Deux regles sont verrouillees ici :
 *
 *   1. le domaine `Core` ne depend pas de `Modules` — toute NOUVELLE arete
 *      fait echouer la suite ;
 *   2. les `Modules` n'accedent a `Core` que par contrats/interfaces — tout
 *      NOUVEL import d'une classe concrete de `Core` fait echouer la suite.
 *
 * Pourquoi un RATCHET et non une interdiction seche : le depot porte deja une
 * dette d'architecture reelle (21 aretes `Core -> Modules`, 36 imports
 * concrets `Modules -> Core`). Un test qui exigerait zero violation serait
 * rouge des le premier run et serait desactive — il ne protegerait rien. La
 * baseline ci-dessous fige l'existant : elle ne peut que retrecir. Une arete
 * corrigee doit etre retiree de la baseline (le test de fraicheur le rappelle),
 * et une arete ajoutee echoue immediatement.
 *
 * Test volontairement SANS boot Laravel (pur PHPUnit + autoload composer) :
 * il ne depend d'aucune base de donnees, comme
 * `QueueTenantContextArchitectureTest`.
 */
final class IdentityLayerBoundariesArchitectureTest extends TestCase
{
    /**
     * Aretes `Core -> Modules` tolerees, avec la raison de leur presence.
     *
     * Cle : `<chemin relatif a app/>|<FQN importe>`.
     *
     * @var array<string, string>
     */
    private const CORE_TO_MODULES_BASELINE = [
        // Relations Eloquent du modele Employee vers les modules verticaux.
        // Dette #6568 : a extraire en contrats si le domaine doit redevenir pur.
        'Core/Auth/Domain/Models/Employee.php|App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest' => 'relation Eloquent Employee -> Attendance (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\Cabinet\Domain\Models\CabinetDocument' => 'relation Eloquent Employee -> Cabinet (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\Cabinet\Domain\Models\CabinetFolder' => 'relation Eloquent Employee -> Cabinet (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\HR\Domain\Models\Department' => 'relation Eloquent Employee -> HR (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\HR\Domain\Models\EmployeeDocument' => 'relation Eloquent Employee -> HR (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\HR\Domain\Models\OnboardingProgress' => 'relation Eloquent Employee -> HR (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\HR\Domain\Models\Position' => 'relation Eloquent Employee -> HR (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\HR\Domain\Models\PrivacyRequest' => 'relation Eloquent Employee -> HR (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\Notification\Domain\Models\Notification' => 'relation Eloquent Employee -> Notification (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\Notification\Domain\Models\NotificationPreference' => 'relation Eloquent Employee -> Notification (dette #6568)',
        'Core/Auth/Domain/Models/Employee.php|App\Modules\Planning\Domain\Models\Schedule' => 'relation Eloquent Employee -> Planning (dette #6568)',

        // Relations Eloquent du modele Company vers les modules verticaux.
        'Core/Tenant/Domain/Models/Company.php|App\Modules\Attendance\Domain\Models\AttendanceKiosk' => 'relation Eloquent Company -> Attendance (dette #6568)',
        'Core/Tenant/Domain/Models/Company.php|App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest' => 'relation Eloquent Company -> Attendance (dette #6568)',

        // Lien d'authentification resolu AVANT l'etablissement du contexte
        // tenant (necessaire a l'authentification elle-meme).
        'Core/Auth/Domain/Models/User.php|App\Modules\HR\Domain\Models\UserEmployeeLink' => 'lien d authentification resolu avant le contexte tenant',

        // Actions/controleurs de profil partages avec HR.
        'Core/Auth/Application/Actions/UpdateProfileAction.php|App\Modules\HR\Application\DTOs\UpdateEmployeeDTO' => 'DTO de mise a jour de profil partage avec HR',
        'Core/Auth/Application/Actions/UpdateProfileAction.php|App\Modules\HR\Infrastructure\Services\EmployeeService' => 'service employe appele par la mise a jour de profil',
        'Core/Auth/Interfaces/Api/V1/Controllers/AuthController.php|App\Modules\HR\Application\DTOs\UpdateEmployeeDTO' => 'DTO de mise a jour de profil expose par AuthController',

        // Notification de verrouillage de compte.
        'Core/Auth/Infrastructure/Services/AccountLockoutNotifier.php|App\Modules\Notification\Infrastructure\Services\NotificationDispatcher' => 'notification de verrouillage de compte',

        // Detection de fonctionnalites (Core/Feature) adossee au modele Billing.
        'Core/Feature/Infrastructure/Services/ApiEndpointRegistry.php|App\Modules\Billing\Domain\Models\Feature' => 'registre d endpoints adosse au modele Feature (dette #6568)',
        'Core/Feature/Infrastructure/Services/FeatureDetector.php|App\Modules\Billing\Domain\Models\Feature' => 'detection de fonctionnalites adossee au modele Feature (dette #6568)',
        'Core/Feature/Interfaces/Api/V1/Requests/UpdateFeatureMatrixRequest.php|App\Modules\Billing\Domain\Enums\PlanCode' => 'validation de la matrice de fonctionnalites par PlanCode',
    ];

    /**
     * Imports de classes CONCRETES de `Core` depuis `Modules`, toleres.
     *
     * Les imports de contrats/interfaces/enums/value objects/exceptions ne
     * figurent pas ici : ils sont autorises par nature (voir CONTRACT_MARKERS).
     *
     * @var list<string>
     */
    private const MODULES_TO_CORE_CONCRETE_BASELINE = [
        'App\Core\AI\Infrastructure\Adapters\UnavailableFaceVerificationAdapter',
        'App\Core\Auth\Domain\Models\AuditLog',
        'App\Core\Auth\Domain\Models\Employee',
        'App\Core\Auth\Domain\Models\User',
        'App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess',
        'App\Core\Auth\Infrastructure\Services\AuthService',
        'App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger',
        'App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor',
        'App\Core\Auth\Interfaces\Api\V1\Requests\UpdateProfileRequest',
        'App\Core\Feature\Infrastructure\Services\FeatureFlag',
        'App\Core\Feature\Infrastructure\Services\FeatureFlagAuditRecorder',
        'App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService',
        'App\Core\Feature\Infrastructure\Services\ModuleRegistryGateway',
        'App\Core\Http\Idempotency\IdempotencyStore',
        'App\Core\Mail\EmailTemplateRegistry',
        'App\Core\Mail\EmailTemplateRepository',
        'App\Core\Mail\EmailTemplateResolver',
        'App\Core\Privacy\Infrastructure\Services\PiiRegistry',
        'App\Core\Solutions\DemoDataRegistry',
        'App\Core\Solutions\SolutionActivator',
        'App\Core\Solutions\SolutionCatalogue',
        'App\Core\Solutions\Survey\SolutionSurveyRegistry',
        'App\Core\Tenant\Domain\Models\Company',
        'App\Core\Tenant\Domain\Models\CompanyRequest',
        'App\Core\Tenant\Domain\Models\CompanySetting',
        'App\Core\Tenant\Domain\Models\EmployeeModuleGrant',
        'App\Core\Tenant\Domain\Models\EmployeeResourceAssignment',
        'App\Core\Tenant\Domain\Models\Site',
        'App\Core\Tenant\Domain\Models\SuperAdmin',
        'App\Core\Tenant\Infrastructure\Services\ResourceTypeRegistry',
        'App\Core\Tenant\Infrastructure\Services\TenantCacheService',
        'App\Core\Tenant\Infrastructure\Services\TenantEventDispatcher',
        'App\Core\Tenant\TenantManager',
    ];

    /**
     * Fragments de namespace de `Core` consideres comme des CONTRATS : un
     * module peut les importer sans contrainte, c'est leur raison d'etre.
     *
     * @var list<string>
     */
    private const CONTRACT_MARKERS = [
        '\Domain\Contracts\\',
        '\Domain\Enums\\',
        '\Domain\ValueObjects\\',
        '\Domain\Exceptions\\',
        '\Domain\Events\\',
        '\Contracts\\',
        // Enums/Exceptions hors Domain (ex. Core/Solutions/Enums) : ce sont des
        // types de contrat, pas des services concrets.
        '\Enums\\',
        '\Exceptions\\',
    ];

    public function test_core_does_not_gain_new_dependencies_on_modules(): void
    {
        $new = array_values(array_diff($this->coreToModulesEdges(), array_keys(self::CORE_TO_MODULES_BASELINE)));

        $this->assertSame(
            [],
            $new,
            "Le domaine Core ne doit pas dependre de Modules (#8717, ecart I-3).\n"
            ."Nouvelles aretes :\n - ".implode("\n - ", $new)
            ."\nSoit passer par un contrat/une interface, soit ajouter l'arete a "
            .'CORE_TO_MODULES_BASELINE avec une justification (decision relue en PR).'
        );
    }

    public function test_core_to_modules_baseline_stays_fresh(): void
    {
        $stale = array_values(array_diff(array_keys(self::CORE_TO_MODULES_BASELINE), $this->coreToModulesEdges()));

        $this->assertSame(
            [],
            $stale,
            'Baseline obsolete : ces aretes Core -> Modules n\'existent plus, les retirer de '
            ."CORE_TO_MODULES_BASELINE (le ratchet ne peut que retrecir) :\n - ".implode("\n - ", $stale)
        );
    }

    public function test_modules_reach_core_only_through_contracts(): void
    {
        $violations = [];

        foreach ($this->modulesToCoreImports() as $import) {
            if ($this->isContract($import)) {
                continue;
            }

            if (in_array($import, self::MODULES_TO_CORE_CONCRETE_BASELINE, true)) {
                continue;
            }

            $violations[] = $import;
        }

        $this->assertSame(
            [],
            $violations,
            "Les Modules ne doivent acceder a Core que par contrats/interfaces (#8717, ecart I-3).\n"
            ."Imports de classes concretes non allowlistes :\n - ".implode("\n - ", $violations)
            ."\nSoit dependre d'un contrat (Domain/Contracts, Enums, ValueObjects, Exceptions), "
            .'soit ajouter l\'import a MODULES_TO_CORE_CONCRETE_BASELINE avec une justification.'
        );
    }

    public function test_modules_to_core_concrete_baseline_stays_fresh(): void
    {
        $imports = $this->modulesToCoreImports();
        $stale = [];

        foreach (self::MODULES_TO_CORE_CONCRETE_BASELINE as $import) {
            if (! in_array($import, $imports, true)) {
                $stale[] = $import;
            }
        }

        $this->assertSame(
            [],
            $stale,
            'Baseline obsolete : ces imports concrets de Core ne sont plus utilises, les retirer de '
            ."MODULES_TO_CORE_CONCRETE_BASELINE :\n - ".implode("\n - ", $stale)
        );
    }

    public function test_scan_is_not_broken(): void
    {
        // Si le scan ne trouve plus rien, les tests ci-dessus passeraient a vide
        // (un tableau vide n'a aucune violation) : on verrouille le fait que le
        // perimetre est bien explore.
        $this->assertNotEmpty($this->coreToModulesEdges(), 'Le scan Core -> Modules ne trouve plus rien : le perimetre est casse.');
        $this->assertNotEmpty($this->modulesToCoreImports(), 'Le scan Modules -> Core ne trouve plus rien : le perimetre est casse.');
    }

    /**
     * Aretes `Core -> Modules` : `<chemin relatif a app/>|<FQN importe>`.
     *
     * @return list<string>
     */
    private function coreToModulesEdges(): array
    {
        $edges = [];

        foreach ($this->phpFiles('Core') as $relative => $file) {
            foreach ($this->imports($file) as $import) {
                if (str_starts_with($import, 'App\Modules\\')) {
                    $edges[] = $relative.'|'.$import;
                }
            }
        }

        sort($edges);

        return array_values(array_unique($edges));
    }

    /**
     * Imports distincts de `Core` depuis `Modules`.
     *
     * @return list<string>
     */
    private function modulesToCoreImports(): array
    {
        $imports = [];

        foreach ($this->phpFiles('Modules') as $file) {
            foreach ($this->imports($file) as $import) {
                if (str_starts_with($import, 'App\Core\\')) {
                    $imports[] = $import;
                }
            }
        }

        sort($imports);

        return array_values(array_unique($imports));
    }

    /**
     * Fichiers PHP d'un sous-arbre de `app/`, indexes par chemin relatif a `app/`.
     *
     * @return array<string, string>
     */
    private function phpFiles(string $subtree): array
    {
        $base = dirname(__DIR__, 3).'/app/'.$subtree;
        $files = [];

        $finder = Finder::create()->files()->in($base)->name('*.php');

        foreach ($finder as $file) {
            $relative = $subtree.'/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
            $files[$relative] = $file->getPathname();
        }

        return $files;
    }

    /**
     * Instructions `use` de premier niveau d'un fichier (FQN seul, alias retire).
     *
     * @return list<string>
     */
    private function imports(string $file): array
    {
        $source = (string) file_get_contents($file);

        $count = preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

        if ($count === false || $count === 0) {
            return [];
        }

        $imports = [];

        foreach ($matches[1] as $raw) {
            // `use App\X as Y;` -> on ne conserve que le FQN.
            $imports[] = (string) preg_replace('/\s+as\s+.*$/i', '', trim($raw));
        }

        return $imports;
    }

    private function isContract(string $import): bool
    {
        foreach (self::CONTRACT_MARKERS as $marker) {
            if (str_contains($import, $marker)) {
                return true;
            }
        }

        return false;
    }
}
