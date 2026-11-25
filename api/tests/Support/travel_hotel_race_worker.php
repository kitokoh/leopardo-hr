<?php

declare(strict_types=1);

/**
 * M4 — worker de course pour `TravelHotelInventoryConcurrencyTest`.
 *
 * Lancé comme PROCESSUS OS séparé (un par concurrent) par le test, afin de
 * produire une vraie course sur l'inventaire hôtelier : deux connexions PDO
 * distinctes, deux transactions distinctes, un seul verrou `FOR UPDATE` qui
 * doit sérialiser les deux.
 *
 * Usage :
 *   php tests/Support/travel_hotel_race_worker.php \
 *       <companyId> <hotelId> <roomId> <checkIn> <checkOut> <idempotencyKey>
 *
 * Sortie : une ligne JSON `{"status": <http>, "body": "<message>"}` sur stdout.
 * Le code de sortie est toujours 0 quand un verdict a pu être rendu — c'est le
 * `status` qui porte le résultat métier (201 gagnant, 409 perdant).
 */

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\BookHotelRoomAction;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $companyId, $hotelId, $roomId, $checkIn, $checkOut, $idempotencyKey, $database] = $argv + array_fill(0, 8, null);

if ($companyId === null || $roomId === null || $checkIn === null || $checkOut === null || $idempotencyKey === null) {
    fwrite(STDERR, "Missing arguments.\n");
    exit(2);
}

// Base forcée par le test : en `--parallel`, chaque processus PHPUnit travaille
// sur sa propre base (`leopardo_test_<token>`). Sans ce forçage, le worker
// ouvrirait la connexion de `.env`/config et ne verrait pas les lignes commitées
// par le test — il échouait alors en 500 « ModelNotFoundException ».
if ($database !== null && $database !== '') {
    config(['database.connections.'.config('database.default').'.database' => $database]);
    DB::purge();
}

try {
    /** @var Company $company */
    $company = Company::query()->findOrFail($companyId);

    $status = 201;
    $body = '';

    app(TenantManager::class)->withinTenant($company, function () use ($company, $roomId, $checkIn, $checkOut, $idempotencyKey, &$status, &$body): void {
        /** @var Employee $actor */
        $actor = Employee::query()
            ->where('company_id', $company->id)
            ->where('role', 'manager')
            ->firstOrFail();

        /** @var TravelHotelRoom $room */
        $room = TravelHotelRoom::query()->findOrFail($roomId);

        try {
            $booking = app(BookHotelRoomAction::class)->execute(
                room: $room,
                actor: $actor,
                idempotencyKey: $idempotencyKey,
                data: ['check_in' => $checkIn, 'check_out' => $checkOut, 'rooms' => 1],
            );

            $status = 201;
            $body = (string) $booking->reference;
        } catch (HttpExceptionInterface $exception) {
            // 409 (plus de disponibilité) ou 422 : refus métier propre.
            $status = $exception->getStatusCode();
            $body = $exception->getMessage();
        }
    });

    echo json_encode(['status' => $status, 'body' => $body], JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    // Toute autre erreur (violation de contrainte brute, deadlock non géré)
    // est un ÉCHEC du contrat : on la remonte en 500 pour que le test la voie.
    echo json_encode([
        'status' => 500,
        'body' => $exception::class.': '.$exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(0);
} finally {
    try {
        DB::disconnect();
    } catch (Throwable) {
        // Rien à faire : le processus se termine.
    }
}
