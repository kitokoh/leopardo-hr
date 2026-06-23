<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On MySQL/PostgreSQL, la colonne manager_role est VARCHAR — pas besoin de modifier l'ENUM
        // On ajoute juste un commentaire de documentation dans extra_data si besoin
        // La validation est faite au niveau du Request, pas de la DB
        // Pas de changement DDL nécessaire — la colonne est déjà VARCHAR nullable
    }

    public function down(): void
    {
        // Nothing to undo
    }
};
