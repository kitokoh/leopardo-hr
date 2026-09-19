<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7688 (R3 Communication, spec MODULE_COMMUNICATION_EMAIL_IA.md §3.3)
 * — champs de classification IA + liaison CRM sur `communication_messages`
 * (migration ADDITIVE sur la table R2 #7687).
 *
 * - `ai_category` / `ai_language` / `ai_sentiment` / `ai_action` : sortie
 *   STRUCTUREE du tool `email_classify` (JSON schema valide cote service —
 *   jamais de texte libre du LLM persiste tel quel) ;
 * - `ai_confidence` : confiance 0-100 (pilote l'escalade snippet -> corps) ;
 * - `classification_status` : `pending` (defaut) | `classified` | `failed` ;
 * - `classification_error` : code machine (jamais de payload LLM) ;
 * - `crm_contact_id` : id du `crm_contacts` rattache par correspondance
 *   d'email — SANS contrainte FK inter-BC (BC-29 -> BC-11 via contrat
 *   partage `App\Shared\Contracts\Crm`, isolation #5584) ;
 * - `contact_link_status` : `linked` | `proposed` | `none` — la creation
 *   d'un contact inconnu n'est JAMAIS silencieuse (proposition, §3.3).
 *
 * Migration reentrante (garde colonne par colonne), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('communication_messages')) {
            return;
        }

        Schema::table('communication_messages', function (Blueprint $table): void {
            if (! schemaHasColumn('communication_messages', 'ai_category')) {
                $table->string('ai_category', 64)->nullable()->index();
            }

            if (! schemaHasColumn('communication_messages', 'ai_language')) {
                $table->string('ai_language', 8)->nullable();
            }

            if (! schemaHasColumn('communication_messages', 'ai_sentiment')) {
                $table->string('ai_sentiment', 16)->nullable();
            }

            if (! schemaHasColumn('communication_messages', 'ai_action')) {
                $table->string('ai_action', 32)->nullable();
            }

            if (! schemaHasColumn('communication_messages', 'ai_confidence')) {
                $table->unsignedTinyInteger('ai_confidence')->nullable();
            }

            if (! schemaHasColumn('communication_messages', 'classification_status')) {
                $table->string('classification_status', 16)->default('pending')->index();
            }

            if (! schemaHasColumn('communication_messages', 'classification_error')) {
                $table->string('classification_error', 64)->nullable();
            }

            if (! schemaHasColumn('communication_messages', 'classified_at')) {
                $table->timestamp('classified_at')->nullable();
            }

            if (! schemaHasColumn('communication_messages', 'crm_contact_id')) {
                $table->unsignedBigInteger('crm_contact_id')->nullable()->index();
            }

            if (! schemaHasColumn('communication_messages', 'contact_link_status')) {
                $table->string('contact_link_status', 16)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('communication_messages')) {
            return;
        }

        Schema::table('communication_messages', function (Blueprint $table): void {
            foreach ([
                'ai_category',
                'ai_language',
                'ai_sentiment',
                'ai_action',
                'ai_confidence',
                'classification_status',
                'classification_error',
                'classified_at',
                'crm_contact_id',
                'contact_link_status',
            ] as $column) {
                if (schemaHasColumn('communication_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
