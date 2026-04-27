<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // En mode shared_tenants, on boucle sur tous les employés.
        // Le simple fait de charger et sauvegarder le modèle déclenche le cast 'encrypted'.
        Employee::withoutGlobalScopes()->chunk(100, function ($employees) {
            foreach ($employees as $employee) {
                // On ne sauvegarde que si l'un des champs est présent pour éviter des écritures inutiles
                if ($employee->iban || $employee->bank_account || $employee->national_id) {
                    $employee->save();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Attention : le passage en arrière nécessite de désactiver le cast 
        // ou de gérer manuellement le déchiffrement.
        // Étant donné que c'est une mesure de sécurité critique, le rollback 
        // n'est pas automatique ici pour éviter de corrompre les données déchiffrées.
    }
};
