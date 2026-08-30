<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Illuminate\Support\Facades\URL;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-913 (#6425) — POST /travel/public/contact (formulaire public signé).
 *
 * Route publique (middleware `signed` + throttle, pattern restaurant/public/*) :
 * le `company` est un paramètre signé de l'URL — forger un lien pour un autre
 * tenant est impossible. Consentement email obligatoire (422), événement
 * `travel.contact.submitted.v1` publié, registre upserté côté tenant ciblé.
 */
class TravelPublicContactApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    private function signedUrl(string $companyId): string
    {
        return URL::temporarySignedRoute('travel.public.contact.store', now()->addHour(), ['company' => $companyId]);
    }

    private function validPayload(): array
    {
        return [
            'first_name' => 'Aline',
            'last_name' => 'Ngo',
            'email' => 'aline.public@example.com',
            'phone' => '+237699999999',
            'message' => 'Demande publique : informations sur la ligne Douala-Yaoundé.',
            'consent_email' => true,
        ];
    }

    public function test_public_contact_requires_valid_signature(): void
    {
        $this->postJson('/api/v1/travel/public/contact', $this->validPayload())
            ->assertStatus(403);
    }

    public function test_public_contact_rejects_forged_company_signature(): void
    {
        // URL signée pour le tenant A, mais signature rejouée sur un autre
        // paramètre company → la signature ne correspond plus → 403.
        $signed = URL::temporarySignedRoute('travel.public.contact.store', now()->addHour(), ['company' => $this->company->id]);

        $this->postJson($signed.'&company=00000000-0000-0000-0000-000000000001', $this->validPayload())
            ->assertStatus(403);
    }

    public function test_public_contact_publishes_event_and_registers_consent(): void
    {
        $url = $this->signedUrl((string) $this->company->id);

        $this->postJson($url, $this->validPayload())
            ->assertStatus(202)
            ->assertJson(['status' => 'received']);

        $event = TravelOutboxEvent::query()
            ->where('event_type', 'travel.contact.submitted.v1')
            ->firstOrFail();
        self::assertSame('aline.public@example.com', $event->payload_redacted['email']);
        self::assertSame($this->company->id, $event->company_id);

        $contact = TravelCustomerContact::query()->where('email', 'aline.public@example.com')->firstOrFail();
        self::assertTrue($contact->email_consent_given, 'consentement email enregistré');
        self::assertSame($this->company->id, $contact->company_id);
    }

    public function test_public_contact_requires_consent(): void
    {
        $url = $this->signedUrl((string) $this->company->id);
        $payload = $this->validPayload();
        unset($payload['consent_email']);

        $this->postJson($url, $payload)->assertStatus(422);
    }

    public function test_public_contact_unknown_company_is_404(): void
    {
        $url = $this->signedUrl('00000000-0000-0000-0000-000000000000');

        $this->postJson($url, $this->validPayload())->assertStatus(404);
    }

    public function test_public_contact_writes_only_to_target_tenant(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($other, function (): void {
            TravelCustomerContact::factory()->create(['email' => 'existante.autre@example.com']);
        });

        $this->postJson($this->signedUrl((string) $other->id), $this->validPayload())
            ->assertStatus(202);

        self::assertSame(
            1,
            TravelCustomerContact::query()->where('email', 'aline.public@example.com')->count(),
            'contact créé uniquement chez le tenant ciblé',
        );
        self::assertSame(
            $other->id,
            TravelCustomerContact::query()->where('email', 'aline.public@example.com')->firstOrFail()->company_id,
        );
    }
}
