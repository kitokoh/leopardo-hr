<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Infrastructure\Services\TokenRevocationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Issue #9808 — bannir (ou débannir) un compte sans toucher à ses données.
 *
 * ## Pourquoi une commande, et pas seulement une route
 *
 * Un bannissement est un geste d'EXPLOITATION : il se fait quand quelqu'un
 * constate un abus, souvent depuis un terminal, parfois alors que l'API est
 * dégradée. Il ne doit dépendre ni d'un front, ni d'une session
 * d'administrateur valide. La route plateforme existe aussi (voir
 * `PlatformAccountBanController`), mais la commande est le chemin de dernier
 * recours.
 *
 * ## Ce qu'elle ne fait pas
 *
 * Elle n'écrit pas `status` en base : c'est un **bannissement de session**
 * (aucun jeton n'est accepté), distinct d'une suspension métier qui peut
 * impliquer des règles RH ou comptables. Les deux sont complémentaires — un
 * employé suspendu (`status = suspended`) est refusé par
 * `AccountStatusGuard` même sans bannissement.
 */
final class BanAccountCommand extends Command
{
    protected $signature = 'auth:ban-account
                            {type : employee|user|travel_customer|super_admin}
                            {id : Identifiant du compte dans son provider}
                            {--hours= : Durée du bannissement en heures (défaut : permanent)}
                            {--reason= : Motif, journalisé et audité}
                            {--lift : Lève le bannissement au lieu de le poser}';

    protected $description = 'Bannit (ou débannit) un compte : tous ses jetons sont refusés dès la requête suivante.';

    public function handle(TokenRevocationService $revocations): int
    {
        $type = (string) $this->argument('type');
        $id = (int) $this->argument('id');

        $allowed = [
            TokenRevocationService::TYPE_EMPLOYEE,
            TokenRevocationService::TYPE_USER,
            TokenRevocationService::TYPE_TRAVEL_CUSTOMER,
            TokenRevocationService::TYPE_SUPER_ADMIN,
        ];

        if (! in_array($type, $allowed, true)) {
            $this->error(sprintf('Type inconnu : %s. Attendu : %s', $type, implode('|', $allowed)));

            return self::FAILURE;
        }

        if ($id <= 0) {
            $this->error('Identifiant invalide.');

            return self::FAILURE;
        }

        if ($this->option('lift')) {
            $revocations->unban($type, $id);
            $this->info(sprintf('Bannissement levé pour %s#%d.', $type, $id));

            return self::SUCCESS;
        }

        $hours = $this->option('hours');
        $until = null;

        if ($hours !== null) {
            $hoursInt = (int) $hours;

            if ($hoursInt <= 0) {
                $this->error('--hours doit être un entier positif.');

                return self::FAILURE;
            }

            $until = CarbonImmutable::now()->addHours($hoursInt);
        }

        $reason = $this->option('reason');
        $revocations->ban(
            $type,
            $id,
            $until,
            is_string($reason) && $reason !== '' ? $reason : null,
        );

        $this->info(sprintf(
            'Compte %s#%d banni %s.',
            $type,
            $id,
            $until === null ? 'définitivement' : 'jusqu\'au '.$until->toIso8601String(),
        ));
        $this->line('Ses jetons sont refusés dès la requête suivante (aucune ligne supprimée).');

        return self::SUCCESS;
    }
}
