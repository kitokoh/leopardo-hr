<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Interfaces\Api\V1\Controllers\KioskController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-019 (#8204) — critère d'acceptation 3 : après une exception pendant
 * une opération kiosk, la requête suivante retrouve le search_path correct.
 *
 * Le contrôleur est appelé DIRECTEMENT (hors kernel HTTP) pour éprouver sa
 * propre restauration : le middleware `EnsureKioskSearchPathReset` (#3368)
 * restaurerait de toute façon en fin de requête et masquerait une fuite —
 * il reste un filet, pas une permission.
 */
final class KioskSearchPathLeakTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Company Leak',
            'slug' => 'company-leak-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-leak.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');
    }

    public function test_search_path_restored_when_device_unknown(): void
    {
        $before = $this->normalizedSearchPath();

        $request = Request::create('/api/v1/kiosks/UNKNOWN/roster', 'GET');
        $request->headers->set('X-Kiosk-Token', Str::random(48));

        try {
            app(KioskController::class)->roster($request, strtoupper(Str::random(10)));
            $this->fail('Un appareil inconnu doit lever une exception (firstOrFail).');
        } catch (ModelNotFoundException) {
            // attendu : levée DANS le scope de bascule du contrôleur
        }

        $this->assertSame($before, $this->normalizedSearchPath());
    }

    public function test_search_path_restored_when_token_invalid(): void
    {
        [$deviceCode] = $this->createKiosk();
        $before = $this->normalizedSearchPath();

        $request = Request::create('/api/v1/kiosks/'.$deviceCode.'/roster', 'GET');
        $request->headers->set('X-Kiosk-Token', 'mauvais-token');

        try {
            app(KioskController::class)->roster($request, $deviceCode);
            $this->fail('Un token invalide doit aboutir à un abort 401.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('INVALID_KIOSK_TOKEN', $e->getMessage());
        }

        $this->assertSame($before, $this->normalizedSearchPath());
    }

    public function test_search_path_restored_after_successful_roster(): void
    {
        [$deviceCode, $syncToken] = $this->createKiosk();
        $before = $this->normalizedSearchPath();

        $request = Request::create('/api/v1/kiosks/'.$deviceCode.'/roster', 'GET');
        $request->headers->set('X-Kiosk-Token', $syncToken);

        $response = app(KioskController::class)->roster($request, $deviceCode);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($before, $this->normalizedSearchPath());
    }

    /**
     * search_path normalisé (guillemets/espaces variables selon la provenance).
     */
    private function normalizedSearchPath(): string
    {
        $path = DB::scalar('SHOW search_path');

        return is_string($path) ? str_replace(['"', ' '], '', $path) : '';
    }

    /**
     * @return array{0: string, 1: string} [device_code en clair, sync_token en clair]
     */
    private function createKiosk(): array
    {
        $plainDeviceCode = strtoupper(Str::random(10));
        $plainToken = Str::random(48);

        AttendanceKiosk::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Entree Leak',
            'biometric_mode' => 'fingerprint',
            'device_code' => AttendanceKiosk::hashDeviceCode($plainDeviceCode),
            'sync_token_hash' => Hash::make($plainToken),
            'status' => 'active',
        ]);

        return [$plainDeviceCode, $plainToken];
    }
}
