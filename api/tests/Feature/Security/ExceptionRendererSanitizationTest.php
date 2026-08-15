<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Issue #3810 — le renderer global d'exceptions HTTP ne doit jamais exposer
 * de message brut issu d'une exception interne (SQLSTATE, chemins serveur,
 * traces) dans les réponses JSON. Les messages statiques passés à abort()
 * restent exposés ; les messages à signature interne sont remplacés par un
 * code stable + message générique localisé, avec détail en log serveur.
 *
 * Contrat #3725/#3810 :
 *   1. Aucun getMessage() de RuntimeException/QueryException dans le JSON.
 *   2. Codes d'erreur stables (VALIDATION_FAILED, CONFLICT, SERVER_ERROR…).
 *   3. Aucun mot-clé interne (SQLSTATE, path, /var/www) dans les corps
 *      d'erreur.
 */
class ExceptionRendererSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->middleware('api')->group(function (): void {
            Route::get('/_test/leak-422', fn () => abort(422, 'SQLSTATE[23505]: Unique violation in /var/www/api/app/Modules/Payroll/Service.php on line 42'));
            Route::get('/_test/leak-500', fn () => abort(500, 'QueryException: could not find driver in /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Connection.php:712'));
            Route::get('/_test/leak-empty', fn () => abort(422, ''));
            Route::get('/_test/leak-stack', fn () => abort(500, "RuntimeException: boom\n#0 /var/www/api/app/Modules/Payroll/RunService.php(88): App\\Payroll\\RunService->calculate()\n#1 /var/www/api/app/Http/Controllers/PayrollRunController.php(120)"));
            Route::get('/_test/safe-message', fn () => abort(409, 'RATE_EDIT_LOCKED'));
        });
    }

    public function test_internal_sql_message_is_sanitized_on_422(): void
    {
        $response = $this->getJson('/api/_test/leak-422');

        $response->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_FAILED')
            ->assertJsonPath('message', 'VALIDATION_FAILED')
            ->assertJsonPath('localized_message', __('errors.VALIDATION_FAILED'))
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('/var/www');
    }

    public function test_query_exception_message_is_sanitized_on_500(): void
    {
        $response = $this->getJson('/api/_test/leak-500');

        $response->assertStatus(500)
            ->assertJsonPath('error', 'SERVER_ERROR')
            ->assertJsonPath('message', 'SERVER_ERROR')
            ->assertDontSee('QueryException')
            ->assertDontSee('/var/www')
            ->assertDontSee('vendor');
    }

    public function test_empty_abort_message_falls_back_to_stable_code(): void
    {
        $this->getJson('/api/_test/leak-empty')
            ->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_FAILED')
            ->assertJsonPath('message', 'VALIDATION_FAILED');
    }

    public function test_stack_trace_is_never_exposed(): void
    {
        $response = $this->getJson('/api/_test/leak-stack');

        $response->assertStatus(500)
            ->assertJsonPath('error', 'SERVER_ERROR')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('#0')
            ->assertDontSee('.php(');
    }

    public function test_static_abort_message_is_preserved(): void
    {
        // Les messages statiques/volontaires (codes stables, textes
        // localisés) passés à abort() restent exposés — c'est le contrat
        // existant (ex. SalaryAdvanceSecurityTest, TaxSlabAdminController).
        $this->getJson('/api/_test/safe-message')
            ->assertStatus(409)
            ->assertJsonPath('error', 'RATE_EDIT_LOCKED')
            ->assertJsonPath('message', 'RATE_EDIT_LOCKED');
    }
}
