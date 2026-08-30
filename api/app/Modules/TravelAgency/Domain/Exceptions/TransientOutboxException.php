<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Exceptions;

use RuntimeException;

/**
 * #6066 (TRAVEL-414) — Erreur TRANSITOIRE de consommation d'un événement
 * d'outbox TravelAgency : le rejeu (retry avec backoff exponentiel) a une
 * chance de réussir (dépendance temporairement indisponible, contention…).
 * Borné par TravelOutboxEvent::MAX_ATTEMPTS → dead-letter.
 */
final class TransientOutboxException extends RuntimeException {}
