<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\AI\DTOs\AIResponse;
use App\AI\LLMClient;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Marketing OS — generation assistee par IA (issue #8533).
 *
 * Le brief §D pose une limite explicite : « L'IA peut aider a rediger scripts,
 * descriptions et prompts, mais ne doit pas pretendre avoir genere une video
 * ou une image si aucun outil ne l'a reellement fait. »
 *
 * Ces tests verrouillent cette limite :
 *
 *  - l'IA PROPOSE script, description, legende, hashtags et prompts ;
 *  - `media_generated` vaut toujours `false` : aucune video ni image n'est
 *    annoncee comme produite ;
 *  - rien n'est persiste et aucun statut de publication n'est modifie par un
 *    appel IA — un humain valide et decide de ce qu'il conserve ;
 *  - une reponse LLM inexploitable donne 503, jamais une fiche inventee.
 */
class MarketingAiProductionBriefTest extends TestCase
{
    use RefreshTenantDatabase;

    private function marketingManager(Company $company): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);
    }

    private function actingAsPlatform(PlatformRole $role = PlatformRole::SuperAdmin): SuperAdmin
    {
        /** @var SuperAdmin $account */
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => 'marketing-ai-brief-'.$role->value.'@leopardo.test',
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($account, ['*'], 'super_admin_api');

        return $account;
    }

    private function stubLlm(?string $content, ?string $error = null): void
    {
        $this->app->instance(LLMClient::class, new class($content, $error) implements LLMClient
        {
            public function __construct(private readonly ?string $content, private readonly ?string $error) {}

            public function chat(array $messages, array $tools = [], ?array $responseFormat = null): AIResponse
            {
                return new AIResponse(content: $this->content ?? '', model: 'stub', error: $this->error);
            }

            public function provider(): string
            {
                return 'stub';
            }
        });
    }

    private function validBriefJson(): string
    {
        return json_encode([
            'script' => 'Scene 1 : un restaurateur ouvre son logiciel. Scene 2 : il publie en un clic.',
            'description' => 'Decouvrez comment Leopardo simplifie la gestion quotidienne.',
            'caption' => 'Gagnez du temps chaque jour.',
            'hashtags' => ['gestion', 'restaurant', 'leopardo'],
            'video_prompt' => 'Plan serre sur un restaurateur souriant devant une tablette, lumiere chaude.',
            'image_prompts' => ['Affiche publicitaire : restaurant moderne, texte minimaliste.'],
        ], JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. L'IA propose une fiche de production complete
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_ai_proposes_a_complete_production_brief(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm($this->validBriefJson());

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson('/api/v1/marketing/ai/suggest-production-brief', [
            'brief' => [
                'concept' => 'Montrer la simplicite de la prise de commande',
                'objectif' => 'acquisition',
                'public' => 'restaurateurs',
                'produit' => 'Leopardo Resto',
            ],
            'format' => 'video_short',
            'locale' => 'fr',
        ])
            ->assertOk()
            ->assertJsonPath('data.script', 'Scene 1 : un restaurateur ouvre son logiciel. Scene 2 : il publie en un clic.')
            ->assertJsonPath('data.caption', 'Gagnez du temps chaque jour.')
            ->assertJsonPath('data.hashtags', ['gestion', 'restaurant', 'leopardo'])
            ->assertJsonPath('data.video_prompt', 'Plan serre sur un restaurateur souriant devant une tablette, lumiere chaude.')
            ->assertJsonPath('data.image_prompts', ['Affiche publicitaire : restaurant moderne, texte minimaliste.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. L'IA ne pretend JAMAIS avoir produit un media
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_ai_never_claims_to_have_generated_a_media(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm($this->validBriefJson());

        Sanctum::actingAs($this->marketingManager($company));

        $response = $this->postJson('/api/v1/marketing/ai/suggest-production-brief', [
            'brief' => ['concept' => 'Publicite restaurant'],
            'format' => 'video_short',
        ])->assertOk();

        // Marqueur explicite, cote donnee ET cote meta : une proposition
        // n'est pas un livrable.
        $this->assertFalse($response->json('data.media_generated'));
        $this->assertFalse($response->json('meta.media_generated'));
        $this->assertTrue($response->json('meta.requires_human_validation'));
    }

    public function test_the_ai_call_does_not_touch_any_publication_status(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm($this->validBriefJson());

        // L'employe est cree AVANT l'appel plateforme : la surface plateforme
        // rebascule le contexte tenant, et le schema tenant doit deja etre
        // complet a ce moment-la.
        $manager = $this->marketingManager($company);

        // La publication est creee par la surface plateforme (Super Admin)...
        $this->actingAsPlatform();
        $publicationId = $this->postJson(
            "/api/v1/admin/companies/{$company->id}/marketing-os/publications",
            ['platform' => 'linkedin', 'title' => 'Publicite restaurant'],
        )->assertCreated()->json('data.id');

        // ...puis le manager marketing demande une fiche de production IA.
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/marketing/ai/suggest-production-brief', [
            'brief' => ['concept' => 'Publicite restaurant'],
            'format' => 'video_short',
        ])->assertOk();

        // L'appel IA ne doit avoir modifie AUCUN statut : la validation reste
        // humaine (brief §D et §E).
        $status = app(TenantManager::class)->withinTenant(
            $company,
            function () use ($publicationId): string {
                /** @var MarketingPublication $publication */
                $publication = MarketingPublication::query()->findOrFail($publicationId);

                return $publication->status->value;
            },
        );

        $this->assertSame('idea', $status);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Une reponse inexploitable ne produit pas de fiche inventee
    // ─────────────────────────────────────────────────────────────────────

    public function test_an_unreadable_llm_response_returns_503_without_inventing_a_brief(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm('Desole, je ne peux pas repondre.');

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson('/api/v1/marketing/ai/suggest-production-brief', [
            'brief' => ['concept' => 'Publicite restaurant'],
        ])
            ->assertStatus(503)
            ->assertJsonPath('error', 'MARKETING_AI_UNAVAILABLE');
    }

    public function test_a_llm_failure_returns_503(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm(null, 'provider down');

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson('/api/v1/marketing/ai/suggest-production-brief', [
            'brief' => ['concept' => 'Publicite restaurant'],
        ])->assertStatus(503);
    }

    public function test_the_brief_is_required(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm($this->validBriefJson());

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson('/api/v1/marketing/ai/suggest-production-brief', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brief']);
    }

    public function test_a_plain_employee_cannot_request_an_ai_brief(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm($this->validBriefJson());

        Sanctum::actingAs(Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]));

        $this->postJson('/api/v1/marketing/ai/suggest-production-brief', [
            'brief' => ['concept' => 'Publicite restaurant'],
        ])->assertForbidden();
    }
}
