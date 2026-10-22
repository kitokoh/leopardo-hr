<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farm OS — T2.2 Materiel agricole (BC-37 AGRICULTURE, #8701).
 *
 * Registre du materiel (tracteurs, machines, vehicules, equipements), journal
 * des releves de compteur, entretiens planifies/realises, pannes et reparations,
 * couts de maintenance et documents associes.
 *
 * Invariants portes par le schema :
 *   - `meter_type` peut valoir `none` : un outil sans compteur n'est pas un
 *     outil incomplet, et Farm OS n'affiche alors AUCUN compteur plutot que
 *     d'en inventer un a zero ;
 *   - `meter_source` n'a que `manual` / `imported` : il n'existe aucune case
 *     `sensor` / `telemetry`, car Farm OS n'a aucune integration de telemetrie
 *     agricole. Une valeur n'est donc jamais presentee comme automatiquement
 *     mesuree (exigence d'honnetete de la mission) ;
 *   - `farm_equipment_usage_logs` est un JOURNAL : chaque releve de compteur y
 *     laisse exactement une ligne, jamais une mise a jour ;
 *   - `cost_amount` / `currency` NULLABLES : un cout inconnu reste inconnu, il
 *     n'est jamais remplace par zero (la rentabilite distingue reel / estime /
 *     manquant) ;
 *   - `file_id` / `external_url` NULLABLES sur les documents : Farm OS ne
 *     fabrique jamais de lien de telechargement.
 *
 * Conventions respectees (docs/architecture + lecons CI) :
 *   - `company_id` uuid NULLABLE + indexe sur CHAQUE table (isolation tenant) ;
 *   - references internes par identifiant stable SANS cle etrangere
 *     (conventions migrations tenant §2.6) ;
 *   - `timestampTz` partout ;
 *   - `up()` idempotent (`schemaTableExists`) — Render rejoue les migrations ;
 *   - `down()` ne detruit QUE ce que ce fichier a cree (garde #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('farm_equipment')) {
            Schema::create('farm_equipment', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                // Rattachement optionnel a une exploitation/site : un materiel
                // peut etre partage entre plusieurs sites.
                $table->unsignedBigInteger('farm_site_id')->nullable();

                $table->string('name', 160);
                $table->string('reference', 60)->nullable();
                // tractor | harvester | seeder | sprayer | trailer | truck | car |
                // irrigation_system | milking_machine | tool | other
                // (registre FERME cote application : enum FarmEquipmentKind).
                $table->string('kind', 40);
                $table->string('brand', 80)->nullable();
                $table->string('model', 80)->nullable();
                $table->string('serial_number', 80)->nullable();
                $table->string('plate_number', 32)->nullable();

                // Date d'acquisition DECLAREE (jamais deduite d'un document).
                $table->date('acquired_on')->nullable();
                // Cout d'acquisition : NULLABLE — inconnu reste inconnu.
                $table->decimal('acquisition_cost', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();

                // hours | kilometers | hectares | none (enum FarmEquipmentMeterType).
                $table->string('meter_type', 16)->default('none');
                // Derniere valeur de compteur CONNUE (saisie ou importee).
                $table->decimal('meter_value', 14, 2)->nullable();
                // manual | imported (enum FarmMeterSource) — jamais `sensor`.
                $table->string('meter_source', 16)->nullable();

                // active | maintenance | out_of_service | retired
                // (enum FarmEquipmentStatus). Etat DECLARE, jamais deduit.
                $table->string('status', 16)->default('active');

                // Affectation a un site, une equipe ou un conducteur.
                $table->unsignedBigInteger('assigned_team_id')->nullable();
                $table->unsignedBigInteger('assigned_employee_id')->nullable();

                $table->text('notes')->nullable();
                // Idempotence terrain : le rejeu d'une ecriture mobile/hors ligne
                // renvoie le materiel existant au lieu d'en creer un second.
                $table->string('client_equipment_id', 80)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_equipment_id'], 'farm_equipment_client_unique');
                $table->index(['company_id', 'status'], 'farm_equipment_company_status_index');
                $table->index(['company_id', 'kind'], 'farm_equipment_company_kind_index');
            });
        }

        if (! schemaTableExists('farm_equipment_usage_logs')) {
            Schema::create('farm_equipment_usage_logs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_equipment_id');
                // Valeur de compteur RELEVEE (saisie manuelle ou import).
                $table->decimal('meter_value', 14, 2);
                // Ecart avec le releve precedent : NULL au premier releve (on ne
                // suppose pas un compteur a zero au depart).
                $table->decimal('meter_delta', 14, 2)->nullable();
                // manual | imported (enum FarmMeterSource).
                $table->string('meter_source', 16)->default('manual');

                // Rattachement optionnel a une intervention / parcelle : une
                // utilisation de materiel n'est jamais saisie deux fois.
                $table->unsignedBigInteger('farm_operation_id')->nullable();
                $table->unsignedBigInteger('parcel_id')->nullable();
                $table->unsignedBigInteger('operator_id')->nullable();

                $table->string('reason', 200)->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_usage_id', 80)->nullable();
                $table->timestampTz('occurred_at');
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_usage_id'], 'farm_equipment_usage_client_unique');
                $table->index(['company_id', 'farm_equipment_id', 'occurred_at'], 'farm_equipment_usage_equipment_index');
            });
        }

        if (! schemaTableExists('farm_equipment_maintenances')) {
            Schema::create('farm_equipment_maintenances', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_equipment_id');
                // preventive | corrective (enum FarmMaintenanceType).
                $table->string('maintenance_type', 16);
                // planned | done | cancelled (enum FarmMaintenanceStatus).
                $table->string('status', 16)->default('planned');

                $table->string('title', 160);
                $table->text('description')->nullable();

                $table->timestampTz('planned_at')->nullable();
                $table->timestampTz('performed_at')->nullable();
                // Compteur au moment de l'entretien (NULLABLE : pas toujours connu).
                $table->decimal('meter_value_at', 14, 2)->nullable();

                // Cout : NULLABLE — un cout inconnu n'est jamais remplace par 0.
                $table->decimal('cost_amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                // Panne a l'origine d'un entretien correctif (NULLABLE).
                $table->unsignedBigInteger('breakdown_id')->nullable();

                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_maintenance_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_maintenance_id'], 'farm_equipment_maintenance_client_unique');
                $table->index(['company_id', 'farm_equipment_id', 'status'], 'farm_equipment_maintenance_equipment_index');
                $table->index(['company_id', 'planned_at'], 'farm_equipment_maintenance_planned_index');
            });
        }

        if (! schemaTableExists('farm_equipment_breakdowns')) {
            Schema::create('farm_equipment_breakdowns', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_equipment_id');
                // minor | major | critical (enum FarmBreakdownSeverity).
                // Gravite DECLAREE : Farm OS ne diagnostique jamais une panne.
                $table->string('severity', 16)->default('minor');

                $table->string('title', 160);
                $table->text('description')->nullable();

                $table->timestampTz('occurred_at');
                $table->timestampTz('resolved_at')->nullable();
                // Immobilisation en heures : NULLABLE (souvent inconnue).
                $table->decimal('downtime_hours', 10, 2)->nullable();

                $table->decimal('cost_amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();

                $table->text('notes')->nullable();
                $table->unsignedBigInteger('reported_by')->nullable();
                $table->string('client_breakdown_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_breakdown_id'], 'farm_equipment_breakdown_client_unique');
                $table->index(['company_id', 'farm_equipment_id', 'occurred_at'], 'farm_equipment_breakdown_equipment_index');
            });
        }

        if (! schemaTableExists('farm_equipment_documents')) {
            Schema::create('farm_equipment_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_equipment_id');
                // registration | insurance | invoice | manual | inspection | other.
                $table->string('document_type', 32)->default('other');
                $table->string('title', 160);

                // Reference vers le service de fichiers EXISTANT de Leopardo
                // (aucun stockage parallele) — NULLABLE : un document peut etre
                // seulement decrit, sans fichier joint.
                $table->unsignedBigInteger('file_id')->nullable();
                // Lien externe fourni par l'exploitant — jamais fabrique par Farm OS.
                $table->string('external_url', 500)->nullable();

                $table->date('issued_on')->nullable();
                $table->date('expires_on')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->index(['company_id', 'farm_equipment_id'], 'farm_equipment_documents_equipment_index');
                $table->index(['company_id', 'expires_on'], 'farm_equipment_documents_expiry_index');
            });
        }
    }

    public function down(): void
    {
        // Ordre inverse de creation : les enfants referencent `farm_equipment`.
        Schema::dropIfExists('farm_equipment_documents');
        Schema::dropIfExists('farm_equipment_breakdowns');
        Schema::dropIfExists('farm_equipment_maintenances');
        Schema::dropIfExists('farm_equipment_usage_logs');
        Schema::dropIfExists('farm_equipment');
    }
};
