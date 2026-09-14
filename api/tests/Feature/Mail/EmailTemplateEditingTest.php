<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Core\Mail\EmailTemplateRegistry;
use App\Core\Mail\EmailTemplateRepository;
use App\Core\Mail\EmailTemplateResolver;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7347 — édition des e-mails depuis l'admin.
 *
 * Ce que ces tests verrouillent :
 *   1. **aucune surcharge = aucun changement** : un template non modifié rend
 *      exactement la valeur du catalogue (c'est la propriété la plus importante
 *      de la fonctionnalité : elle ne doit rien casser par défaut) ;
 *   2. la surcharge prime, champ par champ, et est traçable (`updated_by`) ;
 *   3. un champ vidé revient au défaut ;
 *   4. le corps saisi est du TEXTE : échappé au rendu, et seules les variables
 *      déclarées par le registre sont substituées (pas d'injection) ;
 *   5. le retour au défaut supprime la surcharge.
 */
class EmailTemplateEditingTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La table de surcharge est créée par migration ; en test on garantit sa
        // présence (et on repart d'un état vide pour l'isolation).
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.email_templates (
    id bigserial PRIMARY KEY,
    template_key varchar(80) NOT NULL,
    locale varchar(5) NOT NULL,
    subject text NULL,
    heading text NULL,
    body text NULL,
    cta_label varchar(160) NULL,
    updated_by varchar(180) NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT email_templates_key_locale_unique UNIQUE (template_key, locale)
)
SQL);
    }

    protected function tearDown(): void
    {
        DB::table('email_templates')->delete();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function resolver(): EmailTemplateResolver
    {
        return app(EmailTemplateResolver::class);
    }

    private function repository(): EmailTemplateRepository
    {
        return app(EmailTemplateRepository::class);
    }

    private function actingAsSuperAdmin(): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    public function test_sans_surcharge_le_template_rend_la_valeur_du_catalogue(): void
    {
        $resolved = $this->resolver()->resolve('password_reset', 'fr');

        $this->assertSame(
            (string) __('emails.email_password_reset_subject', [], 'fr'),
            $resolved->subject
        );
        $this->assertFalse($resolved->isOverridden('subject'));
        $this->assertFalse($resolved->isOverridden('body'));
        $this->assertTrue($this->resolver()->resolve('password_reset', 'fr')->body !== '');
    }

    public function test_la_surcharge_prime_champ_par_champ_et_est_tracable(): void
    {
        $this->repository()->upsert('password_reset', 'fr', [
            'subject' => 'Sujet personnalise',
        ], 'admin@leopardo.test');

        $resolved = $this->resolver()->resolve('password_reset', 'fr');

        $this->assertSame('Sujet personnalise', $resolved->subject);
        $this->assertTrue($resolved->isOverridden('subject'));
        // Les autres champs restent ceux du catalogue.
        $this->assertFalse($resolved->isOverridden('body'));
        $this->assertSame(
            (string) __('emails.email_password_reset_body', [], 'fr'),
            $resolved->body
        );

        $row = DB::table('email_templates')
            ->where('template_key', 'password_reset')
            ->where('locale', 'fr')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('admin@leopardo.test', $row->updated_by);
    }

    public function test_un_champ_vide_revient_au_defaut(): void
    {
        $this->repository()->upsert('password_reset', 'fr', ['subject' => '   '], null);

        $resolved = $this->resolver()->resolve('password_reset', 'fr');

        $this->assertFalse($resolved->isOverridden('subject'));
        $this->assertSame(
            (string) __('emails.email_password_reset_subject', [], 'fr'),
            $resolved->subject
        );
    }

    public function test_le_corps_surcharge_est_echappe_et_les_variables_inconnues_ignorees(): void
    {
        $this->repository()->upsert('password_reset', 'fr', [
            'body' => "Bonjour :name\n<script>alert(1)</script> :evil",
        ], null);

        $resolved = $this->resolver()->resolve('password_reset', 'fr', [
            ':name' => 'Ada',
            ':evil' => 'INJECTE',
        ]);

        $html = $resolved->bodyHtml();

        $this->assertStringContainsString('Ada', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        // `:evil` n'est pas déclaré par le registre : il reste littéral.
        $this->assertStringContainsString(':evil', $html);
        $this->assertStringNotContainsString('INJECTE', $html);
        // Les retours à la ligne sont préservés (texte, pas HTML).
        $this->assertStringContainsString('<br />', $html);
    }

    public function test_les_quatre_locales_sont_independantes(): void
    {
        $this->repository()->upsert('password_reset', 'en', ['subject' => 'EN subject'], null);

        $this->assertSame('EN subject', $this->resolver()->resolve('password_reset', 'en')->subject);
        $this->assertNotSame('EN subject', $this->resolver()->resolve('password_reset', 'fr')->subject);
    }

    public function test_le_retour_au_defaut_supprime_la_surcharge(): void
    {
        $this->repository()->upsert('password_reset', 'fr', ['subject' => 'Temporaire'], null);
        $this->assertSame('Temporaire', $this->resolver()->resolve('password_reset', 'fr')->subject);

        $this->repository()->reset('password_reset', 'fr');

        $resolved = $this->resolver()->resolve('password_reset', 'fr');
        $this->assertFalse($resolved->isOverridden('subject'));
        $this->assertSame(
            (string) __('emails.email_password_reset_subject', [], 'fr'),
            $resolved->subject
        );
    }

    public function test_le_registre_ne_contient_aucun_texte_en_dur(): void
    {
        // Chaque champ du registre doit pointer vers une clé du catalogue : si
        // quelqu'un y écrit un littéral, la traduction (et donc la parité ×4)
        // serait contournée silencieusement.
        foreach (EmailTemplateRegistry::all() as $key => $definition) {
            foreach (['subject', 'heading'] as $field) {
                $this->assertStringStartsWith(
                    'emails.',
                    $definition[$field],
                    sprintf('Le registre doit pointer une clé de catalogue (%s.%s).', $key, $field)
                );
            }

            foreach ($definition['body'] as $line) {
                $this->assertStringStartsWith('emails.', $line);
            }

            if ($definition['cta_label'] !== null) {
                $this->assertStringStartsWith('emails.', $definition['cta_label']);
            }
        }
    }

    public function test_l_admin_liste_les_templates_avec_leurs_quatre_locales(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/v1/admin/email-templates');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(count(EmailTemplateRegistry::keys()), $data);
        $this->assertSame(['fr', 'en', 'ar', 'tr'], $response->json('meta.locales'));

        foreach ($data as $template) {
            foreach (['fr', 'en', 'ar', 'tr'] as $locale) {
                $this->assertArrayHasKey($locale, $template['locales']);
                $this->assertArrayHasKey('body', $template['locales'][$locale]);
                $this->assertArrayHasKey('overridden', $template['locales'][$locale]);
            }
        }
    }

    public function test_l_admin_peut_enregistrer_une_surcharge(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->putJson('/api/v1/admin/email-templates', [
            'template_key' => 'password_reset',
            'locale' => 'fr',
            'subject' => 'Nouveau sujet',
            'body' => 'Nouveau corps',
        ]);

        $response->assertOk();
        $this->assertSame('Nouveau sujet', $response->json('data.subject'));
        $this->assertTrue($response->json('data.overridden.subject'));
        $this->assertTrue($response->json('data.overridden.body'));

        $this->assertSame('Nouveau sujet', $this->resolver()->resolve('password_reset', 'fr')->subject);
    }

    public function test_une_cle_inconnue_ou_une_locale_non_supportee_est_refusee(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/email-templates', [
            'template_key' => 'template_inexistant',
            'locale' => 'fr',
            'subject' => 'x',
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/email-templates', [
            'template_key' => 'password_reset',
            'locale' => 'de',
            'subject' => 'x',
        ])->assertStatus(422);
    }

    public function test_l_admin_peut_reinitialiser_un_template(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/email-templates', [
            'template_key' => 'password_reset',
            'locale' => 'fr',
            'subject' => 'Temporaire',
        ])->assertOk();

        $this->deleteJson('/api/v1/admin/email-templates', [
            'template_key' => 'password_reset',
            'locale' => 'fr',
        ])->assertOk()->assertJsonPath('data.overridden.subject', false);
    }

    public function test_l_apercu_rend_le_contenu_dans_le_vrai_layout(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/admin/email-templates/preview', [
            'locale' => 'ar',
            'heading' => 'Titre apercu',
            'body' => "Premiere ligne\nDeuxieme ligne",
            'cta_label' => 'Ouvrir',
            'variables' => [':company' => 'Acme'],
        ]);

        $response->assertOk();
        $html = (string) $response->json('data.html');

        $this->assertStringContainsString('Titre apercu', $html);
        $this->assertStringContainsString('Premiere ligne', $html);
        $this->assertStringContainsString('Ouvrir', $html);
        // Le vrai layout : RTL pour l'arabe.
        $this->assertStringContainsString('dir="rtl"', $html);
    }

    public function test_les_endpoints_exigent_une_session_super_admin(): void
    {
        $this->getJson('/api/v1/admin/email-templates')->assertStatus(401);
    }
}
