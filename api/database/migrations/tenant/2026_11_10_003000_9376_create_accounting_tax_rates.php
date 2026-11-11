<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #9376.
 *
 * `accounting_documents` ne portait qu'**un seul taux de TVA** (`tva_rate`)
 * appliqué au document entier. Or `AccountingSettingsDefaults` déclare du
 * multi-taux (DZ 19/9, TR 20/10/1, CA TPS+TVQ par province) : ces taux étaient
 * donc *déclarés mais inapplicables*. Une facture turque mêlant un service à
 * 20 % et un livre à 1 % ne pouvait pas être saisie correctement.
 *
 * `accounting_document_lines.tax_id` existait, mais n'était relié à **aucune**
 * table : champ orphelin, jamais valorisé, jamais consommé.
 *
 * Cette migration crée le référentiel `accounting_tax_rates` (par tenant, seedé
 * depuis les défauts pays) et ajoute aux lignes de document le taux appliqué,
 * dénormalisé volontairement pour que la modification ultérieure du référentiel
 * ne réécrive pas l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_tax_rates')) {
            Schema::create('accounting_tax_rates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('label', 120);
                $table->decimal('rate', 7, 4);
                $table->char('country', 2)->nullable();
                // standard | reduced | super_reduced | zero | exempt
                $table->string('kind', 20)->default('standard');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'accounting_tax_rates_company_code_unique');
                $table->index(['company_id', 'is_active']);
            });
        }

        if (schemaTableExists('accounting_document_lines')) {
            if (! schemaHasColumn('accounting_document_lines', 'tax_rate_id')) {
                Schema::table('accounting_document_lines', function (Blueprint $table): void {
                    // Référence logique vers accounting_tax_rates (pas de FK
                    // physique : le référentiel est par tenant et supprimable).
                    $table->unsignedBigInteger('tax_rate_id')->nullable()->after('discount');
                });
            }

            if (! schemaHasColumn('accounting_document_lines', 'tax_rate')) {
                Schema::table('accounting_document_lines', function (Blueprint $table): void {
                    // Taux dénormalisé : une facture passée reste lisible même si
                    // le référentiel de taxes est modifié ou re-seedé.
                    $table->decimal('tax_rate', 7, 4)->nullable()->after('tax_rate_id');
                });
            }

            if (! schemaHasColumn('accounting_document_lines', 'tax_amount')) {
                Schema::table('accounting_document_lines', function (Blueprint $table): void {
                    // Montant de taxe de la ligne (base × taux), arrondi half-up.
                    $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
                });
            }

            // Backfill mono-taux : sur un document existant, chaque ligne portait
            // implicitement le taux du document. On l'explicite au prorata de son
            // montant HT, ce qui reproduit exactement le total du document.
            DB::statement(<<<'SQL'
                UPDATE accounting_document_lines AS l
                SET tax_rate = d.tva_rate,
                    tax_amount = CASE
                        WHEN d.subtotal_ht > 0
                            THEN ROUND((l.quantity * l.unit_price * (1 - COALESCE(l.discount, 0) / 100)) * d.tva_rate / 100, 2)
                        ELSE 0
                    END
                FROM accounting_documents AS d
                WHERE l.document_id = d.id
                  AND l.tax_rate IS NULL
                  AND d.tva_rate IS NOT NULL
            SQL);
        }

        // Suppression du champ orphelin `tax_id` (aucune table de taxes ne lui
        // correspondait, aucune valeur n'a jamais été écrite) — remplacer deux
        // champs concurrents par un seul, `tax_rate_id`.
        if (schemaTableExists('accounting_document_lines') && schemaHasColumn('accounting_document_lines', 'tax_id')) {
            Schema::table('accounting_document_lines', function (Blueprint $table): void {
                $table->dropColumn('tax_id');
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('accounting_document_lines')) {
            if (schemaHasColumn('accounting_document_lines', 'tax_rate')) {
                Schema::table('accounting_document_lines', function (Blueprint $table): void {
                    $table->dropColumn(['tax_rate_id', 'tax_rate', 'tax_amount']);
                });
            }

            if (! schemaHasColumn('accounting_document_lines', 'tax_id')) {
                Schema::table('accounting_document_lines', function (Blueprint $table): void {
                    $table->string('tax_id', 60)->nullable();
                });
            }
        }

        Schema::dropIfExists('accounting_tax_rates');
    }
};
