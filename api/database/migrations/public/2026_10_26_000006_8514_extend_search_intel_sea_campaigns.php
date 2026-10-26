<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GSI-3 (#8514) — colonnes SEA manquantes.
 *
 * La fondation GSI-1 avait ouvert `campaigns` avec le strict minimum (canal,
 * nom, statut, budget, dates). GSI-3 a besoin de trois choses de plus, et
 * chacune répond à un besoin réel plutôt qu'à une symétrie de schéma :
 *
 *  - `objective` — sans objectif explicite, une « conversion » ne veut rien
 *    dire : un lead, un appel et une vente ne se comparent pas.
 *  - `audience` (JSON) — pays et langues visés ; une campagne sans audience
 *    déclarée ne peut pas être confrontée à ses résultats par marché.
 *  - `utm_campaign` / `landing_page` — le lien avec les données d'atterrissage.
 *
 * `awaiting_validation` s'ajoute à l'enum de statut : la validation humaine
 * avant activation (GSI-3) exige un état où la campagne est prête mais pas
 * encore en dépense. En PostgreSQL, on remplace la contrainte CHECK générée
 * par l'enum Laravel au lieu d'en ajouter une seconde, qui serait redondante.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('campaigns', 'objective', function (Blueprint $table): void {
            $table->string('objective', 32)->nullable();
        });

        $this->addColumn('campaigns', 'audience', function (Blueprint $table): void {
            $table->jsonb('audience')->nullable();
        });

        $this->addColumn('campaigns', 'utm_campaign', function (Blueprint $table): void {
            $table->string('utm_campaign')->nullable();
        });

        $this->addColumn('campaigns', 'landing_page', function (Blueprint $table): void {
            $table->string('landing_page', 512)->nullable();
        });

        $this->addColumn('campaigns', 'total_budget_cents', function (Blueprint $table): void {
            $table->bigInteger('total_budget_cents')->nullable();
        });

        $this->addColumn('campaigns', 'validated_by', function (Blueprint $table): void {
            $table->unsignedBigInteger('validated_by')->nullable();
        });

        $this->addColumn('campaigns', 'validated_at', function (Blueprint $table): void {
            $table->timestamp('validated_at')->nullable();
        });

        // Remplacement de la contrainte d'enum : PostgreSQL nomme
        // automatiquement la contrainte `campaigns_status_check`.
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_status_check');
        DB::statement(
            "ALTER TABLE campaigns ADD CONSTRAINT campaigns_status_check "
            ."CHECK (status IN ('draft', 'awaiting_validation', 'active', 'paused', 'ended'))"
        );

        // Une campagne `active` sans validation humaine contredirait la règle
        // centrale de GSI-3. Le CHECK la rend impossible même en SQL direct.
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_active_requires_validation');
        DB::statement(
            "ALTER TABLE campaigns ADD CONSTRAINT campaigns_active_requires_validation "
            ."CHECK (status <> 'active' OR validated_at IS NOT NULL)"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_active_requires_validation');

        // On remet la contrainte d'origine, en réaffectant au préalable les
        // lignes qui portent le nouveau statut — sinon le CHECK échouerait.
        DB::statement("UPDATE campaigns SET status = 'draft' WHERE status = 'awaiting_validation'");
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_status_check');
        DB::statement(
            "ALTER TABLE campaigns ADD CONSTRAINT campaigns_status_check "
            ."CHECK (status IN ('draft', 'active', 'paused', 'ended'))"
        );

        foreach ([
            'objective',
            'audience',
            'utm_campaign',
            'landing_page',
            'total_budget_cents',
            'validated_by',
            'validated_at',
        ] as $column) {
            if ($this->publicColumnExists('campaigns', $column)) {
                Schema::table('campaigns', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Ajout conditionnel : la migration reste rejouable sur un schéma déjà
     * enrichi, sans lever « column already exists ».
     *
     * @param  callable(Blueprint): void  $definition
     */
    private function addColumn(string $table, string $column, callable $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, $definition);
    }

    /**
     * `Schema::hasTable()` / `hasColumn()` résolvent via `current_schema()`,
     * qui vaut `shared_tenants` hors du harnais de test : un garde sur une
     * table `public` renvoie alors systématiquement `false` et la migration
     * saute ses ALTER sans rien signaler. On interroge `public` explicitement.
     */
    private function publicTableExists(string $table): bool
    {
        /** @var object{exists: bool}|null $row */
        $row = DB::selectOne(
            'select exists (select 1 from information_schema.tables where table_schema = ? and table_name = ?) as exists',
            ['public', $table],
        );

        return $row !== null && (bool) $row->exists;
    }

    private function publicColumnExists(string $table, string $column): bool
    {
        /** @var object{exists: bool}|null $row */
        $row = DB::selectOne(
            'select exists (select 1 from information_schema.columns '
            .'where table_schema = ? and table_name = ? and column_name = ?) as exists',
            ['public', $table, $column],
        );

        return $row !== null && (bool) $row->exists;
    }

};
