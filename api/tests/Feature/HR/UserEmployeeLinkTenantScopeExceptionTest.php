<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserEmployeeLink;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #8716 (audit readiness, ecart I-1) — VERROUILLE l'exception
 * tenant-scope de `UserEmployeeLink`.
 *
 * Pourquoi ce test existe : `UserEmployeeLink` est resolu pendant le LOGIN,
 * donc AVANT que le contexte tenant ne soit etabli (c'est justement cette
 * table qui permet de determiner la societe de l'utilisateur). Le modele ne
 * doit donc PAS porter `BelongsToCompany` : un scope fail-closed casserait
 * l'authentification (#7711).
 *
 * Le risque que ce test neutralise est double :
 *  1. un developeur « corrige » le scope manquant pour faire plaisir a la
 *     garde #7999 et casse silencieusement le login ;
 *  2. l'exception est retiree du registre de gouvernance sans que le
 *     commentaire de garde du modele soit mis a jour (derive documentaire).
 *
 * Si ce test devient rouge, ce n'est pas un test a adapter : c'est une
 * decision d'architecture a reprendre explicitement.
 */
class UserEmployeeLinkTenantScopeExceptionTest extends TestCase
{
    use RefreshTenantDatabase;

    private const MODEL = UserEmployeeLink::class;

    /**
     * Le modele ne doit PAS porter le trait de scope tenant : son absence est
     * le mecanisme meme de l'exception (resolution au login, hors contexte).
     */
    public function test_le_modele_ne_porte_pas_le_scope_tenant(): void
    {
        $traits = class_uses_recursive(self::MODEL);

        $this->assertArrayNotHasKey(
            BelongsToCompany::class,
            $traits,
            'UserEmployeeLink ne doit PAS utiliser BelongsToCompany : le lien User<->Employee '
            .'est resolu au login, avant tout contexte tenant. Un scope fail-closed ici '
            .'casserait l\'authentification (#7711, #8716).'
        );
    }

    /**
     * Le comportement verrouille : meme avec un autre tenant courant ET le
     * marqueur fail-closed actif (`tenant_scope_required`), le lien reste
     * lisible. C'est exactement ce qui permet au login de fonctionner.
     */
    public function test_le_lien_reste_lisible_hors_du_tenant_courant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        $user = $this->makeUser('login.exception@example.com');

        $link = UserEmployeeLink::query()->create([
            'user_id' => $user->id,
            'employee_id' => 424242,
            'company_id' => $companyA->id,
            'status' => 'active',
            'linked_at' => now(),
        ]);

        // Contexte tenant = B, marqueur fail-closed pose (comme sur la surface
        // API tenant via TenantMiddleware, #3727).
        app()->instance('current_company', $companyB);
        app()->instance('tenant_scope_required', true);

        $resolved = UserEmployeeLink::query()->whereKey($link->id)->first();

        $this->assertNotNull(
            $resolved,
            'Le lien doit rester lisible malgre un tenant courant different : c\'est la '
            .'condition du login (resolution de la societe de l\'utilisateur, #7711).'
        );
        $this->assertSame(
            $companyA->id,
            $resolved->company_id,
            'La societe restituee doit etre celle du lien, pas celle du tenant courant.'
        );
    }

    /**
     * La resolution par utilisateur (le parcours reel du login) doit rendre
     * TOUS les liens de l'utilisateur, sans filtrage par tenant courant.
     */
    public function test_la_resolution_par_utilisateur_couvre_tous_les_liens(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        $user = $this->makeUser('multi.tenant@example.com');

        foreach ([$companyA, $companyB] as $index => $company) {
            UserEmployeeLink::query()->create([
                'user_id' => $user->id,
                'employee_id' => 500000 + $index,
                'company_id' => $company->id,
                'status' => 'active',
                'linked_at' => now(),
            ]);
        }

        app()->instance('current_company', $companyA);
        app()->instance('tenant_scope_required', true);

        $links = UserEmployeeLink::query()->where('user_id', $user->id)->get();

        $this->assertCount(
            2,
            $links,
            'Un utilisateur multi-societe doit voir ses liens des DEUX societes au login : '
            .'sinon le choix de societe est impossible (#8716).'
        );
    }

    /**
     * Reference croisee obligatoire : le modele doit etre declare dans le
     * registre canonique de gouvernance, et son commentaire de garde doit
     * nommer cette exception. Une exception sans registre (ou l'inverse) est
     * exactement la derive que l'audit reproche.
     */
    public function test_l_exception_est_referencee_dans_le_registre_de_gouvernance(): void
    {
        $registry = base_path('../dev-hub/governance/tenant-scope-exceptions.json');

        $this->assertFileExists($registry, 'Le registre canonique tenant-scope est introuvable.');

        $raw = file_get_contents($registry);
        $this->assertNotFalse($raw, 'Le registre tenant-scope doit etre lisible.');

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded, 'Le registre tenant-scope doit etre un objet JSON valide.');

        $exceptions = $decoded['exceptions'] ?? [];
        $this->assertIsArray($exceptions);

        $declared = false;
        $reason = null;

        foreach ($exceptions as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['model'] ?? null) === self::MODEL) {
                $declared = true;
                $reason = is_string($entry['reason'] ?? null) ? $entry['reason'] : null;
                break;
            }
        }

        $this->assertTrue(
            $declared,
            'UserEmployeeLink doit figurer dans `exceptions` de tenant-scope-exceptions.json : '
            .'un modele hors scope tenant sans declaration fait echouer la garde #7999.'
        );
        $this->assertNotNull(
            $reason,
            'L\'exception doit porter une justification ecrite (champ `reason`).'
        );

        $source = file_get_contents(base_path('app/Modules/HR/Domain/Models/UserEmployeeLink.php'));
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            'EXCEPTION TENANT-SCOPE',
            $source,
            'Le modele doit porter le commentaire de garde « EXCEPTION TENANT-SCOPE » '
            .'attendu par dev-hub/tools/check-tenant-scope-models.sh (#7999, #8716).'
        );
        $this->assertStringContainsString(
            'tenant-scope-exceptions.json',
            $source,
            'Le commentaire de garde doit pointer vers le registre de gouvernance '
            .'(reference croisee exigee par #8716).'
        );
    }

    private function makeUser(string $email): User
    {
        $user = User::query()->forceCreate([
            'first_name' => 'Scope',
            'last_name' => 'Exception',
            'email' => $email,
            'password_hash' => Hash::make('password123'),
        ]);

        $user->forceFill(['status' => 'active'])->save();

        return $user;
    }
}
