<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPrintJobRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Accusé / échec d'un travail
 * d'impression signalé par le bridge local (REST-OS-3).
 *
 * RÈGLE CARDINALE : un travail n'est `printed` QUE sur accusé EXPLICITE du
 * bridge (qui a effectivement remis les octets à l'imprimante). L'API ne
 * considère jamais une impression réussie parce qu'elle a émis une requête.
 *
 * Idempotence : acquitter un travail déjà terminal (`printed`/`cancelled`) ne
 * produit aucun effet (retour du travail inchangé) — un bridge qui rejoue son
 * accusé après une coupure réseau ne casse rien et ne double pas l'événement.
 *
 * Garde de pureté #6568 : aucune facade Laravel ici — les transitions d'état
 * atomiques vivent dans le port de persistance.
 */
final class AckPrintJobAction
{
    public const EVENT_JOB_PRINTED = 'restaurant.print.job.printed.v1';

    public const EVENT_JOB_FAILED = 'restaurant.print.job.failed.v1';

    public function __construct(
        private readonly RestaurantOutboxPublisher $outbox,
        private readonly RestaurantPrintJobRepositoryInterface $jobs,
    ) {}

    /**
     * Point d'entrée canonique de l'action (convention #6570 : 1 action = 1
     * `execute()`). Le résultat d'impression est explicite côté appelant :
     * `$printed = true` → accusé d'impression RÉELLE, `false` → échec signalé
     * par le bridge. Aucun autre chemin ne fait passer un travail à `printed`.
     */
    public function execute(
        RestaurantPrintJob $job,
        bool $printed,
        ?string $printerRef = null,
        string $error = '',
    ): RestaurantPrintJob {
        return $printed
            ? $this->printed($job, $printerRef)
            : $this->failed($job, $error);
    }

    /**
     * Le bridge confirme l'impression réelle.
     */
    public function printed(RestaurantPrintJob $job, ?string $printerRef = null): RestaurantPrintJob
    {
        if ($job->status->isTerminal()) {
            return $job;
        }

        $affected = $this->jobs->markPrinted(
            (int) $job->id,
            (string) $job->company_id,
            $printerRef ?? $job->printer_ref,
        );

        $job->refresh();

        if ($affected === 1) {
            $this->outbox->publish(
                (string) $job->company_id,
                self::EVENT_JOB_PRINTED,
                [
                    'print_job_id' => $job->id,
                    'order_id' => $job->order_id,
                    'ticket_kind' => $job->ticket_kind->value,
                    'revision' => $job->revision,
                    'attempts' => $job->attempts,
                ],
                'print_job:printed:'.$job->id,
            );
        }

        return $job;
    }

    /**
     * Le bridge signale un échec (papier, déconnexion, driver) : le travail
     * repart dans la file (`failed` est réclamable) et le motif est conservé.
     */
    public function failed(RestaurantPrintJob $job, string $error): RestaurantPrintJob
    {
        if ($job->status->isTerminal()) {
            return $job;
        }

        $affected = $this->jobs->markFailed(
            (int) $job->id,
            (string) $job->company_id,
            $error,
        );

        $job->refresh();

        if ($affected === 1) {
            $this->outbox->publish(
                (string) $job->company_id,
                self::EVENT_JOB_FAILED,
                [
                    'print_job_id' => $job->id,
                    'order_id' => $job->order_id,
                    'ticket_kind' => $job->ticket_kind->value,
                    'revision' => $job->revision,
                    'attempts' => $job->attempts,
                    'error' => $error,
                ],
                'print_job:failed:'.$job->id,
            );
        }

        return $job;
    }
}
