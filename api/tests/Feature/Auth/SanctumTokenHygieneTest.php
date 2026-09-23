<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Testing\PendingCommand;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7655 (tranche 2, ADR-0025) — hygiène des tokens Sanctum.
 *
 * Le TTL de 30 jours glissants est une décision propriétaire (#7491,
 * gardée par SessionDurationTest). Cette classe verrouille les contrôles
 * compensatoires livrés avec l'ADR-0025 :
 *
 * 1. la purge quotidienne des tokens expirés (`sanctum:prune-expired`)
 *    est bien planifiée — sans elle, les hash de tokens expirés
 *    s'accumulent indéfiniment dans `personal_access_tokens` ;
 * 2. le préfixe `leo_` (détection de fuite par secret scanning) est le
 *    défaut et marque les nouveaux tokens ;
 * 3. le changement de préfixe est rétro-compatible : un token émis SANS
 *    préfixe reste résoluble après activation du préfixe (le hash stocké
 *    est figé à la création).
 */
class SanctumTokenHygieneTest extends TestCase
{
    use CreatesMvpSchema;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->employee = $employee;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_expired_token_pruning_is_scheduled_daily(): void
    {
        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('schedule:list');
        $cmd->expectsOutputToContain('sanctum:prune-expired');
        $cmd->assertExitCode(0);
    }

    public function test_default_token_prefix_is_leo(): void
    {
        // Garde de configuration : si le défaut retombe à '' (aucun
        // marqueur secret-scanning), ce test le signale.
        $this->assertSame('leo_', config('sanctum.token_prefix'));
    }

    public function test_new_tokens_carry_the_prefix_and_resolve(): void
    {
        config(['sanctum.token_prefix' => 'leo_']);

        $newToken = $this->employee->createToken('api');

        [, $plain] = explode('|', $newToken->plainTextToken, 2);
        $this->assertStringStartsWith('leo_', $plain);

        $found = PersonalAccessToken::findToken($newToken->plainTextToken);
        $this->assertNotNull($found);
        $this->assertSame($newToken->accessToken->getKey(), $found->getKey());
    }

    public function test_legacy_tokens_without_prefix_survive_prefix_activation(): void
    {
        // Token émis avant l'introduction du préfixe…
        config(['sanctum.token_prefix' => '']);
        $legacy = $this->employee->createToken('api');

        // …le préfixe est ensuite activé : le token historique doit
        // rester résoluble tel que le client le présente.
        config(['sanctum.token_prefix' => 'leo_']);

        $found = PersonalAccessToken::findToken($legacy->plainTextToken);
        $this->assertNotNull($found);
        $this->assertSame($legacy->accessToken->getKey(), $found->getKey());
    }
}
