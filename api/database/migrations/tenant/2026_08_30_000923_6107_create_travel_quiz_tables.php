<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (schemaTableExists('travel_quizzes')) {
            // Issue #7452 — la table est créée par 2026_08_30_000016_6107_create_travel_quiz_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_quizzes', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_quizzes', 'title')) {
                    $table->string('title', 200)->nullable();
                }
                if (! schemaHasColumn('travel_quizzes', 'description_redacted')) {
                    $table->text('description_redacted')->nullable();
                }
                if (! schemaHasColumn('travel_quizzes', 'status')) {
                    $table->string('status', 20)->default('draft');
                }
                if (! schemaHasColumn('travel_quizzes', 'max_attempts')) {
                    $table->unsignedSmallInteger('max_attempts')->default(1);
                }
                if (! schemaHasColumn('travel_quizzes', 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
                if (! schemaHasColumn('travel_quizzes', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_quiz_questions')) {
            // Issue #7452 — la table est créée par 2026_08_30_000016_6107_create_travel_quiz_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_quiz_questions', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_quiz_questions', 'quiz_id')) {
                    $table->unsignedBigInteger('quiz_id')->nullable();
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
                if (! schemaHasColumn('travel_quiz_questions', 'sort_order')) {
                    $table->unsignedSmallInteger('sort_order')->default(0);
                }
                if (! schemaHasColumn('travel_quiz_questions', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_quiz_participations')) {
            // Issue #7452 — la table est créée par 2026_08_30_000016_6107_create_travel_quiz_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_quiz_participations', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'quiz_id')) {
                    $table->unsignedBigInteger('quiz_id')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'participant_type')) {
                    $table->string('participant_type', 20)->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'participant_id')) {
                    $table->unsignedBigInteger('participant_id')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'answers')) {
                    $table->jsonb('answers')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'score')) {
                    $table->unsignedSmallInteger('score')->default(0);
                }
                if (! schemaHasColumn('travel_quiz_participations', 'status')) {
                    $table->string('status', 20)->default('completed');
                }
                if (! schemaHasColumn('travel_quiz_participations', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
                if (! schemaHasColumn('travel_quiz_participations', 'created_at')) {
                    $table->timestamps();
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
        if (schemaHasColumn('travel_quiz_participations', 'participant_type')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('participant_type');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'participant_id')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('participant_id');
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
        if (schemaHasColumn('travel_quiz_participations', 'status')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'completed_at')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('completed_at');
            });
        }
        if (schemaHasColumn('travel_quiz_participations', 'created_at')) {
            Schema::table('travel_quiz_participations', function (Blueprint $table): void {
                $table->dropColumn('created_at');
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
        if (schemaHasColumn('travel_quiz_questions', 'sort_order')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('sort_order');
            });
        }
        if (schemaHasColumn('travel_quiz_questions', 'created_at')) {
            Schema::table('travel_quiz_questions', function (Blueprint $table): void {
                $table->dropColumn('created_at');
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
        if (schemaHasColumn('travel_quizzes', 'status')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'max_attempts')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('max_attempts');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'published_at')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('published_at');
            });
        }
        if (schemaHasColumn('travel_quizzes', 'created_at')) {
            Schema::table('travel_quizzes', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
