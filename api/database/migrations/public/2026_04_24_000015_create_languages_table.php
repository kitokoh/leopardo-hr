<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ML-01 — Table publique : languages
 *
 * Table référentielle des langues supportées par la plateforme.
 * Source de vérité pour les locales (fr, ar, tr, en).
 * L'arabe (ar) est la seule langue RTL.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('languages')) {
            try {
                Schema::create('languages', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');
        Schema::dropIfExists('languages');
    }
};
