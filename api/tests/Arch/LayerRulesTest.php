<?php

declare(strict_types=1);

/**
 * Règles de couches des modules (PA2-ARCH-002 / ADR-0020).
 *
 * Ces tests rendent EXÉCUTABLES des règles qui n'étaient jusqu'ici que
 * documentées dans `api/ARCHITECTURE.md` et vérifiées à la main. Constat de
 * l'audit : sur 34 modules, aucune règle de couche n'était testée — une
 * violation ne se voyait qu'en revue humaine, donc souvent jamais.
 *
 * Chaque règle est un test Pest qui échoue avec la liste des fichiers
 * fautifs. Les exceptions légitimes sont énumérées explicitement ci-dessous,
 * avec leur justification : une nouvelle violation casse la CI, une exception
 * doit être argumentée dans ce fichier.
 */

use Symfony\Component\Finder\Finder;

/**
 * Fichiers PHP d'une couche donnée, tous modules confondus.
 *
 * @return array<string, string> chemin => contenu
 */
function archLayerFiles(string $layer): array
{
    // Pas de base_path() : ces tests doivent tourner sans bootstrap complet de
    // l'application (aucune base de donnees requise). __DIR__ = api/tests/Arch.
    $root = dirname(__DIR__, 2).'/app/Modules';
    if (! is_dir($root)) {
        return [];
    }

    $files = [];
    $finder = (new Finder)
        ->files()
        ->in($root)
        ->path('/\/'.$layer.'\//')
        ->name('*.php');

    foreach ($finder as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

/**
 * Imports `use` d'un fichier, normalisés.
 *
 * @return list<string>
 */
function archImports(string $contents): array
{
    preg_match_all('/^use\s+([^;]+);/m', $contents, $matches);

    return array_map(static fn (string $m): string => trim($m), $matches[1]);
}

/**
 * Exceptions documentées : chemin relatif à app/Modules => justification.
 *
 * @return array<string, string>
 */
function archAllowlist(): array
{
    return [
        // Le contrat de passerelle de paiement expose des types du domaine
        // Accounting lui-même (montants, devises) : c'est un port, pas une
        // dépendance à la couche Application.
        'Accounting/Domain/Contracts/PaymentGatewayInterface.php' => 'port de domaine exposant ses propres types',
        // Le stockage de fichiers renvoie un UploadedFile Laravel : le contrat
        // est consommé par la couche Interfaces, le type HTTP est le contrat.
        'Library/Domain/Contracts/LibraryFileStorage.php' => 'port de stockage, type HTTP dans la signature',
        // Table de correspondance de locales (données statiques, aucun I/O).
        'Showcase/Domain/Support/ShowcaseLocales.php' => 'table de locales statique',
        // Le modèle écrit une invitation : accès DB direct assumé et documenté
        // (contrainte de transaction sur l'invitation).
        'HR/Domain/Models/UserInvitation.php' => 'écriture transactionnelle assumée',
        // DTOs de la couche Application typés sur les FormRequests de la couche
        // Interfaces : dette connue, à extraire vers des DTOs autonomes.
        'Attendance/Application/DTOs/CheckInDTO.php' => 'dette : DTO typé sur un FormRequest',
        'HR/Application/DTOs/CreateEmployeeDTO.php' => 'dette : DTO typé sur un FormRequest',
        'HR/Application/DTOs/UpdateEmployeeDTO.php' => 'dette : DTO typé sur un FormRequest',
    ];
}

/**
 * @param  array<string, string>  $files
 * @param  list<string>  $forbiddenPrefixes
 * @return list<string>
 */
function archViolations(array $files, array $forbiddenPrefixes): array
{
    $allow = archAllowlist();
    $violations = [];

    foreach ($files as $path => $contents) {
        if (isset($allow[$path])) {
            continue;
        }

        foreach (archImports($contents) as $import) {
            foreach ($forbiddenPrefixes as $prefix) {
                if (str_starts_with($import, $prefix)) {
                    $violations[] = $path.' → '.$import;
                }
            }
        }
    }

    return $violations;
}

it('interdit à la couche Domain d\'importer Infrastructure', function (): void {
    $violations = archViolations(archLayerFiles('Domain'), ['App\\Modules\\']);

    // Seules les dépendances INTRA-module vers Infrastructure sont interdites :
    // on filtre les imports vers un autre module (règle séparée ci-dessous).
    $violations = array_values(array_filter(
        $violations,
        static function (string $v): bool {
            [$path, $import] = explode(' → ', $v, 2);
            $module = explode('/', $path)[0];

            return str_starts_with($import, 'App\\Modules\\'.$module.'\\Infrastructure');
        },
    ));

    expect($violations)->toBe([], "Domain ne doit pas dépendre d'Infrastructure :\n".implode("\n", $violations));
});

it('interdit à la couche Domain d\'importer Interfaces', function (): void {
    $violations = archViolations(archLayerFiles('Domain'), ['App\\Modules\\']);

    $violations = array_values(array_filter(
        $violations,
        static function (string $v): bool {
            [$path, $import] = explode(' → ', $v, 2);
            $module = explode('/', $path)[0];

            return str_starts_with($import, 'App\\Modules\\'.$module.'\\Interfaces');
        },
    ));

    expect($violations)->toBe([], "Domain ne doit pas dépendre d'Interfaces :\n".implode("\n", $violations));
});

it('interdit à la couche Application d\'importer Interfaces', function (): void {
    $violations = archViolations(archLayerFiles('Application'), ['App\\Modules\\']);

    $violations = array_values(array_filter(
        $violations,
        static function (string $v): bool {
            [$path, $import] = explode(' → ', $v, 2);
            $module = explode('/', $path)[0];

            return str_starts_with($import, 'App\\Modules\\'.$module.'\\Interfaces');
        },
    ));

    expect($violations)->toBe([], "Application ne doit pas dépendre d'Interfaces :\n".implode("\n", $violations));
});

it('interdit les imports croisés entre Domain de modules différents', function (): void {
    // RATCHET. Les 9 violations ci-dessous sont des relations Eloquent
    // inter-modules (un modèle qui `belongsTo` le modèle d'un autre module).
    // Les casser demanderait de remplacer chaque relation par une projection
    // ou un identifiant nu — un chantier de modélisation, pas un correctif de
    // style. On fige donc la dette : elle ne peut que DÉCROÎTRE. Retirer une
    // ligne quand la relation est remplacée.
    $knownDebt = [
        'Attendance/Domain/Models/AttendanceLog.php → App\Modules\Planning\Domain\Models\Schedule',
        'Billing/Domain/Models/Invoice.php → App\Modules\Payroll\Domain\Models\Payment',
        'Billing/Domain/Models/Partner.php → App\Modules\Payroll\Domain\Models\Commission',
        'Expense/Domain/Models/ExpenseAccountingEntry.php → App\Modules\Planning\Domain\Models\ExpenseClaim',
        'Notification/Domain/Models/CompanyAnnouncement.php → App\Modules\HR\Domain\Models\Department',
        'Payroll/Domain/Models/Commission.php → App\Modules\Billing\Domain\Models\Partner',
        'Payroll/Domain/Models/Payment.php → App\Modules\Billing\Domain\Models\Invoice',
        'Recruitment/Domain/Models/JobPosting.php → App\Modules\HR\Domain\Models\Department',
        'Recruitment/Domain/Models/JobPosting.php → App\Modules\HR\Domain\Models\Position',
    ];

    $files = archLayerFiles('Domain');
    $allow = archAllowlist();
    $violations = [];

    foreach ($files as $path => $contents) {
        if (isset($allow[$path])) {
            continue;
        }
        $module = explode('/', $path)[0];

        foreach (archImports($contents) as $import) {
            if (! str_starts_with($import, 'App\\Modules\\')) {
                continue;
            }
            $target = explode('\\', $import)[2] ?? '';
            if ($target !== '' && $target !== $module) {
                $violations[] = $path.' → '.$import;
            }
        }
    }

    $new = array_values(array_diff($violations, $knownDebt));

    expect($new)->toBe([], 'Nouvel import croisé entre Domain de modules (dette figée : '.count($knownDebt).") :\n".implode("\n", $new));
});

it('interdit à la couche Domain d\'utiliser les facades HTTP et DB', function (): void {
    $violations = archViolations(archLayerFiles('Domain'), [
        'Illuminate\\Http\\',
        'Illuminate\\Support\\Facades\\DB',
    ]);

    expect($violations)->toBe([], "Domain ne doit pas dépendre de HTTP ni de la facade DB :\n".implode("\n", $violations));
});
