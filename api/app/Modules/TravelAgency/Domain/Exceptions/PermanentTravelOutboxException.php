<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Exceptions;

use RuntimeException;

/**
 * #6066 (TRAVEL-414) — Erreur permanente de consommation d'un événement
 * d'outbox TravelAgency (dead-letter immédiate, aucun retry).
 */
final class PermanentTravelOutboxException extends RuntimeException {}
