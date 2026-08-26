<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5588 (durcissement) : le `device_code` kiosque est stocké HACHÉ
 * (SHA-256 déterministe, mutator sur AttendanceKiosk) — jamais en clair.
 * Le code clair n'est renvoyé qu'à la création (affichage sur la borne) et
 * les lookups (API + web) hachent le code présenté par la borne.
 */
class KioskDeviceCodeHashingTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_device_code_is_stored_hashed_not_plaintext(): void
    {
        $company = Company::factory()->create();

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Hashed Kiosk',
            'device_code' => 'KIOSK-HASH-1',
            'biometric_mode' => 'fingerprint',
            'sync_token_hash' => Hash::make('plain-token'),
            'status' => 'active',
        ]);

        $stored = $kiosk->fresh()->device_code;

        $this->assertNotSame('KIOSK-HASH-1', $stored, 'le device_code ne doit jamais être stocké en clair');
        $this->assertSame(hash('sha256', 'KIOSK-HASH-1'), $stored);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $stored);
    }

    public function test_hash_device_code_is_case_insensitive(): void
    {
        $this->assertSame(
            AttendanceKiosk::hashDeviceCode('kiosk-hash-1'),
            AttendanceKiosk::hashDeviceCode('KIOSK-HASH-1'),
        );
    }

    public function test_roster_lookup_accepts_plaintext_device_code_against_hashed_storage(): void
    {
        $company = Company::factory()->create();
        AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Lookup Kiosk',
            'device_code' => 'KIOSK-ROSTER-2',
            'biometric_mode' => 'fingerprint',
            'sync_token_hash' => Hash::make('tok-123'),
            'status' => 'active',
        ]);

        $this->withHeader('X-Kiosk-Token', 'tok-123')
            ->getJson('/api/v1/kiosks/KIOSK-ROSTER-2/roster')
            ->assertOk();
    }

    public function test_roster_lookup_rejects_unknown_device_code(): void
    {
        $this->withHeader('X-Kiosk-Token', 'tok-123')
            ->getJson('/api/v1/kiosks/UNKNOWN-CODE/roster')
            ->assertNotFound();
    }
}
