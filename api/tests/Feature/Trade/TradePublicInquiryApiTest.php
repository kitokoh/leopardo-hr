<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Modules\CRM\Domain\Models\CrmLead;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Demande de devis d'un visiteur PUBLIC — #9779.
 *
 * ## Le defaut que ce test protege
 *
 * La fiche publique et son catalogue rendaient le vendeur VISIBLE, mais un
 * acheteur qui trouvait le bon fournisseur n'avait aucun moyen de le joindre.
 * Le catalogue etait une vitrine sans porte : la visite ne produisait jamais
 * de contact, donc le vendeur ne pouvait pas etre promu.
 *
 * ## Ce qui est verifie ici
 *
 * - une demande publique cree un lead CRM dans le tenant du VENDEUR, avec
 *   `source = trade.public.inquiry` (sinon la fiche n'est pas mesurable) ;
 * - le message et le produit vise sont conserves (le lead reste exploitable) ;
 * - la reponse est un `202` uniforme, sans identifiant de lead ;
 * - un slug inconnu renvoie 404, indistinguable d'un profil depublie ;
 * - un visiteur ne peut pas fixer `company_id` ni `source` par le corps.
 */
class TradePublicInquiryApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_a_public_inquiry_creates_a_lead_in_the_seller_tenant(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $slug = $this->publishPublicProfile();

        // Le visiteur n'est PAS authentifie : on quitte la session employee
        // pour eprouver la route telle qu'elle est appelee en production.
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/v1/public/trade/companies/{$slug}/inquiries", [
            'name' => 'Awa Diallo',
            'email' => 'Awa.Diallo@Example.com',
            'company_name' => 'Sahel Trading',
            'phone' => '+221 77 000 00 00',
            'message' => 'Nous cherchons 20 tonnes de cacao, livraison Dakar.',
            'product_name' => 'Cacao brut',
        ])
            ->assertStatus(202)
            ->assertJsonPath('status', 'received')
            // Aucun identifiant de lead : un visiteur ne doit pas pouvoir
            // compter les demandes deposes chez un vendeur.
            ->assertJsonMissingPath('data');

        $lead = CrmLead::query()->where('email', 'awa.diallo@example.com')->first();

        self::assertNotNull($lead, 'Le lead doit exister dans le tenant du vendeur.');
        self::assertSame('trade.public.inquiry', $lead->source);
        self::assertSame('new', $lead->status);
        self::assertSame('Awa', $lead->first_name);
        self::assertSame('Diallo', $lead->last_name);
        self::assertSame('Sahel Trading', $lead->company_name);

        // Le message et le produit vise sont conserves : sans eux, on saurait
        // qu'on a ete contacte, pas pourquoi.
        self::assertStringContainsString('20 tonnes de cacao', (string) $lead->notes);
        self::assertStringContainsString('Cacao brut', (string) $lead->notes);
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->postJson('/api/v1/public/trade/companies/slug-inexistant/inquiries', [
            'name' => 'Test Visiteur',
            'email' => 'visiteur@example.com',
            'message' => 'Bonjour, je souhaite un devis.',
        ])->assertStatus(404);
    }

    public function test_a_visitor_cannot_choose_the_tenant_or_the_source(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $slug = $this->publishPublicProfile();

        $this->app['auth']->forgetGuards();

        // `company_id` et `source` ne sont pas dans les regles de validation :
        // ils sont ignores, jamais appliques. Un visiteur ne peut donc pas
        // deposer un lead chez un AUTRE tenant, ni maquiller l'origine.
        $this->postJson("/api/v1/public/trade/companies/{$slug}/inquiries", [
            'name' => 'Test Visiteur',
            'email' => 'visiteur2@example.com',
            'message' => 'Bonjour, je souhaite un devis.',
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'source' => 'manuel',
        ])->assertStatus(202);

        $lead = CrmLead::query()->where('email', 'visiteur2@example.com')->first();

        self::assertNotNull($lead);
        self::assertSame('trade.public.inquiry', $lead->source);
        self::assertSame($company->id, $lead->company_id);
    }

    public function test_an_invalid_payload_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $slug = $this->publishPublicProfile();

        $this->app['auth']->forgetGuards();

        // Email invalide et message trop court : la validation est la seule
        // barriere avant l'ecriture d'un lead dans le tenant du vendeur.
        $this->postJson("/api/v1/public/trade/companies/{$slug}/inquiries", [
            'name' => 'X',
            'email' => 'pas-un-email',
            'message' => 'court',
        ])->assertStatus(422);
    }

    /**
     * Publie la fiche publique du tenant courant et retourne son slug.
     */
    private function publishPublicProfile(): string
    {
        $slug = 'vendeur-'.substr(md5((string) microtime(true)), 0, 8);

        // `public_slug` et `public_name` appartiennent a l'acte de PUBLICATION,
        // pas au formulaire de profil (voir TradeCompanyProfileTest).
        $this->putJson('/api/v1/trade/profile', ['import_enabled' => true])->assertStatus(200);

        $this->putJson('/api/v1/trade/profile/publication', [
            'is_public' => true,
            'public_slug' => $slug,
            'public_name' => 'Vendeur Test B2B',
            'public_city' => 'Dakar',
        ])->assertStatus(200);

        return $slug;
    }
}
