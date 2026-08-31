<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use App\Exceptions\DomainException;

/**
 * BC-23-D05 (issue #6237) — l'utilisateur ne remplit pas la matrice de
 * permissions de l'outil AI appelé (rôle minimal ou permission requise).
 *
 * Traitée par l'IntentEngine : refus explicite sans effet de bord (ToolResult
 * en erreur pour les tools, tableau d'erreur pour les confirmations). Le code
 * stable `AI_TOOL_PERMISSION_DENIED` est exposé au client, le détail reste en
 * logs.
 */
class ToolPermissionDeniedException extends DomainException
{
    public const ERROR_CODE = 'AI_TOOL_PERMISSION_DENIED';

    public function __construct(string $message)
    {
        parent::__construct($message, 403, self::ERROR_CODE);
    }
}
