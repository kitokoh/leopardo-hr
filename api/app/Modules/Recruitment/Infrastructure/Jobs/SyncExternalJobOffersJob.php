<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Jobs;

use App\Modules\Recruitment\Domain\Models\JobSource;
use App\Modules\Recruitment\Infrastructure\Services\SyncExternalJobOffers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * BC-10 RECRUITMENT — synchronisation d'un connecteur d'agrégateur en tâche de
 * fond.
 *
 * Le job ne décide de RIEN : il délègue au service `SyncExternalJobOffers`, qui
 * porte les invariants (idempotence, honnêteté de l'état, non-suppression).
 * Cela garantit qu'une synchronisation déclenchée depuis l'écran super admin et
 * une synchronisation planifiée se comportent exactement pareil.
 */
class SyncExternalJobOffersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $sourceId,
        public readonly ?int $actorId = null,
    ) {}

    public function handle(SyncExternalJobOffers $service): void
    {
        $source = JobSource::query()->find($this->sourceId);

        if ($source === null) {
            return;
        }

        $service->execute($source, $this->actorId);
    }
}
