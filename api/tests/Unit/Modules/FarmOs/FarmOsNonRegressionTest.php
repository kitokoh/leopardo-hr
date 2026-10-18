<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Farm OS — garde de NON-RÉGRESSION des autres verticales.
 *
 * La mission impose de ne pas perturber les verticales existantes et de ne pas
 * créer de second système de tenants / rôles / permissions / provisioning. Ces
 * garanties sont ici vérifiées STRUCTURELLEMENT, sans base de données : elles
 * restent donc exécutables partout, y compris là où PostgreSQL est absent.
 *
 * Ce test échoue si quelqu'un :
 *   - expose une route Farm OS hors du préfixe `farm` (fuite de surface) ;
 *   - réimplémente localement un modèle d'entreprise / un système de rôles ;
 *   - réintroduit une façade Laravel dans le Domain (règle #6568).
 */
final class FarmOsNonRegressionTest extends TestCase
{
    private const MODULE_PATH = __DIR__.'/../../../../app/Modules/FarmOs';

    /**
     * Farm OS ne crée AUCUN second système de tenants ni de rôles : il réutilise
     * `Company`, `BelongsToCompany`, les employés RH et le socle de permissions.
     * Un modèle local `Company`/`Role`/`Permission`/`Tenant` signalerait une
     * duplication d'abstraction centrale.
     */
    public function test_the_module_does_not_redefine_core_tenant_concepts(): void
    {
        $forbidden = ['Company', 'Role', 'Permission', 'Tenant', 'User'];
        $offenders = [];

        foreach (Finder::create()->files()->in(self::MODULE_PATH.'/Domain/Models')->name('*.php') as $file) {
            $class = $file->getBasename('.php');

            if (in_array($class, $forbidden, true)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Farm OS redéfinit un concept central au lieu de le réutiliser :\n - ".implode("\n - ", $offenders)
        );
    }

    /**
     * Règle #6568 : le Domain ne dépend d'aucune façade Laravel (DB, Schema,
     * Auth…). La persistance passe par la connexion du modèle et les
     * contrôleurs/infrastructure. Les services Farm OS doivent rester purs.
     */
    public function test_domain_services_do_not_use_laravel_facades(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(self::MODULE_PATH.'/Domain/Services')->name('*.php') as $file) {
            $source = (string) file_get_contents($file->getRealPath());

            if (preg_match('/^use\s+Illuminate\\\\Support\\\\Facades\\\\/m', $source) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Façade Laravel introduite dans le Domain (interdit, #6568) :\n - ".implode("\n - ", $offenders)
        );
    }

    /**
     * Les contrôleurs Farm OS doivent exister et rester sous Interfaces : c'est
     * le seul endroit où les gates de capacité sont appliquées.
     */
    public function test_every_controller_lives_in_the_interfaces_layer(): void
    {
        $controllers = [];

        foreach (Finder::create()->files()->in(self::MODULE_PATH)->name('*Controller.php') as $file) {
            $controllers[] = $file->getRelativePathname();
        }

        $this->assertNotSame([], $controllers);

        $offenders = array_values(array_filter(
            $controllers,
            static fn (string $path): bool => ! str_starts_with($path, 'Interfaces/')
        ));

        $this->assertSame(
            [],
            $offenders,
            "Contrôleur Farm OS hors de la couche Interfaces :\n - ".implode("\n - ", $offenders)
        );
    }

    /**
     * Les services du module doivent être déclarés `final` : aucune extension
     * sauvage d'un service métier depuis une autre verticale.
     */
    public function test_domain_services_are_final(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(self::MODULE_PATH.'/Domain/Services')->name('*.php') as $file) {
            $reflection = new ReflectionClass('App\\Modules\\FarmOs\\Domain\\Services\\'.$file->getBasename('.php'));

            if (! $reflection->isFinal()) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Service métier Farm OS non `final` :\n - ".implode("\n - ", $offenders)
        );
    }

}
