<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\QuizStatus;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours.
 *
 * Participation bornée (une par quiz/email), notation serveur (la bonne
 * réponse n'est jamais exposée), résultats cohérents.
 */
class TravelQuizApiTest extends TestCase
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

    private function actingManager(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function makeQuizWithQuestions(): TravelQuiz
    {
        return $this->tenants->withinTenant($this->company, function (): TravelQuiz {
            $quiz = TravelQuiz::factory()->create(['status' => QuizStatus::ACTIVE->value]);

            TravelQuizQuestion::factory()->create([
                'quiz_id' => $quiz->id,
                'question' => 'Capitale du Cameroun ?',
                'options' => ['Douala', 'Yaoundé', 'Garoua', 'Bafoussam'],
                'correct_option_index' => 1,
                'points' => 2,
                'position' => 0,
            ]);
            TravelQuizQuestion::factory()->create([
                'quiz_id' => $quiz->id,
                'question' => 'Plus grande ville ?',
                'options' => ['Yaoundé', 'Douala', 'Kribi', 'Maroua'],
                'correct_option_index' => 1,
                'points' => 1,
                'position' => 1,
            ]);

            return $quiz;
        });
    }

    public function test_quiz_detail_never_exposes_correct_answers(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();

        $this->getJson("/api/v1/travel/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.questions.0.correct_option_index')
            ->assertJsonPath('data.questions.0.question', 'Capitale du Cameroun ?');
    }

    public function test_participation_is_graded_server_side(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();
        $questions = $quiz->questions()->orderBy('position')->get();

        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", [
            'participant_email' => 'joueur@example.com',
            'participant_name' => 'Joueur Test',
            'answers' => [
                ['question_id' => $questions[0]->id, 'selected_option' => 1], // bonne
                ['question_id' => $questions[1]->id, 'selected_option' => 0], // mauvaise
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.bonus', 0);
    }

    public function test_perfect_participation_gets_bonus(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();
        $questions = $quiz->questions()->orderBy('position')->get();

        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", [
            'participant_email' => 'parfait@example.com',
            'answers' => [
                ['question_id' => $questions[0]->id, 'selected_option' => 1],
                ['question_id' => $questions[1]->id, 'selected_option' => 1],
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.score', 3)
            ->assertJsonPath('data.bonus', 3);
    }

    public function test_participation_is_unique_per_quiz_and_email(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();
        $questions = $quiz->questions()->orderBy('position')->get();

        $payload = [
            'participant_email' => 'unique@example.com',
            'answers' => [['question_id' => $questions[0]->id, 'selected_option' => 1]],
        ];

        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $payload)->assertStatus(201);
        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", $payload)->assertStatus(409);
    }

    public function test_closed_quiz_rejects_participation(): void
    {
        $this->actingManager();
        $quiz = $this->tenants->withinTenant($this->company, function (): TravelQuiz {
            return TravelQuiz::factory()->create(['status' => QuizStatus::CLOSED->value]);
        });

        $this->postJson("/api/v1/travel/quizzes/{$quiz->id}/participate", [
            'participant_email' => 'tard@example.com',
            'answers' => [],
        ])->assertStatus(422);
    }

    public function test_results_are_sorted_by_score(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();

        $this->tenants->withinTenant($this->company, function () use ($quiz): void {
            TravelQuizParticipation::factory()->create(['quiz_id' => $quiz->id, 'participant_email' => 'a@example.com', 'score' => 1]);
            TravelQuizParticipation::factory()->create(['quiz_id' => $quiz->id, 'participant_email' => 'b@example.com', 'score' => 3]);
        });

        $this->getJson("/api/v1/travel/quizzes/{$quiz->id}/results")
            ->assertOk()
            ->assertJsonPath('data.0.participant_email', 'b@example.com');
    }

    public function test_quiz_is_isolated_per_tenant(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($companyB, function (): void {
            TravelQuiz::factory()->create(['title' => 'Quiz tenant B']);
        });

        $this->actingManager();

        $this->getJson('/api/v1/travel/quizzes')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Quiz tenant B']);
    }
}
