<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\EmployeeCreated;
use App\Listeners\WebhookListener;
use App\Modules\Billing\Infrastructure\Services\WebhookDispatcher;
use Mockery;
use Tests\TestCase;

/**
 * #7655 (tranche 3, bugs latents) — le listener de webhooks n'avait AUCUN test :
 * `resolveModel()` renvoyait `?object` et manipulait ensuite la valeur comme un
 * modèle Eloquent (`->company_id`, `->toArray()`), ce que l'analyse statique
 * signalait (baseline stricte) et qui aurait produit un TypeError sur un
 * événement porteur d'autre chose qu'un modèle.
 *
 * Ces cas verrouillent le contrat : modèle Eloquent + `company_id` → webhook
 * planifié avec le payload `toArray()` ; modèle sans `company_id` → aucun envoi ;
 * événement hors périmètre → aucun envoi.
 */
class WebhookListenerTest extends TestCase
{
    private const COMPANY_ID = '9f1c0a6e-2b4d-4e6a-8f7b-1c2d3e4f5a6b';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_employee_created_event_dispatches_webhook_with_model_payload(): void
    {
        $employee = (new Employee)->forceFill([
            'id' => 'c0ffee00-1111-4222-8333-444455556666',
            'company_id' => self::COMPANY_ID,
            'first_name' => 'Awa',
            'last_name' => 'Diop',
            'email' => 'awa.diop@example.com',
        ]);

        $dispatcher = Mockery::mock(WebhookDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (string $companyId, string $event, array $payload): bool {
                return $companyId === self::COMPANY_ID
                    && $event === 'employee.created'
                    && ($payload['company_id'] ?? null) === self::COMPANY_ID;
            });

        (new WebhookListener($dispatcher))->handle(new EmployeeCreated($employee));

        $this->assertTrue(true);
    }

    public function test_model_without_company_id_is_not_dispatched(): void
    {
        $employee = (new Employee)->forceFill([
            'id' => 'c0ffee00-1111-4222-8333-444455556667',
            'first_name' => 'Sans',
            'last_name' => 'Société',
        ]);

        $dispatcher = Mockery::mock(WebhookDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        (new WebhookListener($dispatcher))->handle(new EmployeeCreated($employee));

        $this->assertTrue(true);
    }

    public function test_event_outside_the_map_is_ignored(): void
    {
        $dispatcher = Mockery::mock(WebhookDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        (new WebhookListener($dispatcher))->handle(new \stdClass);

        $this->assertTrue(true);
    }
}
