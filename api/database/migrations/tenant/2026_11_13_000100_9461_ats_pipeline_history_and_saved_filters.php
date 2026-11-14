<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ATS — historique du pipeline et filtres sauvegardés.
 *
 * Deux manques constatés sur le module Recruitment :
 *
 * 1. `applicants.status` est une colonne écrasée à chaque changement. Un
 *    recruteur qui rouvre un dossier ne peut pas savoir QUI l'a fait passer de
 *    `screening` à `rejected`, NI QUAND. C'est pourtant l'information qu'on
 *    demande en cas de litige, et celle qui permet de mesurer le délai réel de
 *    traitement par étape. `applicant_status_events` est un journal APPEND-ONLY :
 *    on n'y met jamais à jour une ligne, on en ajoute une.
 *
 * 2. Les filtres ATS (statut, note minimale, ancienneté, recherche plein texte)
 *    étaient reconstruits à la main à chaque visite. `ats_saved_filters` les
 *    persiste par (entreprise, offre éventuelle) pour qu'un recruteur retrouve
 *    sa vue de travail.
 *
 * Migration ADDITIVE uniquement : aucune table existante n'est recréée, et
 * chaque création est gardée par `schemaTableExists` pour rester rejouable sur
 * une base où le lot aurait déjà été appliqué.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! schemaTableExists('applicant_status_events')) {
            Schema::create('applicant_status_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('applicant_id');
                $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
                // `null` = première entrée du dossier (candidature déposée) :
                // il n'y a pas de statut « d'avant ».
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20);
                // Auteur du changement. `null` = changement automatique (dépôt
                // public, purge RGPD) : on ne fabrique pas un employé fantôme.
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('actor_name', 120)->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestampTz('occurred_at');
                $table->timestampsTz();

                // Le seul accès chaud : « l'historique de CE dossier, du plus
                // récent au plus ancien ».
                $table->index(['applicant_id', 'occurred_at'], 'applicant_status_events_applicant_idx');
                $table->index(['company_id', 'to_status'], 'applicant_status_events_company_status_idx');
            });
        }

        if (! schemaTableExists('ats_saved_filters')) {
            Schema::create('ats_saved_filters', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // `null` = vue transverse à toutes les offres de l'entreprise.
                $table->unsignedBigInteger('job_posting_id')->nullable();
                $table->foreign('job_posting_id')->references('id')->on('job_postings')->cascadeOnDelete();
                $table->string('name', 120);
                // Critères sérialisés (statuts, note minimale, ancienneté, q…).
                // jsonb et non json : on veut pouvoir interroger les critères
                // sans désérialiser côté SQL si le besoin apparaît.
                $table->jsonb('criteria');
                $table->unsignedInteger('created_by')->nullable();
                $table->boolean('is_shared')->default(false);
                $table->timestampsTz();

                $table->index(['company_id', 'job_posting_id'], 'ats_saved_filters_company_job_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ats_saved_filters');
        Schema::dropIfExists('applicant_status_events');
    }
};
