<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #9698 — le login web super-admin (`POST /platform/login`) absorbait la
 * `ValidationException` levee par `$request->validate()` dans son
 * `catch (\Throwable)` (garde #6974) et affichait `INTERNAL_ERROR` au lieu du
 * message de champ. Meme defaut que #9687 cote API.
 *
 * Ces tests verrouillent le contrat : une saisie invalide doit produire une
 * redirection avec `errors` (et non un message d'erreur interne), et le filet
 * #6974 doit rester en place pour les vraies erreurs serveur.
 */
class PlatformWebAuthValidationContractTest extends TestCase
{
    use CreatesMvpSchema;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->superAdmin = new SuperAdmin([
            'name' => 'Test Super Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $this->superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @return array{_token: string} */
    private function csrf(): array
    {
        $this->get('/platform/login');

        return ['_token' => session()->token()];
    }

    public function test_missing_email_returns_field_error_not_internal_error(): void
    {
        $response = $this->post('/platform/login', array_merge($this->csrf(), [
            'email' => '',
            'password' => '',
        ]));

        $response->assertSessionHasErrors(['email', 'password']);

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringNotContainsString(
            'INTERNAL_ERROR',
            (string) $errors->first('email'),
            'La ValidationException a ete absorbee par le catch (Throwable) : #9698 est revenu.',
        );
    }

    public function test_invalid_email_format_returns_field_error(): void
    {
        $response = $this->post('/platform/login', array_merge($this->csrf(), [
            'email' => 'pas-un-email',
            'password' => 'secret',
        ]));

        $response->assertSessionHasErrors(['email']);
        $this->assertStringNotContainsString(
            'INTERNAL_ERROR',
            (string) session('errors')?->first('email'),
        );
    }

    public function test_validation_failure_does_not_log_an_unexpected_error(): void
    {
        // Le garde #6974 loggue sur le canal 'structured' quand il absorbe une
        // exception. Une saisie invalide ne doit PAS produire cette trace : on
        // lit le fichier de log du canal et on verifie qu'aucune nouvelle
        // entree n'est apparue pendant l'appel.
        $logPath = storage_path('logs/structured-'.now()->format('Y-m-d').'.log');
        $before = is_file($logPath) ? (int) filesize($logPath) : 0;

        $this->post('/platform/login', array_merge($this->csrf(), [
            'email' => '',
            'password' => '',
        ]));

        clearstatcache(true, $logPath);
        $after = is_file($logPath) ? (int) filesize($logPath) : 0;

        $this->assertSame(
            $before,
            $after,
            'Une saisie invalide a produit une entree dans le canal structured : '
            .'la ValidationException est de nouveau absorbee par le catch (Throwable).',
        );
    }

    public function test_wrong_credentials_still_show_invalid_credentials(): void
    {
        $response = $this->post('/platform/login', array_merge($this->csrf(), [
            'email' => 'inconnu@leopardo.test',
            'password' => 'mauvais-mot-de-passe',
        ]));

        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString(
            'Identifiants invalides',
            (string) session('errors')?->first('email'),
        );
    }

    public function test_valid_credentials_still_authenticate(): void
    {
        $response = $this->post('/platform/login', array_merge($this->csrf(), [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]));

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->superAdmin, 'super_admin_web');
    }

    public function test_controller_keeps_the_throwable_safety_net(): void
    {
        // Garde #6974 : le filet doit survivre au correctif #9698. On verifie
        // la presence du catch cible AVANT le catch (Throwable) dans la source,
        // sans provoquer d'erreur serveur reelle (non destructif).
        $source = (string) file_get_contents(
            app_path('Http/Controllers/Web/PlatformAuthController.php'),
        );

        $rethrowPos = strpos($source, 'catch (ValidationException|HttpExceptionInterface');
        $throwablePos = strpos($source, 'catch (\\Throwable');

        $this->assertNotFalse($rethrowPos, 'Le rethrow cible de #9698 a disparu.');
        $this->assertNotFalse($throwablePos, 'Le filet #6974 a disparu.');
        $this->assertLessThan(
            $throwablePos,
            $rethrowPos,
            'Le catch cible doit precede le catch (Throwable), sinon il ne sert a rien.',
        );
    }
}
