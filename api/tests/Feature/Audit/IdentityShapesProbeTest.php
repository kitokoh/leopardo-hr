<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Sonde de forme de réponse — contre-revue de l'audit IAM.
 *
 * But unique : prouver PAR EXÉCUTION où se trouve réellement `token_expires_at`
 * dans la réponse de `POST /api/v1/auth/login`, afin de trancher si l'échec de
 * `SessionDurationTest::test_login_issues_a_token_expiring_in_thirty_days`
 * (`Failed asserting that null is of type string`, `:68`) est :
 *
 *   (a) un DÉFAUT du code de production (jeton sans expiration), ou
 *   (b) une ERREUR DE CHEMIN dans le test (`data.token_expires_at` alors que la
 *       clé est à la racine, à côté du wrapper `data`).
 *
 * Aucun code de production n'est modifié par ce test.
 */
#[Group('audit')]
class IdentityShapesProbeTest extends TestCase
{
    use CreatesMvpSchema;
    use RefreshDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_login_response_places_token_expires_at_outside_the_data_wrapper(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->manager->email,
            'password' => 'password123', // défaut EmployeeFactory
        ]);

        $response->assertOk();

        $json = $response->json();

        $atRoot = is_array($json) && array_key_exists('token_expires_at', $json);
        $underData = is_array($json)
            && isset($json['data'])
            && is_array($json['data'])
            && array_key_exists('token_expires_at', $json['data']);

        fwrite(STDERR, sprintf(
            "\n[PROBE] root_keys=%s\n[PROBE] token_expires_at@root=%s value=%s\n[PROBE] token_expires_at@data=%s value=%s\n",
            implode(',', is_array($json) ? array_keys($json) : ['<not-array>']),
            $atRoot ? 'YES' : 'NO',
            var_export($atRoot ? $json['token_expires_at'] : null, true),
            $underData ? 'YES' : 'NO',
            var_export($underData ? $json['data']['token_expires_at'] : null, true),
        ));

        $this->assertTrue(
            $atRoot || $underData,
            'token_expires_at est absent de la réponse : le jeton serait bien sans expiration.',
        );

        $raw = $atRoot ? $json['token_expires_at'] : $json['data']['token_expires_at'];
        $this->assertIsString($raw);

        $minutes = (int) abs(Carbon::parse($raw)->diffInMinutes(now()));
        fwrite(STDERR, sprintf("[PROBE] expires_in_minutes=%d (attendu 43200)\n", $minutes));

        $this->assertEqualsWithDelta(
            43200,
            $minutes,
            5,
            "L'expiration réelle s'écarte de 30 jours.",
        );

        $this->assertNotSame(
            $atRoot,
            $underData,
            'La clé ne peut pas être simultanément à la racine et sous data.',
        );
    }
}
