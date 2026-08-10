<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (Schema::hasTable('languages')) {
            // La table existe déjà (créée par `2026_04_01_000003`, schéma sans
            // `updated_at`) : réconcilier les colonnes modernes au lieu de
            // simplement retourner — sinon les inserts Eloquent échouent
            // (`column updated_at does not exist`). Migration additive.
            if (! Schema::hasColumn('languages', 'updated_at')) {
                Schema::table('languages', function (Blueprint $table): void {
                    $table->timestampTz('updated_at')->useCurrent();
                });
            }
            if (! Schema::hasColumn('languages', 'name_native')) {
                Schema::table('languages', function (Blueprint $table): void {
                    $table->string('name_native', 50)->default('');
                });
            }

            return;
        }

        try {
            Schema::create('languages', function (Blueprint $table): void {
                $table->char('code', 2)->primary();
                $table->string('name_fr', 50);
                $table->string('name_native', 50);
                $table->boolean('is_rtl')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
                $table->index('is_active');
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '42P07') {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');
        Schema::dropIfExists('languages');
    }
};
