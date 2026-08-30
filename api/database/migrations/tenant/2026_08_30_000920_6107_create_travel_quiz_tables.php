<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6107 (TRAVEL-904) — Quiz & jeu-concours (gamification legacy gv-back).
 *
 * `travel_quizzes` (fenêtres de participation, statuts draft/active/closed),
 * `travel_quiz_questions` (options JSONB, réponse correcte indicée — jamais
 * exposée au participant), `travel_quiz_participations` (réponses JSONB,
 * score/bonus calculés serveur, participation unique par quiz/email).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_quizzes')) {
            Schema::create('travel_quizzes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 160);
                $table->string('description_redacted', 2000)->nullable();
                $table->timestampTz('starts_at')->nullable();
                $table->timestampTz('ends_at')->nullable();
                $table->unsignedInteger('max_participations_per_contact')->default(1);
                $table->string('status', 20)->default('draft');
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
                $table->index(['company_id', 'status'], 'travel_quizzes_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE travel_quizzes IS 'Quiz & jeu-concours TravelAgency (TRAVEL-904/#6107).'");
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
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
                $table->unique(['company_id', 'quiz_id', 'position'], 'travel_quiz_questions_company_quiz_position_unique');
            });

            DB::statement("COMMENT ON TABLE travel_quiz_questions IS 'Questions de quiz — reponse correcte indicée, jamais exposée (TRAVEL-904/#6107).'");
        }

        if (! schemaTableExists('travel_quiz_participations')) {
            Schema::create('travel_quiz_participations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('quiz_id');
                $table->unsignedBigInteger('participant_contact_id')->nullable();
                $table->string('participant_email', 190)->nullable();
                $table->string('participant_name', 160)->nullable();
                $table->jsonb('answers');
                $table->unsignedSmallInteger('score')->default(0);
                $table->unsignedSmallInteger('bonus')->default(0);
                $table->string('status', 20)->default('submitted');
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
                $table->unique(['company_id', 'quiz_id', 'participant_email'], 'travel_quiz_participations_company_quiz_email_unique');
                $table->index(['company_id', 'quiz_id'], 'travel_quiz_participations_company_quiz_idx');
            });

            DB::statement("COMMENT ON TABLE travel_quiz_participations IS 'Participations au quiz — score/bonus calculés serveur, participation unique (TRAVEL-904/#6107).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_quiz_participations');
        Schema::dropIfExists('travel_quiz_questions');
        Schema::dropIfExists('travel_quizzes');
    }
};
