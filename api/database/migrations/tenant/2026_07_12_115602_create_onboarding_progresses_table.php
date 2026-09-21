<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progression d'onboarding employé (`OnboardingProgress`, module HR).
 *
 * #7975 — cette migration vivait à la RACINE de database/migrations/, hors
 * du runner canonique (`leopardo:migrate` ne migre que `--path=.../public`
 * puis `--path=.../tenant`, cf. api/docker-entrypoint.sh) : la table n'était
 * donc JAMAIS créée par le pipeline de déploiement. Déplacée dans `tenant/`
 * (company_id uuid indexé porte le tenant, conventions §2.6).
 *
 * Le basename est conservé à l'identique : les bases où l'orpheline aurait
 * été exécutée hors runner (ex. `php artisan migrate` en local) l'ont déjà
 * enregistrée dans `migrations` et la sautent ; la garde `schemaTableExists`
 * (#1613) couvre les bases où la table existerait sans enregistrement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('onboarding_progresses')) {
            return;
        }

        Schema::create('onboarding_progresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('employee_id')->index();

            $table->string('current_step')->nullable()->default('welcome');
            $table->boolean('is_completed')->default(false);
            $table->json('completed_steps')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progresses');
    }
};
