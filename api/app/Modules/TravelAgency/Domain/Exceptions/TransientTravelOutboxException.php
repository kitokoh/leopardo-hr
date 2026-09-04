<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Exceptions;

use RuntimeException;

/**
 * #6066 (TRAVEL-414) — Erreur transitoire de consommation d'un événement
 * d'outbox TravelAgency (retry avec backoff exponentiel borné).
 */
final class TransientTravelOutboxException extends RuntimeException {}
