<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * #9344 (audit sécurité 2026-11-10, F2) — source UNIQUE de la durée de vie
 * des jetons Sanctum.
 *
 * ## Pourquoi ce service existe
 *
 * Avant ce correctif, chaque point d'émission recopiait la même paire de
 * lignes (`config('sanctum.expiration')` → `now()->addMinutes(...)`), et tous
 * héritaient donc du même défaut : **30 jours** (`43200` minutes). Sur une
 * application qui manipule la paie, les bulletins, les IBAN et les données
 * biométriques, la fenêtre d'exposition d'un jeton volé (poste partagé,
 * sauvegarde de navigateur, proxy, log) était d'un mois, sans détection.
 *
 * La durée vit désormais ici et nulle part ailleurs :
 *  - **12 h** par défaut pour les surfaces interactives (web, mobile, SSO) ;
 *  - **30 jours** uniquement pour les appareils explicitement enrôlés
 *    (kiosque de pointage, nœud Edge), qui ne peuvent pas se reconnecter
 *    aussi souvent sans casser l'exploitation — et qui tournent sur un
 *    matériel contrôlé, pas sur le poste d'un salarié.
 *
 * La rotation (TokenAutoRefreshMiddleware, RefreshTokenAction) reste
 * inchangée : elle rafraîchit dans la fenêtre `sanctum.auto_refresh_window`
 * et supprime l'ancien jeton, donc un utilisateur actif ne voit jamais
 * l'écran de connexion.
 */
final class TokenExpiryService
{
    /** 12 h — durée par défaut d'un jeton interactif. */
    public const DEFAULT_INTERACTIVE_MINUTES = 720;

    /** 30 jours — durée d'un jeton d'appareil enrôlé. */
    public const DEFAULT_DEVICE_MINUTES = 43200;

    /**
     * Durée de vie d'un jeton sur une surface interactive (minutes).
     */
    public function interactiveMinutes(): int
    {
        return (int) config('sanctum.expiration', self::DEFAULT_INTERACTIVE_MINUTES);
    }

    /**
     * Durée de vie d'un jeton d'appareil enrôlé (minutes).
     */
    public function deviceMinutes(): int
    {
        return (int) config('sanctum.device_expiration', self::DEFAULT_DEVICE_MINUTES);
    }

    /**
     * Échéance d'un jeton interactif, ou `null` si les jetons n'expirent pas
     * (configuration `sanctum.expiration = 0`, réservée au développement).
     */
    public function forInteractive(): ?CarbonImmutable
    {
        return $this->fromMinutes($this->interactiveMinutes());
    }

    /**
     * Échéance d'un jeton d'appareil enrôlé.
     */
    public function forDevice(): ?CarbonImmutable
    {
        return $this->fromMinutes($this->deviceMinutes());
    }

    /**
     * Échéance explicite pour une durée fournie (ex. impersonation plateforme,
     * qui porte son propre TTL métier).
     */
    public function fromMinutes(int $minutes): ?CarbonImmutable
    {
        if ($minutes <= 0) {
            return null;
        }

        return CarbonImmutable::now()->addMinutes($minutes);
    }

    /**
     * Décide de l'échéance à appliquer à un nouveau jeton.
     *
     * Un client ne peut PAS s'auto-attribuer la durée longue en envoyant un
     * `device_name` arbitraire : `$isEnrolledDevice` est fourni par l'appelant
     * serveur (route d'enrôlement d'appareil qui vérifie une credential
     * dédiée). Le nom d'appareil reste purement cosmétique.
     */
    public function forNewToken(?string $deviceName, bool $isEnrolledDevice = false): ?CarbonImmutable
    {
        if ($isEnrolledDevice) {
            Log::channel('structured')->info('auth.token_issued_device_long_lived', [
                'device_name' => $deviceName,
                'expires_in_minutes' => $this->deviceMinutes(),
            ]);

            return $this->forDevice();
        }

        return $this->forInteractive();
    }
}
