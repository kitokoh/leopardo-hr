<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\AI\EnsureAIAnalyticsAccess;
use App\Http\Middleware\Web\EnsureManagerRoleMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * #4812 (audit SWEQA-3) — les messages 403 des middlewares de rôle passent
 * par le catalogue errors.* ×4 locales (avant : littéraux FR/EN en dur,
 * résidu du #4690 clôturé sans correctif).
 */
class ManagerRoleLocalizationTest extends TestCase
{
    public function test_manager_role_required_is_localized_french_by_default(): void
    {
        $this->app->setLocale('fr');

        try {
            (new EnsureManagerRoleMiddleware())->handle(
                Request::create('/api/v1/test', 'GET'),
                fn () => response('ok')
            );
            $this->fail('Expected 403 HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame(__('errors.MANAGER_ROLE_REQUIRED'), $e->getMessage());
            $this->assertSame('Accès réservé aux managers.', $e->getMessage());
        }
    }

    public function test_manager_role_required_follows_english_locale(): void
    {
        $this->app->setLocale('en');

        try {
            (new EnsureManagerRoleMiddleware())->handle(
                Request::create('/api/v1/test', 'GET'),
                fn () => response('ok')
            );
            $this->fail('Expected 403 HttpException');
        } catch (HttpException $e) {
            $this->assertSame('Manager access required.', $e->getMessage());
        }
    }

    public function test_ai_analytics_access_is_localized(): void
    {
        $this->app->setLocale('en');

        try {
            (new EnsureAIAnalyticsAccess())->handle(
                Request::create('/api/v1/ai/analytics', 'GET'),
                fn () => response('ok')
            );
            $this->fail('Expected 403 HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('AI analytics access requires Principal or RH manager role.', $e->getMessage());
        }
    }
}
