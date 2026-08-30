<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use RuntimeException;

/**
 * FUEL-015 (#5809) — Erreur PERMANENTE d'un consommateur d'outbox
 * FuelStation : le retry est inutile (payload invalide, cible introuvable,
 * contrat cassé) → dead-letter.
 */
class PermanentFuelOutboxException extends RuntimeException {}
