<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * auditable_id doit pouvoir accueillir des identifiants UUID (ex. Company,
     * Employee), pas seulement des entiers : le polymorphisme AuditLog est
     * utilisé par des modèles à clé UUID (réparation pays tenant #1873).
     * Les valeurs numériques existantes restent valides (cast texte).
     */
    public function up(): void
    {
        $schema = resolveTableSchema('audit_logs');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.audit_logs", function (Blueprint $table): void {
            $table->string('auditable_id', 36)->change();
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('audit_logs');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.audit_logs", function (Blueprint $table): void {
            $table->unsignedBigInteger('auditable_id')->change();
        });
    }
};
