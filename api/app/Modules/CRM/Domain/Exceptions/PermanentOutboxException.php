<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * #5741 — Erreur PERMANENTE d'un consommateur d'outbox : le retry est
 * inutile (payload invalide, cible introuvable, contrat cassé) → dead-letter.
 */
class PermanentOutboxException extends RuntimeException
{
}
