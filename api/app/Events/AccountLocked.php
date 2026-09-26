<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Auth\Domain\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * #8163 — un compte tenant vient d'être VERROUILLÉ (anti-brute-force,
 * 5 échecs → `locked_until`). Dispatché UNE fois par création de verrou,
 * jamais sur les tentatives suivantes sous verrou actif.
 *
 * Consommateur unique : `NotifyAccountLocked` (notification au titulaire
 * via le store canonique `notifications`, ADR-0013 — aucun nouvel
 * émetteur). L'événement ne porte que des identifiants et l'échéance :
 * aucune PII au-delà du destinataire lui-même.
 */
class AccountLocked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly CarbonInterface $lockedUntil,
        public readonly int $failedAttempts,
    ) {}
}
