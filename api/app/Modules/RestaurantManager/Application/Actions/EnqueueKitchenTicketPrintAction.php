<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Contracts\OrderTicketRendererInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPrintJobRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Met un ticket cuisine en file
 * d'impression thermique.
 *
 * IDEMPOTENT par clé métier (tenant, commande, type, révision) : rejouer la
 * demande (double clic serveur, retry réseau) ne crée JAMAIS de second travail
 * et ne réimprime pas. Le flux ESC/POS est rendu ici (serveur) et résumé par un
 * `payload_hash` — le bridge local (REST-OS-3) transmet les octets à
 * l'imprimante, l'API ne touche pas au matériel. La mise en file publie un
 * événement outbox (payload redigé) pour l'observabilité.
 *
 * `revision` :
 *  - 1 = première impression d'une commande ;
 *  - > 1 = réimpression explicitement demandée (jamais automatique).
 */
final class EnqueueKitchenTicketPrintAction
{
    public const EVENT_JOB_ENQUEUED = 'restaurant.print.job.enqueued.v1';

    public function __construct(
        private readonly BuildKitchenTicketAction $buildTicket,
        private readonly OrderTicketRendererInterface $renderer,
        private readonly RestaurantPrintJobRepositoryInterface $jobs,
        private readonly RestaurantOutboxPublisher $outbox,
    ) {}

    public function execute(
        RestaurantOrder $order,
        PrintTicketKind $kind = PrintTicketKind::KITCHEN,
        int $revision = 1,
        ?string $printerRef = null,
        ?string $paperWidth = null,
    ): RestaurantPrintJob {
        $existing = $this->jobs->findByKey(
            (string) $order->company_id,
            (int) $order->id,
            $kind,
            $revision,
        );

        if ($existing instanceof RestaurantPrintJob) {
            return $existing;
        }

        $ticket = $this->buildTicket->execute($order, $revision);
        $payload = $this->renderer->render($ticket, $this->paperWidth($paperWidth));

        $attributes = [
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'ticket_kind' => $kind->value,
            'revision' => $revision,
            'status' => PrintJobStatus::PENDING->value,
            'attempts' => 0,
            'printer_ref' => $printerRef,
            'payload_hash' => hash('sha256', $payload),
        ];

        try {
            $job = RestaurantPrintJob::query()->create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Course concurrente : le travail a été créé par un appel parallèle.
            /** @var RestaurantPrintJob $job */
            $job = $this->jobs->findByKey(
                (string) $order->company_id,
                (int) $order->id,
                $kind,
                $revision,
            ) ?? RestaurantPrintJob::query()->create($attributes);
        }

        $this->outbox->publish(
            (string) $order->company_id,
            self::EVENT_JOB_ENQUEUED,
            [
                'print_job_id' => $job->id,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'branch_id' => $order->branch_id,
                'ticket_kind' => $kind->value,
                'revision' => $revision,
            ],
            'print_job:enqueued:'.$job->id,
        );

        return $job;
    }

    private function paperWidth(?string $override): int
    {
        if ($override !== null && is_numeric($override)) {
            return (int) $override;
        }

        return (int) config('restaurantmanager.printing.paper_width', 80);
    }
}
