<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-32 HOSPITALITY (HOSP-006, #7948) — vitrine publique /stay, surface
 * sans auth `/public/hospitality/*` :
 *  - fiche par slug fail-closed (dépublié, inactif, verticale off → 404
 *    uniforme), DTO strict sans ID tenant ;
 *  - disponibilités (422 dates invalides, shape HOSP-004) ;
 *  - réservation en ligne idempotente : montant SERVEUR (prix × nuits),
 *    pending + expires_at ~30 min, tracking_code présenté UNE seule fois
 *    (hash sha256 en base), rejeu → 200 sans le code ;
 *  - anti-overbooking : 409 HOSPITALITY_NO_AVAILABILITY ;
 *  - suivi/annulation par référence + code : 404 uniforme anti-énumération,
 *    aucune PII au-delà du nom du voyageur ;
 *  - aucune fuite cross-tenant.
 */
class PublicStaySurfaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private HospitalityProperty $property;

    private HospitalityRoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $otherCompany->setFeature('hospitality', true);
        $otherCompany->save();
        $this->otherCompany = $otherCompany;

        $this->property = $this->createProperty($this->company, true);
        $this->roomType = $this->createRoomType($this->property, 'STD', 'Standard');
    }

    private function createProperty(Company $company, bool $published): HospitalityProperty
    {
        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Hôtel Test',
            'code' => 'HTL-'.fake()->unique()->numerify('####'),
            'type' => 'hotel',
            'country' => 'FR',
            'currency' => 'EUR',
            'status' => HospitalityProperty::STATUS_ACTIVE,
            'is_public' => $published,
            'public_slug' => $published ? 'hotel-test-'.fake()->unique()->numerify('####') : null,
        ]);

        return $property;
    }

    private function createRoomType(HospitalityProperty $property, string $code, string $name): HospitalityRoomType
    {
        /** @var HospitalityRoomType $roomType */
        $roomType = HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'code' => $code,
            'name' => $name,
            'base_price_minor' => 12000,
            'currency' => 'EUR',
        ]);

        return $roomType;
    }

    private function createUnit(HospitalityProperty $property, ?HospitalityRoomType $roomType, string $code, string $status = 'available'): HospitalityUnit
    {
        /** @var HospitalityUnit $unit */
        $unit = HospitalityUnit::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'room_type_id' => $roomType?->getKey(),
            'code' => $code,
            'status' => $status,
        ]);

        return $unit;
    }

    /** @return array<string, mixed> */
    private function reservationPayload(array $overrides = []): array
    {
        return array_merge([
            'room_type_id' => $this->roomType->getKey(),
            'guest_name' => 'Awa Ndiaye',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-04',
            'idempotency_key' => 'stay-'.fake()->uuid(),
        ], $overrides);
    }

    // ── Fiche ────────────────────────────────────────────────────────

    public function test_show_404_on_unknown_slug(): void
    {
        $this->getJson('/api/v1/public/hospitality/properties/slug-inconnu')->assertNotFound();
    }

    public function test_show_404_when_property_is_not_public(): void
    {
        $privateProperty = $this->createProperty($this->company, false);
        // Slug connu en base mais jamais publié → 404 (pas 403 : rien ne
        // doit révéler l'existence de la ressource).
        HospitalityProperty::query()->withoutGlobalScopes()
            ->whereKey($privateProperty->getKey())
            ->update(['is_public' => false, 'public_slug' => 'slug-jamais-publie']);

        $this->getJson('/api/v1/public/hospitality/properties/slug-jamais-publie')->assertNotFound();
    }

    public function test_show_404_when_vertical_is_disabled(): void
    {
        /** @var Company $companyOff */
        $companyOff = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        // Pas de setFeature('hospitality') → verticale inactive.
        $offProperty = $this->createProperty($companyOff, true);

        $this->getJson('/api/v1/public/hospitality/properties/'.$offProperty->public_slug)
            ->assertNotFound();
    }

    public function test_show_returns_public_dto_without_tenant_identifiers(): void
    {
        $response = $this->getJson('/api/v1/public/hospitality/properties/'.$this->property->public_slug);

        $response->assertOk()
            ->assertJsonPath('data.slug', $this->property->public_slug)
            ->assertJsonPath('data.name', 'Hôtel Test')
            ->assertJsonPath('data.type', 'hotel')
            ->assertJsonPath('data.room_types.0.code', 'STD')
            ->assertJsonPath('data.room_types.0.base_price_minor', 12000)
            ->assertJsonPath('data.room_types.0.currency', 'EUR');

        // Aucun identifiant tenant ne doit fuiter (les ids de types de
        // chambre, eux, sont publics par contrat — le POST réservation
        // les consomme via `room_type_id`).
        $payload = json_encode($response->json('data'));
        $this->assertIsString($payload);
        $this->assertStringNotContainsString('company_id', $payload);
    }

    // ── Disponibilités ───────────────────────────────────────────────

    public function test_availability_422_when_to_is_not_after_from(): void
    {
        $this->getJson('/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/availability?from=2026-10-04&to=2026-10-01')
            ->assertUnprocessable();
    }

    public function test_availability_returns_capacity_and_prices(): void
    {
        $this->createUnit($this->property, $this->roomType, 'U-101');
        $this->createUnit($this->property, $this->roomType, 'U-102');

        $this->getJson('/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/availability?from=2026-10-01&to=2026-10-04')
            ->assertOk()
            ->assertJsonPath('data.0.room_type_id', $this->roomType->getKey())
            ->assertJsonPath('data.0.capacity', 2)
            ->assertJsonPath('data.0.held', 0)
            ->assertJsonPath('data.0.available', 2)
            ->assertJsonPath('data.0.base_price_minor', 12000);
    }

    // ── Réservation en ligne ─────────────────────────────────────────

    public function test_store_creates_pending_reservation_with_server_amount_and_one_time_tracking_code(): void
    {
        $this->createUnit($this->property, $this->roomType, 'U-101');

        $response = $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $this->reservationPayload()
        );

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.created', true)
            // Montant SERVEUR : 3 nuits × 12000 = 36000 (jamais de montant client).
            ->assertJsonPath('data.total_amount_minor', 36000)
            ->assertJsonPath('data.currency', 'EUR');

        $trackingCode = $response->json('data.tracking_code');
        $this->assertIsString($trackingCode);
        $this->assertNotEmpty($trackingCode);

        /** @var HospitalityReservation|null $reservation */
        $reservation = HospitalityReservation::query()->withoutGlobalScopes()
            ->where('reference', $response->json('data.reference'))
            ->first();

        $this->assertNotNull($reservation);
        $this->assertSame(HospitalityReservation::SOURCE_ONLINE, $reservation->source);
        // pending + expiration ≈ 30 min (fenêtre 29-31 pour la marge d'exécution).
        $this->assertNotNull($reservation->expires_at);
        $this->assertTrue($reservation->expires_at->greaterThan(now()->addMinutes(29)));
        $this->assertTrue($reservation->expires_at->lessThan(now()->addMinutes(31)));
        // Le code n'est JAMAIS stocké en clair — seulement son hash sha256.
        $this->assertSame(hash('sha256', $trackingCode), $reservation->tracking_code_hash);
    }

    public function test_store_replay_is_idempotent_and_never_returns_tracking_code_twice(): void
    {
        $this->createUnit($this->property, $this->roomType, 'U-101');
        $payload = $this->reservationPayload();

        $first = $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $payload
        )->assertCreated();

        $second = $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $payload
        );

        $second->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.reference', $first->json('data.reference'));

        $this->assertArrayNotHasKey('tracking_code', $second->json('data'));

        $this->assertSame(1, HospitalityReservation::query()->withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->count());
    }

    public function test_store_refuses_overbooking(): void
    {
        // Capacité 1 : une pending en ligne immobilise déjà l'inventaire.
        $this->createUnit($this->property, $this->roomType, 'U-101');

        $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $this->reservationPayload()
        )->assertCreated();

        $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $this->reservationPayload(['guest_name' => 'Second Client'])
        )->assertConflict();
    }

    public function test_store_422_when_room_type_belongs_to_another_property(): void
    {
        $otherProperty = $this->createProperty($this->company, true);
        $otherRoomType = $this->createRoomType($otherProperty, 'DLX', 'Deluxe');
        $this->createUnit($otherProperty, $otherRoomType, 'U-201');

        $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $this->reservationPayload(['room_type_id' => $otherRoomType->getKey()])
        )->assertUnprocessable();
    }

    // ── Suivi & annulation par code ──────────────────────────────────

    /** @return array{0: string, 1: string} référence + code de suivi */
    private function bookOnline(): array
    {
        $this->createUnit($this->property, $this->roomType, 'U-101');

        $response = $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $this->reservationPayload()
        )->assertCreated();

        return [$response->json('data.reference'), $response->json('data.tracking_code')];
    }

    public function test_track_404_on_wrong_code_or_unknown_reference_uniformly(): void
    {
        [$reference, $trackingCode] = $this->bookOnline();

        // Mauvais code sur une vraie référence…
        $this->getJson('/api/v1/public/hospitality/reservations/'.$reference.'?code=mauvais-code')
            ->assertNotFound();
        // …et bon code sur une fausse référence : MÊME 404 (anti-énumération).
        $this->getJson('/api/v1/public/hospitality/reservations/HRS-2026-ZZZZZZ?code='.$trackingCode)
            ->assertNotFound();
        // Code absent → 404 aussi (jamais de 422 qui révélerait le contrat).
        $this->getJson('/api/v1/public/hospitality/reservations/'.$reference)
            ->assertNotFound();
    }

    public function test_track_returns_minimal_dto_without_extra_pii(): void
    {
        [$reference, $trackingCode] = $this->bookOnline();

        $response = $this->getJson('/api/v1/public/hospitality/reservations/'.$reference.'?code='.$trackingCode);

        $response->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.guest_name', 'Awa Ndiaye')
            ->assertJsonPath('data.property.slug', $this->property->public_slug)
            ->assertJsonPath('data.room_type.code', 'STD');

        // Ni email, ni téléphone, ni IDs internes dans le suivi public.
        $payload = json_encode($response->json('data'));
        $this->assertIsString($payload);
        $this->assertStringNotContainsString('contact_email', $payload);
        $this->assertStringNotContainsString('contact_phone', $payload);
        $this->assertStringNotContainsString('company_id', $payload);
    }

    public function test_cancel_releases_reservation_then_409_on_repeat(): void
    {
        [$reference, $trackingCode] = $this->bookOnline();

        $this->postJson('/api/v1/public/hospitality/reservations/'.$reference.'/cancel', ['code' => $trackingCode])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Une réservation annulée n'immobilise plus l'inventaire.
        $this->postJson(
            '/api/v1/public/hospitality/properties/'.$this->property->public_slug.'/reservations',
            $this->reservationPayload(['guest_name' => 'Client Suivant'])
        )->assertCreated();

        // Re-annulation : transition invalide → 409 (la machine à états garde).
        $this->postJson('/api/v1/public/hospitality/reservations/'.$reference.'/cancel', ['code' => $trackingCode])
            ->assertConflict();

        // Annulation avec mauvais code → 404 uniforme.
        $this->postJson('/api/v1/public/hospitality/reservations/'.$reference.'/cancel', ['code' => 'mauvais'])
            ->assertNotFound();
    }

    // ── Isolation inter-tenant ───────────────────────────────────────

    public function test_no_cross_tenant_leak_between_public_surfaces(): void
    {
        // Établissement publié chez l'AUTRE tenant, avec réservation chez NOUS.
        $foreignProperty = $this->createProperty($this->otherCompany, true);
        $foreignRoomType = $this->createRoomType($foreignProperty, 'SUI', 'Suite');
        $this->createUnit($foreignProperty, $foreignRoomType, 'F-101');

        [$reference] = $this->bookOnline();

        // La réservation de NOTRE tenant n'apparaît dans aucune surface de
        // l'autre établissement…
        $this->getJson('/api/v1/public/hospitality/properties/'.$foreignProperty->public_slug.'/availability?from=2026-10-01&to=2026-10-04')
            ->assertOk()
            ->assertJsonPath('data.0.room_type_id', $foreignRoomType->getKey())
            ->assertJsonPath('data.0.held', 0);

        // …et le suivi par référence reste introuvable sans le bon code.
        $this->getJson('/api/v1/public/hospitality/reservations/'.$reference.'?code=code-invente')
            ->assertNotFound();
    }
}
