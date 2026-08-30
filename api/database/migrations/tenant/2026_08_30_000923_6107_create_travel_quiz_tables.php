<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours (gamification legacy, spec §3).
 *
 * Un quiz publie des questions (options JSONB, réponse correcte côté
 * serveur) ; une participation est UNIQUE par (tenant, quiz, participant) —
 * score calculé serveur, participation bornée par `max_attempts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_quizzes')) {
            Schema::create('travel_quizzes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 200);
                $table->text('description_redacted')->nullable();
                $table->string('status', 20)->default('draft');
                $table->unsignedSmallInteger('max_attempts')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'travel_quizzes_company_status_idx');
            });

            DB::statement("ALTER TABLE travel_quizzes ADD CONSTRAINT travel_quizzes_status_check CHECK (status IN ('draft', 'published', 'archived'))");
            DB::statement("ALTER TABLE travel_quizzes ADD CONSTRAINT travel_quizzes_max_attempts_check CHECK (max_attempts >= 1)");
            DB::statement("COMMENT ON TABLE travel_quizzes IS 'Quiz et jeux-concours (TRAVEL-904/#6107).'");
        }

        if (! schemaTableExists('travel_quiz_questions')) {
            Schema::create('travel_quiz_questions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('quiz_id');
                $table->string('question', 500);
                $table->jsonb('options');
                $table->unsignedSmallInteger('correct_option_index');
                $table->unsignedSmallInteger('points')->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['company_id', 'quiz_id'], 'travel_quiz_questions_company_quiz_idx');
            });

            DB::statement("COMMENT ON TABLE travel_quiz_questions IS 'Questions de quiz — options JSONB, bonne reponse cote serveur (TRAVEL-904/#6107).'");
        }

        if (! schemaTableExists('travel_quiz_participations')) {
            Schema::create('travel_quiz_participations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('quiz_id');
                $table->string('participant_type', 20);
                $table->unsignedBigInteger('participant_id');
                $table->jsonb('answers');
                $table->unsignedSmallInteger('score')->default(0);
                $table->string('status', 20)->default('completed');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'quiz_id', 'participant_type', 'participant_id'], 'travel_quiz_participations_unique');
                $table->index(['company_id', 'quiz_id'], 'travel_quiz_participations_company_quiz_idx');
            });

            DB::statement("COMMENT ON TABLE travel_quiz_participations IS 'Participations aux quiz — unique par (tenant, quiz, participant) (TRAVEL-904/#6107).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_quiz_participations');
        Schema::dropIfExists('travel_quiz_questions');
        Schema::dropIfExists('travel_quizzes');
    }
};
