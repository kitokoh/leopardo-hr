<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use App\Core\Tenant\TenantManager;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours : CRUD quiz + questions,
 * participation unique bornée, score calculé serveur, résultats RBAC,
 * isolation cross-tenant.
 */
class TravelQuizTest extends TestCase
{
    use RefreshTenantDatabase;

    private function login(Company $company, string $role = 'manager', ?string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function makePublishedQuiz(Company $company, int $maxAttempts = 1): TravelQuiz
    {
        $quiz = app(TenantManager::class)->withinTenant($company, fn (): TravelQuiz => TravelQuiz::query()->create([
            'company_id' => $company->id,
            'title' => 'Quiz Cameroun',
            'status' => TravelQuiz::STATUS_PUBLISHED,
            'max_attempts' => $maxAttempts,
            'published_at' => now(),
        ]));

        $this->postJson('/api/v1/travel/quizzes/'.$quiz->id.'/questions', [
            'question' => 'Capitale du Cameroun ?',
            'options' => ['Douala', 'Yaoundé', 'Bafoussam'],
            'correct_option_index' => 1,
            'points' => 10,
            'sort_order' => 1,
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/quizzes/'.$quiz->id.'/questions', [
            'question' => 'Fleuve principal ?',
            'options' => ['Sanaga', 'Nil', 'Congo'],
            'correct_option_index' => 0,
            'points' => 5,
            'sort_order' => 2,
        ])->assertStatus(201);

        return $quiz;
    }

    public function test_quiz_crud_and_question_lifecycle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $created = $this->postJson('/api/v1/travel/quizzes', [
            'title' => 'Quiz test',
            'max_attempts' => 1,
            'status' => 'draft',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $quizId = (int) $created->json('data.id');

        // Question hors bornes → 422.
        $this->postJson("/api/v1/travel/quizzes/{$quizId}/questions", [
            'question' => 'Q?',
            'options' => ['A', 'B'],
            'correct_option_index' => 5,
        ])->assertStatus(422);

        // Publication du quiz.
        $this->putJson("/api/v1/travel/quizzes/{$quizId}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.published_at', fn ($v) => $v !== null);

        // Suppression.
        $this->deleteJson("/api/v1/travel/quizzes/{$quizId}")->assertStatus(204);
    }

    /**
     * Construit la liste `answers` du contrat public ({question_id, selected_option}).
     *
     * @param  array<int, int>  $selectedByPosition
     * @return array<int, array{question_id: int, selected_option: int}>
     */
    private function answers(TravelQuiz $quiz, array $selectedByPosition, string $email = 'joueur@example.com'): array
    {
        $questions = $quiz->questions()->orderBy('position')->get();
        $answers = [];

        foreach ($selectedByPosition as $index => $selected) {
            if (! isset($questions[$index])) {
                continue;
            }

            $answers[] = ['question_id' => $questions[$index]->id, 'selected_option' => $selected];
        }

        return ['participant_email' => $email, 'answers' => $answers];
    }

    public function test_participation_is_unique_and_score_is_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);
        $quiz = $this->makePublishedQuiz($company);

        // Bonnes reponses : 10 + 5 = 15.
        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $this->answers($quiz, [1, 0]))
            ->assertStatus(201)
            ->assertJsonPath('data.score', 15);

        // Participation unique : seconde tentative avec le MEME email -> 409.
        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $this->answers($quiz, [0, 0]))
            ->assertStatus(409);

        // Mauvaises reponses, autre participant : score 0 (calcul serveur).
        $this->login($company, role: 'employee', managerRole: null);
        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $this->answers($quiz, [0, 1], 'autre@example.com'))
            ->assertStatus(201)
            ->assertJsonPath('data.score', 0);

        $this->assertSame(2, TravelQuizParticipation::query()->count());
    }

    public function test_participation_requires_published_quiz_and_bounded_answers(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $draft = app(TenantManager::class)->withinTenant($company, fn () => TravelQuiz::query()->create([
            'company_id' => $company->id,
            'title' => 'Brouillon',
            'status' => TravelQuiz::STATUS_DRAFT,
            'max_attempts' => 1,
        ]));

        $this->postJson("/api/v1/travel/quizzes/{$draft->id}/participate", $this->answers($draft, [0]))
            ->assertStatus(422);

        $quiz = $this->makePublishedQuiz($company);

        // Reponse partielle : licite (la question omise compte 0), pas une erreur.
        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $this->answers($quiz, [0], 'partiel@example.com'))
            ->assertStatus(201)
            ->assertJsonPath('data.score', 0);

        // Reponse hors bornes -> 422.
        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $this->answers($quiz, [0, 9]))
            ->assertStatus(422);
    }

    public function test_results_require_manage_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);
        $quiz = $this->makePublishedQuiz($company);
        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $this->answers($quiz, [1, 0]))->assertStatus(201);

        // Manager sans droit gestion (role metier valide, hors principal/rh) -> 403.
        $this->login($company, role: 'manager', managerRole: 'dept');
        $this->getJson("/api/v1/travel/quizzes/{$quiz->id}/participations")->assertStatus(403);

        // Principal → 200 avec les résultats.
        $this->login($company, role: 'manager', managerRole: 'principal');
        $this->getJson("/api/v1/travel/quizzes/{$quiz->id}/participations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.score', 15);
    }

    public function test_quiz_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $this->login($companyA);
        $quizA = $this->makePublishedQuiz($companyA);

        $this->login($companyB);
        $this->getJson("/api/v1/travel/quizzes/{$quizA->id}")->assertStatus(404);
        $this->postJson("/api/v1/travel/quizzes/{$quizA->id}/participate", $this->answers($quizA, [0, 0]))->assertStatus(404);
    }
}
