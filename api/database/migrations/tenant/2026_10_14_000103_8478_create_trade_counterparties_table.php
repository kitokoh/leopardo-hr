<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8478 (TRADE-003) — BC-36 TRADE : table tenant `trade_counterparties`.
 *
 * SATELLITE du CRM : le referentiel clients/prospects/fournisseurs reste
 * `crm_accounts` (module CRM, non modifie — isolation inter-modules respectee).
 * Cette table l'enrichit du ROLE trade : un meme compte peut etre a la fois
 * acheteur et fournisseur selon la transaction (`roles` jsonb).
 *
 * `verification_status` : `none|pending|verified|rejected`. En v1 la
 * verification est une SAISIE MANUELLE TRACEE (`verified_by_employee_id`,
 * `verified_at`) — aucune API de verification n'etant configuree, le statut
 * `verified` ne doit jamais etre presente comme le resultat d'un controle
 * automatise (exigence mission : ne jamais inventer une verification).
 *
 * `crm_account_id` est un `unsignedBigInteger` nullable SANS contrainte FK :
 * la verticale fonctionne sur un tenant SANS le module CRM actif (lecon VTC-01 :
 * degradations gracieuses). Le renseigner quand le CRM est disponible offre la
 * timeline et la dedup CRM.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.2
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_counterparties')) {
            Schema::create('trade_counterparties', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                // Lien optionnel vers le referentiel CRM (module optionnel).
                $table->unsignedBigInteger('crm_account_id')->nullable();

                $table->string('name', 200);
                $table->char('country', 2)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('website', 255)->nullable();

                // Roles metier : buyer, supplier, manufacturer, wholesaler,
                // carrier, forwarder, customs_broker, inspector (multi-valués).
                $table->json('roles');

                $table->string('verification_status', 20)->default('none');
                $table->unsignedBigInteger('verified_by_employee_id')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->text('verification_note')->nullable();

                $table->json('capabilities')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'verification_status'], 'trade_counterparties_company_verif_idx');
                $table->index(['company_id', 'crm_account_id'], 'trade_counterparties_company_crm_idx');
            });

            DB::statement("COMMENT ON TABLE trade_counterparties IS 'Contreparties Trade - satellite du CRM, roles metier multiples, verification manuelle tracee uniquement (TRADE-003/#8478).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_counterparties');
    }
};
