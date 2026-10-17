<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Réclamation d'un travail
 * d'impression par le bridge local (REST-OS-3).
 *
 * Réclamation ATOMIQUE : `UPDATE ... WHERE status IN (pending, failed)` (ou
 * `claimed` dont le bail est expiré — bridge planté). La condition sur le
 * statut garantit qu'un même travail n'est jamais remis à deux instances du
 * bridge simultanément : le premier gagne (`affected = 1`), les autres voient
 * `affected = 0`. `attempts` est incrémenté à chaque tentative — un travail qui
 * échoue reste réessayable, jamais perdu.
 *
 * Le statut ne devient `printed` QUE sur accusé (voir AckPrintJobAction) : une
 * réclamation ne vaut jamais preuve d'impression.
 */
final class ClaimPrintJobAction
{
    public function execute(RestaurantPrintJob $job, ?string $printerRef = null): ?RestaurantPrintJob
    {
        $leaseMinutes = is_numeric(config('restaurantmanager.printing.claim_lease_minutes', 15))
            ? (int) config('restaurantmanager.printing.claim_lease_minutes', 15)
            : 15;

        $claimed = DB::table('restaurant_print_jobs')
            ->where('id', $job->id)
            ->where('company_id', $job->company_id)
            ->where(function (Builder $query) use ($leaseMinutes): void {
                $query->whereIn('status', [
                    PrintJobStatus::PENDING->value,
                    PrintJobStatus::FAILED->value,
                ])->orWhere(function (Builder $query) use ($leaseMinutes): void {
                    $query->where('status', PrintJobStatus::CLAIMED->value)
                        ->where('claimed_at', '<', now()->subMinutes($leaseMinutes));
                });
            })
            ->update([
                'status' => PrintJobStatus::CLAIMED->value,
                'attempts' => DB::raw('attempts + 1'),
                'claimed_at' => now(),
                'printer_ref' => $printerRef,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return $job->refresh();
    }
}
