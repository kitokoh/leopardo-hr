<?php

declare(strict_types=1);

/**
 * #8754 [BOS-CHAT] — prépare un jeu de données JETABLE pour les tests de charge
 * k6 (`api/tests/k6/chat-load.js`, `chat-reconnection.js`).
 *
 * ⚠️ À n'exécuter QUE sur une base de test locale. Le script refuse de tourner
 * si `APP_ENV=production` ET que la base n'est pas explicitement une base de
 * test : il écrit des messages et émet des jetons.
 *
 * Usage :
 *   php api/tests/k6/seed-load-test.php
 *
 * Sortie : les variables d'environnement à passer à k6 (BASE_URL, TOKEN,
 * PEER_TOKEN, THREAD_ID), sur une seule ligne, prêtes à copier.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\ConversationThread;

$database = (string) config('database.connections.pgsql.database');

if (! str_contains($database, 'test')) {
    fwrite(STDERR, "Refus : la base « {$database} » ne ressemble pas à une base de test.\n");
    fwrite(STDERR, "Ce script écrit des messages et émet des jetons — jamais sur une base réelle.\n");
    exit(1);
}

// On passe par la FACTORY du dépôt : elle connaît les colonnes obligatoires
// (slug, sector, …). Les inventer une par une a déjà coûté deux échecs.
$company = Company::query()->where('slug', 'k6-load-test-co')->first()
    ?? Company::factory()->create([
        'name' => 'k6 Load Test Co',
        'slug' => 'k6-load-test-co',
        'email' => 'k6-load@example.test',
    ]);

$manager = Employee::query()->where('email', 'k6-manager@example.test')->first()
    ?? Employee::factory()->manager()->create([
        'company_id' => $company->id,
        'email' => 'k6-manager@example.test',
        'first_name' => 'K6',
        'last_name' => 'Manager',
    ]);

$employee = Employee::query()->where('email', 'k6-employee@example.test')->first()
    ?? Employee::factory()->create([
        'company_id' => $company->id,
        'manager_id' => $manager->id,
        'email' => 'k6-employee@example.test',
        'first_name' => 'K6',
        'last_name' => 'Employee',
    ]);

$thread = ConversationThread::query()->firstOrCreate(
    [
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'manager_id' => $manager->id,
    ],
    [
        'title' => 'Fil de test de charge k6',
        'kind' => 'direct',
    ],
);

// Jetons Sanctum : un par acteur, pour que le test de reconnexion puisse
// poster depuis un « second client ».
$employeeToken = $employee->createToken('k6-load-employee')->plainTextToken;
$managerToken = $manager->createToken('k6-load-manager')->plainTextToken;

echo "\n";
echo "Jeu de données prêt (base : {$database})\n";
echo "  company_id  : {$company->id}\n";
echo "  employee_id : {$employee->id}\n";
echo "  manager_id  : {$manager->id}\n";
echo "  thread_id   : {$thread->id}\n";
echo "\n";
echo "Variables pour k6 :\n\n";
echo "  BASE_URL=http://127.0.0.1:8001/api/v1 \\\n";
echo "  TOKEN={$employeeToken} \\\n";
echo "  PEER_TOKEN={$managerToken} \\\n";
echo "  THREAD_ID={$thread->id} \\\n";
echo "  k6 run -e SCENARIO=smoke api/tests/k6/chat-load.js\n\n";
