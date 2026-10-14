<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8482 (TRADE-007) — BC-36 TRADE : table tenant `trade_customs_cases`.
 *
 * AIDE A LA PREPARATION DOUANIERE — et JAMAIS une decision douaniere.
 *
 * Le systeme rassemble : description produit, code HS (nullable), origine et
 * destination, LISTE DETERMINISTE des documents potentiellement requis,
 * estimation des droits/taxes A PARTIR DE SOURCES IDENTIFIEES, et les
 * INFORMATIONS MANQUANTES (`missing_info`, calcule automatiquement).
 *
 * REGLE DE CONFORMITE LEGALE (exigence mission §11) : Leopardo facilite
 * l'estimation, la preparation documentaire et la mise en relation — il ne
 * realise ni ne pretend realiser une declaration officielle. Le texte de
 * non-responsabilite est systematiquement renvoye par l'API, et
 * `disclaimer_acknowledged_at` doit etre pose AVANT tout partage ou export
 * du dossier.
 *
 * `duty_rate_percent` est nullable et sa provenance est tracee
 * (`duty_source` : `manual|<provider>`) : aucun taux n'est invente, et en
 * l'absence d'un fournisseur de tarifs configure, le taux reste nul et le
 * cas est marque comme non estime.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.5
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_customs_cases')) {
            Schema::create('trade_customs_cases', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trade_deal_id')->nullable();
                $table->unsignedBigInteger('trade_order_id')->nullable();

                $table->text('product_description');
                $table->string('hs_code', 12)->nullable();

                $table->char('origin_country', 2);
                $table->char('destination_country', 2);

                $table->string('status', 20)->default('incomplete');

                $table->json('required_documents')->nullable();
                $table->json('missing_info')->nullable();

                $table->decimal('duty_rate_percent', 7, 4)->nullable();
                $table->string('duty_source', 40)->nullable();
                $table->bigInteger('duty_amount_minor')->nullable();
                $table->char('duty_currency', 3)->nullable();
                $table->json('taxes')->nullable();

                $table->timestamp('disclaimer_acknowledged_at')->nullable();

                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by_employee_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status'], 'trade_customs_cases_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE trade_customs_cases IS 'Aide a la preparation douaniere Trade (jamais une decision officielle) - documents requis deterministes, informations manquantes, droits estimes depuis source tracee, disclaimer obligatoire (TRADE-007/#8482).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_customs_cases');
    }
};
