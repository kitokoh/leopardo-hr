<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPrintJobRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * REST-OS-2 (Mission Leopardo Restaurant OS) — Implémentation Eloquent du port
 * de persistance des travaux d'impression (pattern RestaurantOrderRepository :
 * scoping tenant).
 *
 * Les transitions d'état sont des `UPDATE … WHERE …` conditionnels : la base
 * arbitre le conflit, pas le code appelant (invariant d'atomicité du bridge).
 */
final class RestaurantPrintJobRepository implements RestaurantPrintJobRepositoryInterface
{
    public function findForCompany(int $id, string $companyId): ?RestaurantPrintJob
    {
        return RestaurantPrintJob::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function findByKey(
        string $companyId,
        int $orderId,
        PrintTicketKind $kind,
        int $revision,
    ): ?RestaurantPrintJob {
        return RestaurantPrintJob::query()
            ->where('company_id', $companyId)
            ->where('order_id', $orderId)
            ->where('ticket_kind', $kind->value)
            ->where('revision', $revision)
            ->first();
    }

    public function claim(int $id, string $companyId, ?string $printerRef, int $leaseMinutes): int
    {
        return DB::table('restaurant_print_jobs')
            ->where('id', $id)
            ->where('company_id', $companyId)
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
    }

    public function markPrinted(int $id, string $companyId, ?string $printerRef): int
    {
        return $this->transitionTo(
            $id,
            $companyId,
            [
                'status' => PrintJobStatus::PRINTED->value,
                'printed_at' => now(),
                'last_error' => null,
                'printer_ref' => $printerRef,
                'updated_at' => now(),
            ],
            // `printer_ref` n'est pas écrasé par null : l'appelant a déjà résolu
            // la valeur de repli (travail existant) avant d'appeler le port.
        );
    }

    public function markFailed(int $id, string $companyId, string $error): int
    {
        return $this->transitionTo(
            $id,
            $companyId,
            [
                'status' => PrintJobStatus::FAILED->value,
                'failed_at' => now(),
                'last_error' => mb_substr($error, 0, 500),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Transition conditionnelle : jamais depuis un état terminal.
     *
     * @param  array<string, mixed>  $values
     */
    private function transitionTo(int $id, string $companyId, array $values): int
    {
        return DB::table('restaurant_print_jobs')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNotIn('status', [
                PrintJobStatus::PRINTED->value,
                PrintJobStatus::CANCELLED->value,
            ])
            ->update($values);
    }
}
