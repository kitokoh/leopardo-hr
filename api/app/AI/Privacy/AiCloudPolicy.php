<?php

declare(strict_types=1);

namespace App\AI\Privacy;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Politique d'envoi vers les drivers LLM cloud (issue #6853, P0 RGPD).
 *
 * - Un driver est « cloud » s'il transmet les prompts hors de l'infrastructure
 *   (groq / openai / claude) ; fake et adaptateurs locaux ne sont pas soumis.
 * - L'envoi cloud n'est autorisé que si le flag tenant `ai_cloud_allowed`
 *   (registre Core/Feature, config/feature-flags.php) est actif — défaut OFF
 *   (fail-closed : flag inconnu ou entreprise sans activation → refus).
 * - Le refus est explicite (jamais de 500 muet) et tracé en audit AI.
 */
final class AiCloudPolicy
{
    /** @var list<string> */
    private const CLOUD_DRIVERS = ['groq', 'openai', 'claude'];

    public const ALLOW_FLAG = 'ai_cloud_allowed';

    public function isCloudDriver(string $provider): bool
    {
        return in_array($provider, self::CLOUD_DRIVERS, true);
    }

    /**
     * Fail-closed : entreprise inconnue/null ou flag inactif → refus.
     */
    public function cloudAllowed(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        return FeatureFlag::enabled(self::ALLOW_FLAG, $company);
    }

    public function refusalMessage(): string
    {
        return 'L’envoi des commandes vers le fournisseur IA cloud est désactivé pour votre organisation. '
            .'Contactez votre administrateur pour activer le flag « ai_cloud_allowed ».';
    }
}
