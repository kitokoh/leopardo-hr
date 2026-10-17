<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-a + P1-b (audit FuelStation 2026-10-17) — moyens de paiement et
 * annulation compensatoire des ventes.
 *
 * CONSTAT (audit, verifie dans le code)
 * 1. `fuel_sales` ne portait AUCUNE colonne de reglement. Les CHECKs
 *    n'autorisaient que `source IN ('manual','kiosk','pos')`, qui decrit la
 *    PROVENANCE de la saisie, pas la NATURE du reglement. Une vente ne pouvait
 *    donc pas etre rattachee a un mode de paiement (especes, carte, bon,
 *    compte professionnel) et la cloture de caisse ne pouvait pas etre
 *    rapprochee par nature de reglement (domaine E « Caisse et reglements »).
 * 2. Aucune action `cancel`/`void` n'existait sur `FuelSale` : une vente
 *    erronee ne pouvait etre ni annulee ni corrigee par l'API. Le §6 impose
 *    qu'une correction NE DOIT PAS effacer l'historique financier.
 *
 * CORRECTIF
 * - `payment_method` (nullable, defaut `cash`) + `payment_reference` : la
 *   NATURE du reglement est tracee. Aucune donnee de carte n'est jamais
 *   stockee (pas de PAN) — seule une reference externe opaque (numero de TPE,
 *   bon, compte pro) est acceptee.
 * - `status` (defaut `completed`) + `cancelled_at` / `cancelled_by` /
 *   `cancellation_reason` : l'annulation est une ECRITURE COMPENSATOIRE. La
 *   ligne d'origine est CONSERVEE et marquee `cancelled` ; aucun DELETE. Le
 *   montant, la quantite et l'operateur restent lisibles apres annulation.
 *
 * Migration additive et idempotente (garde schemaTableExists / hasColumn,
 * CHECKs gardes par pg_constraint) — pattern des migrations FUEL existantes.
 * Aucune vente existante n'est invalidee : les deux colonnes ont un defaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_sales')) {
            return;
        }

        Schema::table('fuel_sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('fuel_sales', 'payment_method')) {
                $table->string('payment_method', 20)->default('cash');
            }

            if (! Schema::hasColumn('fuel_sales', 'payment_reference')) {
                // Reference externe opaque (TPE, bon, compte pro) — jamais un PAN.
                $table->string('payment_reference', 120)->nullable();
            }

            if (! Schema::hasColumn('fuel_sales', 'status')) {
                $table->string('status', 20)->default('completed');
            }

            if (! Schema::hasColumn('fuel_sales', 'cancelled_at')) {
                $table->timestampTz('cancelled_at')->nullable();
            }

            if (! Schema::hasColumn('fuel_sales', 'cancelled_by')) {
                $table->unsignedInteger('cancelled_by')->nullable();
            }

            if (! Schema::hasColumn('fuel_sales', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
        });

        $schema = resolveTableSchema('fuel_sales');

        if ($schema === null) {
            return;
        }

        // Index de ventilation de l'encaissement par nature de reglement.
        if (! $this->constraintExists('fuel_sales_company_payment_idx')) {
            DB::statement(
                "CREATE INDEX fuel_sales_company_payment_idx ON {$schema}.fuel_sales (company_id, payment_method)"
            );
        }

        if (! $this->constraintExists('fuel_sales_payment_method_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_sales ADD CONSTRAINT fuel_sales_payment_method_check "
                ."CHECK (payment_method IN ('cash', 'card', 'account', 'voucher', 'other'))"
            );
        }

        if (! $this->constraintExists('fuel_sales_status_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_sales ADD CONSTRAINT fuel_sales_status_check "
                ."CHECK (status IN ('completed', 'cancelled'))"
            );
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('fuel_sales')) {
            return;
        }

        $schema = resolveTableSchema('fuel_sales');

        if ($schema !== null) {
            foreach (['fuel_sales_payment_method_check', 'fuel_sales_status_check'] as $constraint) {
                if ($this->constraintExists($constraint)) {
                    DB::statement("ALTER TABLE {$schema}.fuel_sales DROP CONSTRAINT {$constraint}");
                }
            }

            DB::statement('DROP INDEX IF EXISTS '.$schema.'.fuel_sales_company_payment_idx');
        }

        Schema::table('fuel_sales', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_method',
                'payment_reference',
                'status',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
            ]);
        });
    }

    private function constraintExists(string $name): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]) !== null;
    }
};
