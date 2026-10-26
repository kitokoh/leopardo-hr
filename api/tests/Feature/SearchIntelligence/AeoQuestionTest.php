<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\AeoQuestionStatus;
use App\Modules\SearchIntelligence\Domain\Models\AeoQuestion;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-4 (#8515) — bibliothèque AEO : publication impossible sans révision
 * humaine, et réponse FIGÉE à la validation.
 */
final class AeoQuestionTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_publish_without_review_returns_422(): void
    {
        $question = $this->makeQuestion(AeoQuestionStatus::Draft, ['answer_draft' => 'Une réponse.']);
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'aeo_review_required');
    }

    public function test_publish_after_review_succeeds(): void
    {
        $question = $this->makeQuestion(AeoQuestionStatus::Draft, ['answer_draft' => 'Une réponse.']);
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/submit")->assertOk();
        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/approve")->assertOk();

        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $question->refresh();
        self::assertNotNull($question->published_at);
        self::assertNotNull($question->approved_by);
    }

    public function test_draft_cannot_skip_review_to_published(): void
    {
        $question = $this->makeQuestion(AeoQuestionStatus::Draft, ['answer_draft' => 'Une réponse.']);
        $this->actingAsMarketing();

        // `draft → published` n'existe pas dans la machine à états.
        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/publish")
            ->assertStatus(422);
    }

    public function test_approved_answer_is_frozen_at_validation(): void
    {
        $question = $this->makeQuestion(AeoQuestionStatus::Draft, ['answer_draft' => 'Version relue.']);
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/submit")->assertOk();
        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/approve")->assertOk();

        $question->refresh();
        self::assertSame('Version relue.', $question->answer_approved);

        // Le brouillon change APRÈS validation (accès direct en base) : la
        // version figée ne doit pas bouger, sinon on publierait un texte que
        // personne n'a relu.
        $question->answer_draft = 'Version modifiée après validation.';
        $question->save();

        self::assertSame('Version relue.', $question->refresh()->answer_approved);

        $this->postJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}/publish")->assertOk();

        $question->refresh();
        self::assertSame(AeoQuestionStatus::Published, $question->status);
        self::assertSame('Version relue.', $question->answer_approved);
    }

    public function test_crud_and_filters(): void
    {
        $this->actingAsMarketing();

        $created = $this->postJson('/api/v1/platform/search-intel/aeo/questions', [
            'question' => 'Combien coûte la paie externalisée ?',
            'answer_draft' => 'Brouillon.',
            'locale' => 'fr',
            'sector' => 'restaurant',
        ])->assertStatus(201)->json('data');

        // Une question naît toujours en brouillon, quel que soit le corps
        // envoyé : `status` n'est pas mass-assignable.
        self::assertSame('draft', $created['status']);

        $this->postJson('/api/v1/platform/search-intel/aeo/questions', [
            'question' => 'How much does payroll cost?',
            'answer_draft' => 'Draft.',
            'locale' => 'en',
            'sector' => 'restaurant',
        ])->assertStatus(201);

        $fr = $this->getJson('/api/v1/platform/search-intel/aeo/questions?locale=fr')->assertOk();
        self::assertCount(1, $fr->json('data'));

        $restaurant = $this->getJson('/api/v1/platform/search-intel/aeo/questions?sector=restaurant')->assertOk();
        self::assertCount(2, $restaurant->json('data'));

        $drafts = $this->getJson('/api/v1/platform/search-intel/aeo/questions?status=draft')->assertOk();
        self::assertCount(2, $drafts->json('data'));
    }

    public function test_only_a_draft_is_editable(): void
    {
        // Le statut est fixé EXPLICITEMENT après création : `status` n'est pas
        // mass-assignable par conception (une question ne naît jamais
        // « publiée »), donc le passer à `create()` serait silencieusement
        // ignoré et le test testerait le mauvais état.
        $question = $this->makeQuestion(AeoQuestionStatus::Draft, [
            'answer_draft' => 'Texte.',
            'answer_approved' => 'Texte.',
            'approved_by' => 1,
            'approved_at' => now(),
            'published_at' => now(),
        ]);

        // La contrainte CHECK exige `approved_by` + `approved_at` pour un
        // statut publié : un état publié sans traces de validation serait
        // précisément ce que le module interdit.
        $question->forceFill([
            'status' => AeoQuestionStatus::Published->value,
            'answer_approved' => 'Texte relu.',
            'approved_by' => 1,
            'approved_at' => now(),
            'published_at' => now(),
        ])->save();

        $this->actingAsMarketing();

        $this->patchJson("/api/v1/platform/search-intel/aeo/questions/{$question->id}", [
            'answer_draft' => 'Autre texte.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'question_not_editable');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeQuestion(AeoQuestionStatus $status, array $attributes = []): AeoQuestion
    {
        return AeoQuestion::query()->create(array_merge([
            'question' => 'Question '.uniqid(),
            'locale' => 'fr',
            'status' => $status->value,
        ], $attributes));
    }

    private function actingAsMarketing(): void
    {
        $admin = new SuperAdmin([
            'name' => 'Marketing',
            'email' => 'marketing-'.uniqid().'@leopardo.test',
        ]);

        $admin->forceFill([
            'password_hash' => Hash::make('x'),
            'platform_role' => PlatformRole::Marketing->value,
        ])->save();

        Sanctum::actingAs($admin->refresh(), ['*'], 'super_admin_api');
    }
}
