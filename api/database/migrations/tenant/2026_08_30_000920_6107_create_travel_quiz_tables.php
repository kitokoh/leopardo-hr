<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (schemaTableExists('travel_quizzes')) {
            // Issue #7452 — la table est créée par 2026_08_30_000016_6107_create_travel_quiz_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_quizzes', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_quizzes', 'title')) {
                    $table->string('title', 160);
                }
                if (! schemaHasColumn('travel_quizzes', 'description_redacted')) {
                    $table->string('description_redacted', 2000)->nullable();
                }
                if (! schemaHasColumn('travel_quizzes', 'starts_at')) {
                    $table->timestampTz('starts_at')->nullable();
                }
                if (! schemaHasColumn('travel_quizzes', 'ends_at')) {
                    $table->timestampTz('ends_at')->nullable();
                }
                if (! schemaHasColumn('travel_quizzes', 'max_participations_per_contact')) {
                    $table->unsignedInteger('max_participations_per_contact')->default(1);
                }
                if (! schemaHasColumn('travel_quizzes', 'status')) {
                    $table->string('status', 20)->default('draft');
                }
                if (! schemaHasColumn('travel_quizzes', 'created_at')) {
                    $table->timestampTz('created_at')->useCurrent();
                }
                if (! schemaHasColumn('travel_quizzes', 'updated_at')) {
                    $table->timestampTz('updated_at')->useCurrent();
                }
            });
        }

        if (schemaTableExists('travel_quiz_questions')) {
            // Issue #7452 — la table est créée par 2026_08_30_000016_6107_create_travel_quiz_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_quiz_questions', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_quiz_questions', 'quiz_id')) {
                    $table->unsignedBigInteger('quiz_id');
                }
                if (! schemaHasColumn('travel_quiz_questions', 'question')) {
                    $table->string('question', 500)->nullable();
                }
                if (! schemaHasColumn('travel_quiz_questions', 'options')) {
                    $table->jsonb('options')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_questions', 'correct_option_index')) {
                    $table->unsignedSmallInteger('correct_option_index')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_questions', 'points')) {
                    $table->unsignedSmallInteger('points')->default(1);
                }
                if (! schemaHasColumn('travel_quiz_questions', 'position')) {
                    $table->unsignedSmallInteger('position')->default(0);
                }
                if (! schemaHasColumn('travel_quiz_questions', 'created_at')) {
                    $table->timestampTz('created_at')->useCurrent();
                }
                if (! schemaHasColumn('travel_quiz_questions', 'updated_at')) {
                    $table->timestampTz('updated_at')->useCurrent();
                }
            });
        }

        if (schemaTableExists('travel_quiz_participations')) {
            // Issue #7452 — la table est créée par 2026_08_30_000016_6107_create_travel_quiz_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_quiz_participations', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'quiz_id')) {
                    $table->unsignedBigInteger('quiz_id');
                }
                if (! schemaHasColumn('travel_quiz_participations', 'participant_contact_id')) {
                    $table->unsignedBigInteger('participant_contact_id')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'participant_email')) {
                    $table->string('participant_email', 190)->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'participant_name')) {
                    $table->string('participant_name', 160)->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'answers')) {
                    $table->jsonb('answers')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'score')) {
                    $table->unsignedSmallInteger('score')->default(0);
                }
                if (! schemaHasColumn('travel_quiz_participations', 'bonus')) {
                    $table->unsignedSmallInteger('bonus')->default(0);
                }
                if (! schemaHasColumn('travel_quiz_participations', 'status')) {
                    $table->string('status', 20)->default('submitted');
                }
                if (! schemaHasColumn('travel_quiz_participations', 'created_at')) {
                    $table->timestampTz('created_at')->useCurrent();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'updated_at')) {
                    $table->timestampTz('updated_at')->useCurrent();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_quiz_participations', 'company_id')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'quiz_id')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('quiz_id');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'participant_contact_id')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('participant_contact_id');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'participant_email')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('participant_email');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'participant_name')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('participant_name');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'answers')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('answers');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'score')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('score');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'bonus')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('bonus');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'status')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'created_at')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'updated_at')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'company_id')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'quiz_id')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('quiz_id');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'question')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('question');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'options')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('options');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'correct_option_index')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('correct_option_index');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'points')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('points');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'position')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('position');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'created_at')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'updated_at')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'company_id')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'title')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('title');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'description_redacted')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('description_redacted');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'starts_at')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('starts_at');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'ends_at')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('ends_at');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'max_participations_per_contact')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('max_participations_per_contact');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'status')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'created_at')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'updated_at')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
    }
};
