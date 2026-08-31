<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

/**
 * Erreur permanente d'un événement d'outbox FuelStation — dead-letter
 * immédiate (aucun retry).
 */
final class PermanentFuelOutboxException extends \RuntimeException {}
use RuntimeException;

/**
 * FUEL-015 (#5809) — Erreur PERMANENTE d'un consommateur d'outbox
 * FuelStation : le retry est inutile (payload invalide, cible introuvable,
 * contrat cassé) → dead-letter.
 */
class PermanentFuelOutboxException extends RuntimeException {}
 * Erreur permanente de consommation outbox FuelStation (FUEL-015, #5809)
 * — dead-letter immédiate (statut failed), rejouable manuellement.
 */
final class PermanentFuelOutboxException extends \RuntimeException {}
