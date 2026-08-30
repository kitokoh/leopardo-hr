<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Console\Commands;

use App\Modules\Delivery\Application\Jobs\CloseDeliveryRouteJob;
use App\Modules\Delivery\Application\Jobs\ExportDeliveryReportJob;
use App\Modules\Delivery\Domain\Models\DeliveryDeadLetter;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Rejeu contrôlé de la dead-letter queue du module Delivery (BC-26-D07,
 * issue #6295).
 *
 * Usage :
 *   php artisan delivery:replay-dlq            # rejoue toutes les DLQ 'new'
 *   php artisan delivery:replay-dlq --id=42    # rejoue une DLQ précise
 *
 * Le rejeu re-dispatch le job d'origine (payload stocké) ; l'idempotence
 * métier des jobs (clôture déjà close, même runKey d'export) garantit zéro
 * doublon. La DLQ passe `replayed` ; si le re-dispatch échoue à la
 * validation, elle passe `failed` (à trier manuellement).
 */
final class ReplayDeliveryDlqCommand extends Command
{
    protected $signature = 'delivery:replay-dlq {--id= : Rejouer une DLQ précise (id)}';

    protected $description = 'Rejoue les dead letters des jobs Delivery (clôture/export) sans doublon métier';

    public function handle(): int
    {
        $query = DeliveryDeadLetter::query()->where('status', 'new');

        if ($this->option('id') !== null) {
            $query->whereKey((int) $this->option('id'));
        }

        $replayed = 0;
        $failed = 0;

        $query->orderBy('id')->get()->each(function (DeliveryDeadLetter $letter) use (&$replayed, &$failed): void {
            try {
                /** @var ShouldQueue|null $job */
                $job = $this->rebuild($letter);

                if ($job === null) {
                    $letter->forceFill(['status' => 'failed', 'error' => 'Unknown job_class: '.$letter->job_class])->save();
                    $failed++;

                    return;
                }

                dispatch($job)->onQueue($letter->queue);

                $letter->markReplayed();
                $replayed++;

                Log::info('delivery.dlq.replayed', [
                    'dead_letter_id' => $letter->id,
                    'company_id' => $letter->company_id,
                    'job_class' => $letter->job_class,
                ]);
            } catch (\Throwable $exception) {
                $failed++;

                Log::error('delivery.dlq.replay_failed', [
                    'dead_letter_id' => $letter->id,
                    'company_id' => $letter->company_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        $this->info("DLQ Delivery — rejouées : {$replayed}, échecs : {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function rebuild(DeliveryDeadLetter $letter): ?object
    {
        $payload = is_array($letter->payload) ? $letter->payload : [];

        return match ($letter->job_class) {
            CloseDeliveryRouteJob::class => CloseDeliveryRouteJob::fromPayload($payload),
            ExportDeliveryReportJob::class => ExportDeliveryReportJob::fromPayload($payload),
            default => null,
        };
    }
}
