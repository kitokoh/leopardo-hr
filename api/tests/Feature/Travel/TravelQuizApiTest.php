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
            $quiz = TravelQuiz::factory()->create(['status' => QuizStatus::PUBLISHED->value]);

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

        $response = $this->getJson("/api/v1/travel/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonPath('data.questions.0.question', 'Capitale du Cameroun ?');

        $payload = $response->json();
        self::assertArrayNotHasKey('correct_option_index', $payload['data']['questions'][0], 'la bonne réponse n\'est jamais exposée');
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

    /* ── TRAVEL-914 (#6422) — gestion admin : update quiz / questions ── */

    public function test_quiz_can_be_updated_by_manager(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();

        $this->putJson("/api/v1/travel/quizzes/{$quiz->id}", [
            'title' => 'Quiz édité',
            'description' => 'Nouvelle description',
            'status' => 'closed',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $quiz->id)
            ->assertJsonPath('data.status', 'closed');

        $quiz->refresh();
        self::assertSame('Quiz édité', $quiz->title);
        self::assertSame('closed', $quiz->status->value);
    }

    public function test_quiz_question_can_be_updated_and_deleted(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();
        $question = $quiz->questions()->orderBy('position')->firstOrFail();

        $this->putJson("/api/v1/travel/quizzes/{$quiz->id}/questions/{$question->id}", [
            'question' => 'Capitale du Cameroun (édition) ?',
            'options' => ['Douala', 'Yaoundé', 'Garoua'],
            'correct_option_index' => 1,
            'points' => 3,
            'position' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $question->id);

        $question->refresh();
        self::assertSame('Capitale du Cameroun (édition) ?', $question->question);
        self::assertSame(3, $question->points);

        $this->deleteJson("/api/v1/travel/quizzes/{$quiz->id}/questions/{$question->id}")
            ->assertStatus(204);

        self::assertNull(TravelQuizQuestion::find($question->id));
    }

    public function test_quiz_update_requires_manager_role(): void
    {
        /** @var Employee $agent */
        $agent = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($agent);

        $quiz = $this->makeQuizWithQuestions();

        $this->putJson("/api/v1/travel/quizzes/{$quiz->id}", [
            'title' => 'Interdit',
            'description' => null,
        ])->assertStatus(403);
    }

    public function test_quiz_question_edit_is_isolated_per_tenant(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreignQuestion = $this->tenants->withinTenant($companyB, function (): TravelQuizQuestion {
            $quiz = TravelQuiz::factory()->create();

            return TravelQuizQuestion::factory()->create(['quiz_id' => $quiz->id]);
        });

        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();

        $this->putJson("/api/v1/travel/quizzes/{$quiz->id}/questions/{$foreignQuestion->id}", [
            'question' => 'X',
            'options' => ['a', 'b'],
            'correct_option_index' => 0,
        ])->assertStatus(404);

        $this->deleteJson("/api/v1/travel/quizzes/{$quiz->id}/questions/{$foreignQuestion->id}")
            ->assertStatus(404);
    }

    public function test_questions_admin_list_exposes_correct_answer_only_to_manager(): void
    {
        $this->actingManager();
        $quiz = $this->makeQuizWithQuestions();

        $this->getJson("/api/v1/travel/quizzes/{$quiz->id}/questions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.correct_option_index', 1)
            ->assertJsonPath('data.1.correct_option_index', 1);
    }

    public function test_questions_admin_list_requires_manager_role(): void
    {
        /** @var Employee $agent */
        $agent = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($agent);

        $quiz = $this->makeQuizWithQuestions();

        $this->getJson("/api/v1/travel/quizzes/{$quiz->id}/questions")->assertStatus(403);
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * Les deux premieres questions d'un quiz publie, garanties non nulles.
     *
     * `$quiz->questions()->get()` renvoie une `Collection` dont l'acces par
     * indice est type `TravelQuizQuestion|null` : sur un quiz fraichement cree
     * les questions existent par construction, mais PHPStan (level 8) ne peut
     * pas le savoir. Les deux tests ajoutes par #8396 empilaient donc deux
     * nouvelles entrees dans `phpstan-strict-baseline.neon` (l'entree existante
     * en compte deja 5, elle a ete atteinte). Plutot que d'ajouter une
     * exception de plus, on resout ici avec une garde explicite : si les
     * questions manquent, le test doit echouer avec un message clair plutot
     * que sur un `null` dereference.
     *
     * @return array{0: TravelQuizQuestion, 1: TravelQuizQuestion}
     */
    private function publishedQuizQuestions(TravelQuiz $quiz): array
    {
        $all = array_values($quiz->questions()->orderBy('position')->get()->all());

        $first = $all[0] ?? null;
        $second = $all[1] ?? null;

        if (! $first instanceof TravelQuizQuestion || ! $second instanceof TravelQuizQuestion) {
            $this->fail('Le quiz de test doit porter au moins deux questions.');
        }

        return [$first, $second];
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function publishedQuiz(Company $company): TravelQuiz
    {
        return app(TenantManager::class)->withinTenant($company, function (): TravelQuiz {
            $quiz = TravelQuiz::query()->create([
                'company_id' => app('current_company')->id,
                'title' => 'Quiz decouverte',
                'status' => 'published',
                'max_participations_per_contact' => 1,
                'bonus_points' => 50,
            ]);

            // Contrat actuel : `question` + `options` + `correct_option_index`.
            // Les colonnes legacy (`label`/`choices`/`correct_answer_hash`)
            // ne sont plus lues par le scoring serveur.
            foreach ([
                ['question' => 'Capitale de la France ?', 'options' => ['Londres', 'Paris', 'Berlin'], 'correct_option_index' => 1, 'points' => 1],
                ['question' => '6 x 7 ?', 'options' => ['40', '42', '44'], 'correct_option_index' => 1, 'points' => 2],
            ] as $i => $q) {
                TravelQuizQuestion::query()->create([
                    'company_id' => $quiz->company_id,
                    'quiz_id' => $quiz->id,
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'correct_option_index' => $q['correct_option_index'],
                    'points' => $q['points'],
                    'position' => $i,
                ]);
            }

            return $quiz;
        });
    }

    public function test_correct_answer_is_never_stored_in_clear(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $quiz = $this->publishedQuiz($company);

        // L'API n'expose jamais la bonne reponse en clair.
        $this->getJson("/api/v1/travel/community/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonPath('data.questions.0.question', 'Capitale de la France ?')
            ->assertJsonMissingPath('data.questions.0.correct_option_index');
    }

    public function test_participation_is_unique_per_contact(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $quiz = $this->publishedQuiz($company);

        [$firstQuestion, $secondQuestion] = $this->publishedQuizQuestions($quiz);
        $answers = [
            ['question_id' => $firstQuestion->id, 'selected_option' => 1],
            ['question_id' => $secondQuestion->id, 'selected_option' => 1],
        ];

        // Premiere participation : score 3/3.
        $this->postJson("/api/v1/travel/community/quizzes/{$quiz->id}/participate", [
            'participant_email' => 'client@example.com',
            'answers' => $answers,
        ])->assertStatus(201)
            ->assertJsonPath('data.score', 3);

        // Participation unique : 409 au second essai (meme email).
        $this->postJson("/api/v1/travel/community/quizzes/{$quiz->id}/participate", [
            'participant_email' => 'client@example.com',
            'answers' => $answers,
        ])->assertStatus(409);
    }

    public function test_score_is_consistent_with_wrong_answers(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $quiz = $this->publishedQuiz($company);

        [$firstQuestion, $secondQuestion] = $this->publishedQuizQuestions($quiz);
        $answers = [
            ['question_id' => $firstQuestion->id, 'selected_option' => 0],
            ['question_id' => $secondQuestion->id, 'selected_option' => 1],
        ];

        // 0/1 + 2/2 = score 2 <= total 3.
        $this->postJson("/api/v1/travel/community/quizzes/{$quiz->id}/participate", [
            'participant_email' => 'autre@example.com',
            'answers' => $answers,
        ])->assertStatus(201)
            ->assertJsonPath('data.score', 2);
    }
}
