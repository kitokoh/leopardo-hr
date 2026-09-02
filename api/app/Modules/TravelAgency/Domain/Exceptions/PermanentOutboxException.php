<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Exceptions;

use RuntimeException;

/**
 * #6066 (TRAVEL-414) — Erreur PERMANENTE de consommation d'un événement
 * d'outbox TravelAgency : le rejeu ne corrigera rien (payload invalide,
 * consommateur absent, invariant définitivement violé). Dead-letter
 * immédiate, sans retry.
 */
final class PermanentOutboxException extends RuntimeException {}
