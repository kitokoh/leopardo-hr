<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8477 (TRADE-002) — BC-36 TRADE : table tenant `trade_company_profiles`.
 *
 * Profil commercial de l'entreprise (1:1 company). Porte les DIRECTIONS
 * (`import_enabled` / `export_enabled` — independants, une entreprise peut
 * faire les deux : aucun enfermement dans un role irreversible) et la portee
 * domestique (`domestic_scope`, grossiste B2B local reutilisant le meme cycle
 * commercial que l'export).
 *
 * Creation PARESSEUSE et IDEMPOTENTE par `TradeOnboardingService` — un profil
 * unique par tenant (index unique `company_id`) : un double POST d'onboarding
 * met a jour la meme ligne, jamais de doublon.
 *
 * Montants en minor units entiers, devises `char(3)` ISO 4217, pays `char(2)`
 * ISO 3166-1 alpha-2. Tenant-first, aucune FK inter-tenants.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.1
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_company_profiles')) {
            Schema::create('trade_company_profiles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->boolean('import_enabled')->default(false);
                $table->boolean('export_enabled')->default(false);
                $table->boolean('domestic_scope')->default(false);

                $table->char('hq_country', 2);
                $table->json('markets_served')->nullable();
                $table->json('product_categories')->nullable();

                $table->char('default_currency', 3);
                $table->string('default_incoterm', 3)->nullable();

                // Capacites declaratives (production, MOQ types, certifications) :
                // texte libre structure — JAMAIS presentees comme verifiees.
                $table->json('capabilities')->nullable();
                $table->text('notes')->nullable();

                $table->timestamp('onboarding_completed_at')->nullable();

                $table->timestamps();

                $table->unique('company_id', 'trade_company_profiles_company_unique');
            });

            DB::statement("COMMENT ON TABLE trade_company_profiles IS 'Profil commercial Trade (1:1 company) - directions import/export, portee domestique, pays/marches, devise et Incoterm par defaut (TRADE-002/#8477).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_company_profiles');
    }
};
