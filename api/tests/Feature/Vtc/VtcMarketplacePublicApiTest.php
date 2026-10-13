<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Domain\Models\VtcMarketplaceOperator;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * #8423 (M-15) — Marketplace publique VTC : tests Feature des endpoints
 * publics (`/api/v1/public/vtc/marketplace/*`, Phase 2).
 *
 *   - annuaire : seuls les opérateurs OPT-IN (annuaire actif + feature
 *     `vtc` + société saine) sont listés — DTO strict sans id interne ;
 *   - devis : résolu par slug opérateur, 404 uniforme hors opt-in, grille
 *     tarifaire appliquée côté serveur ;
 *   - réservation invitée : 201 + `tracking_code` présenté UNE SEULE FOIS,
 *     rejeu même `idempotency_key` → 200 sans code, une seule course ;
 *   - suivi : code absent → 422 ; mauvais code, référence inconnue ou
 *     société retirée de l'annuaire → 404 uniforme (anti-énumération).
 */
class VtcMarketplacePublicApiTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Le listener VtcRideRequested enfile le job de dispatch — faked :
        // ces tests couvrent la surface publique, pas le moteur.
        Queue::fake();
    }

    public function test_operators_directory_lists_only_optin_active_companies(): void
    {
        $visible = $this->createOperator('express-vtc-douala', 'Express VTC Douala');
        // Opt-in annuaire mais feature `vtc` coupée → exclu (kill switch).
        $this->createOperator('vtc-sans-flag', 'VTC Sans Flag', enableVtc: false);
        // Feature active mais JAMAIS publié dans l'annuaire → exclu.
        $this->createOperator('vtc-non-publie', 'VTC Non Publié', publish: false);
        // Publication repassée inactive → exclu.
        $this->createOperator('vtc-desactive', 'VTC Désactivé', operatorStatus: VtcMarketplaceOperator::STATUS_INACTIVE);

        $response = $this->getJson('/api/v1/public/vtc/marketplace/operators')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response
            ->assertJsonPath('data.0.slug', 'express-vtc-douala')
            ->assertJsonPath('data.0.name', 'Express VTC Douala')
            ->assertJsonPath('data.0.currency', 'XAF')
            // DTO strict : aucun identifiant interne exposé.
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_estimate_is_bounded_to_optin_operator_and_server_side_fare(): void
    {
        $operator = $this->createOperator('express-vtc-douala', 'Express VTC Douala');
        $this->seedDefaultFareProfile($operator);
        $this->createOperator('vtc-non-publie', 'VTC Non Publié', publish: false);

        // Devis sur l'opérateur opt-in : distance/durée + prix minor units.
        $estimate = $this->postJson('/api/v1/public/vtc/marketplace/estimate', $this->estimatePayload('express-vtc-douala'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['distance_m', 'duration_s', 'price_minor', 'currency']]);

        $distanceM = $estimate->json('data.distance_m');
        $priceMinor = $estimate->json('data.price_minor');
        self::assertTrue(is_numeric($distanceM));
        self::assertTrue(is_numeric($priceMinor));
        self::assertEqualsWithDelta(1_570, (int) $distanceM, 60.0);
        self::assertGreaterThan(1_200_00, (int) $priceMinor);
        self::assertLessThan(1_400_00, (int) $priceMinor);
        self::assertSame('XAF', $estimate->json('data.currency'));

        // Opérateur connu mais non publié → 404 uniforme ; slug inconnu idem.
        $this->postJson('/api/v1/public/vtc/marketplace/estimate', $this->estimatePayload('vtc-non-publie'))
            ->assertStatus(404);
        $this->postJson('/api/v1/public/vtc/marketplace/estimate', $this->estimatePayload('slug-inconnu'))
            ->assertStatus(404);

        // Forme invalide → 422.
        $this->postJson('/api/v1/public/vtc/marketplace/estimate', ['operator' => 'express-vtc-douala'])
            ->assertStatus(422);
    }

    public function test_guest_ride_creation_presents_tracking_code_once_and_is_idempotent(): void
    {
        $operator = $this->createOperator('express-vtc-douala', 'Express VTC Douala');
        $this->seedDefaultFareProfile($operator);

        $key = (string) Str::uuid();

        $created = $this->postJson('/api/v1/public/vtc/marketplace/rides', [
            'operator' => 'express-vtc-douala',
            'idempotency_key' => $key,
            'pickup' => ['lat' => 4.0511, 'lng' => 9.7679],
            'dropoff' => ['lat' => 4.0611, 'lng' => 9.7779],
            'pickup_address' => 'Akwa, Douala',
            'dropoff_address' => 'Bonanjo, Douala',
            'passenger_name' => 'Invité Marketplace',
            'passenger_phone' => '+237690000001',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'dispatching')
            // DTO strict : pas de PII passager exposée.
            ->assertJsonMissingPath('data.passenger_name')
            ->assertJsonMissingPath('data.passenger_phone')
            ->assertJsonStructure(['data' => ['reference', 'status', 'tracking_code', 'estimated_price_minor', 'currency']]);

        $reference = $created->json('data.reference');
        self::assertIsString($reference);
        self::assertMatchesRegularExpression('/^VTC-\d{4}-\d{6}$/', $reference);

        $trackingCode = $created->json('data.tracking_code');
        self::assertIsString($trackingCode);
        self::assertNotSame('', $trackingCode);

        // Course créée dans LE tenant de l'opérateur, passager invité (null).
        $this->withTenantContext($operator, function () use ($key): void {
            /** @var VtcRide $ride */
            $ride = VtcRide::query()->firstOrFail();
            self::assertNull($ride->passenger_user_id);
            self::assertSame(VtcRideStatus::Dispatching, $ride->status);
            self::assertSame($key, $ride->idempotency_key);
        });

        // Rejeu même clé → 200, MÊME course, code NON re-présenté (present-once).
        $replayed = $this->postJson('/api/v1/public/vtc/marketplace/rides', [
            'operator' => 'express-vtc-douala',
            'idempotency_key' => $key,
            'pickup' => ['lat' => 4.0511, 'lng' => 9.7679],
            'dropoff' => ['lat' => 4.0611, 'lng' => 9.7779],
            'passenger_name' => 'Invité Marketplace',
            'passenger_phone' => '+237690000001',
        ])->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonMissingPath('data.tracking_code');

        $this->withTenantContext($operator, function (): void {
            self::assertSame(1, VtcRide::query()->count());
        });
    }

    public function test_tracking_requires_code_and_never_leaks_existence(): void
    {
        $operator = $this->createOperator('express-vtc-douala', 'Express VTC Douala');
        $this->seedDefaultFareProfile($operator);

        [$reference, $trackingCode] = $this->createGuestRide('express-vtc-douala');

        // Code absent → 422 (défaut de forme).
        $this->getJson('/api/v1/public/vtc/marketplace/rides/'.$reference)
            ->assertStatus(422);

        // Mauvais code → 404 (identique à « référence inconnue »).
        $this->getJson('/api/v1/public/vtc/marketplace/rides/'.$reference.'?code=FAUX-CODE-000')
            ->assertStatus(404);

        // Référence inconnue → même 404.
        $this->getJson('/api/v1/public/vtc/marketplace/rides/VTC-2099-000000?code='.$trackingCode)
            ->assertStatus(404);

        // Bon couple référence + code → 200, DTO strict (JAMAIS de position).
        $this->getJson('/api/v1/public/vtc/marketplace/rides/'.$reference.'?code='.$trackingCode)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.status', 'dispatching')
            ->assertJsonMissingPath('data.driver')
            ->assertJsonMissingPath('data.tracking_code_hash');
    }

    public function test_tracking_is_cut_when_operator_leaves_the_directory(): void
    {
        $operator = $this->createOperator('express-vtc-douala', 'Express VTC Douala');
        $this->seedDefaultFareProfile($operator);

        [$reference, $trackingCode] = $this->createGuestRide('express-vtc-douala');

        $this->getJson('/api/v1/public/vtc/marketplace/rides/'.$reference.'?code='.$trackingCode)
            ->assertOk();

        // Retrait de l'annuaire → les courses se « dé-suivent » aussitôt
        // (fail-closed, gardes re-vérifiées à chaque lecture).
        VtcMarketplaceOperator::query()
            ->where('company_id', $operator->id)
            ->update(['status' => VtcMarketplaceOperator::STATUS_INACTIVE]);

        $this->getJson('/api/v1/public/vtc/marketplace/rides/'.$reference.'?code='.$trackingCode)
            ->assertStatus(404);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function createOperator(
        string $slug,
        string $name,
        bool $enableVtc = true,
        bool $publish = true,
        string $operatorStatus = VtcMarketplaceOperator::STATUS_ACTIVE,
    ): Company {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => $name,
            'slug' => $slug,
            'country' => 'CM',
            'currency' => 'XAF',
        ]);

        if ($enableVtc) {
            $company->setFeature('vtc', true);
            $company->save();
        }

        if ($publish) {
            VtcMarketplaceOperator::query()->create([
                'company_id' => $company->id,
                'status' => $operatorStatus,
            ]);
        }

        return $company;
    }

    private function seedDefaultFareProfile(Company $company): void
    {
        $this->withTenantContext($company, function (): void {
            VtcFareProfile::factory()->create([
                'base_minor' => 500_00,
                'per_km_minor' => 250_00,
                'per_minute_minor' => 50_00,
                'minimum_minor' => 1_000_00,
                'is_default' => true,
            ]);
        });
    }

    /**
     * Douala centre → ~1.57 km à vol d'oiseau (mêmes bornes que
     * VtcPassengerApiTest).
     *
     * @return array<string, mixed>
     */
    private function estimatePayload(string $operator): array
    {
        return [
            'operator' => $operator,
            'pickup' => ['lat' => 4.0511, 'lng' => 9.7679],
            'dropoff' => ['lat' => 4.0611, 'lng' => 9.7779],
        ];
    }

    /**
     * @return array{0: string, 1: string} [reference, tracking_code]
     */
    private function createGuestRide(string $operator): array
    {
        $created = $this->postJson('/api/v1/public/vtc/marketplace/rides', [
            'operator' => $operator,
            'idempotency_key' => (string) Str::uuid(),
            'pickup' => ['lat' => 4.0511, 'lng' => 9.7679],
            'dropoff' => ['lat' => 4.0611, 'lng' => 9.7779],
            'passenger_name' => 'Invité Marketplace',
            'passenger_phone' => '+237690000001',
        ])->assertCreated();

        $reference = $created->json('data.reference');
        self::assertIsString($reference);
        $trackingCode = $created->json('data.tracking_code');
        self::assertIsString($trackingCode);

        return [$reference, $trackingCode];
    }
}
