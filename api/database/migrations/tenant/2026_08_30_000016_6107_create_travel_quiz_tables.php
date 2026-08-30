<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6107 (TRAVEL-904) — Quiz & jeu-concours.
 *
 * - `travel_quizzes` : quiz du tenant (titre, statut, bornes de
 *   participation, bonus en points de fidélité).
 * - `travel_quiz_questions` : questions (libellé, ordre, bonne réponse
 *   hachée — jamais en clair au repos).
 * - `travel_quiz_participations` : participation UNIQUE par (quiz, contact)
 *   (critère d'acceptation), score + résultat calculés serveur.
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
                $table->string('status', 20)->default('draft'); // draft|published|closed
                $table->unsignedInteger('max_participations_per_contact')->default(1);
                $table->unsignedInteger('bonus_points')->default(0);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! schemaTableExists('travel_quiz_questions')) {
            Schema::create('travel_quiz_questions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('quiz_id');
                $table->unsignedInteger('rank');
                $table->string('label', 500);
                $table->json('choices');
                $table->string('correct_answer_hash', 64);
                $table->unsignedInteger('points')->default(1);
                $table->timestamps();

                $table->unique(['company_id', 'quiz_id', 'rank'], 'travel_quiz_questions_company_quiz_rank_unique');
            });
        }

        if (! schemaTableExists('travel_quiz_participations')) {
            Schema::create('travel_quiz_participations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('quiz_id');
                $table->string('participant_identifier', 255);
                $table->json('answers_redacted');
                $table->unsignedInteger('score')->default(0);
                $table->unsignedInteger('total_points')->default(0);
                $table->timestamp('completed_at')->useCurrent();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'quiz_id', 'participant_identifier'],
                    'travel_quiz_participations_company_quiz_contact_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_quiz_participations');
        Schema::dropIfExists('travel_quiz_questions');
        Schema::dropIfExists('travel_quizzes');
    }
};
