<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Shared\Services\PublicCommerce\TrackingSecretService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BOS-050 (#8208, tranche 7) — Garde du suivi public des commandes
 * restaurant (boutique par jeton, kiosque, page par slug).
 *
 * Alignement sur l'invariant des autres verticales publiques
 * (TravelTicket, HospitalityReservation) : le suivi exige la référence non
 * énumérable `RST-…` **+ un secret** ({@see TrackingSecretService} — hash
 * SHA-256 stocké, clair présenté une seule fois à la création).
 *
 *  - Commande avec `tracking_secret_hash` (créée après la tranche 7) :
 *    secret requis — référence inconnue ET secret absent/invalide
 *    produisent le MÊME 404 (anti-énumération, jamais de 401/403) ;
 *  - commande historique (hash NULL) : ancien flux « référence seule »
 *    encore accepté pendant la fenêtre de dépréciation de 90 jours — les
 *    en-têtes `Deprecation` (RFC 8594) et `Sunset` sont posés sur la
 *    réponse ; après le {@see LEGACY_SUNSET_HTTP}, le flux legacy sera
 *    fermé (retrait annoncé dans PUBLIC_COMMERCE_CONVENTIONS.md).
 */
final class RestaurantPublicTrackingGuard
{
    /**
     * Date de fermeture de l'ancien flux (référence seule) : 90 jours après
     * la tranche 7 (mergée le 2026-09-29) → 2026-12-28, format HTTP-date.
     */
    public const LEGACY_SUNSET_HTTP = 'Mon, 28 Dec 2026 00:00:00 GMT';

    public function __construct(private readonly TrackingSecretService $secrets) {}

    /**
     * Vérifie le droit de suivre `$order` depuis la requête publique
     * `$request`. 404 uniforme si le secret est requis et absent/invalide.
     *
     * @return bool `true` si la commande relève du flux legacy déprécié
     *              (hash absent — le contrôleur pose alors les en-têtes de
     *              dépréciation via {@see withDeprecationHeaders()})
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (404)
     */
    public function assertTrackable(Request $request, RestaurantOrder $order): bool
    {
        $storedHash = $order->getAttribute('tracking_secret_hash');

        if (! is_string($storedHash) || $storedHash === '') {
            return true; // Commande antérieure à la tranche 7 : flux legacy.
        }

        $secret = $this->providedSecret($request);

        if ($secret === null || ! $this->secrets->matches($secret, $storedHash)) {
            abort(404, 'Commande introuvable.');
        }

        return false;
    }

    /**
     * Secret présenté par le client : query `?secret=` ou en-tête
     * `X-Tracking-Secret` (64 hex attendu ; toute autre valeur échouera la
     * comparaison timing-safe — fail-closed).
     */
    public function providedSecret(Request $request): ?string
    {
        $secret = $request->query('secret');

        if (is_string($secret) && trim($secret) !== '') {
            return trim($secret);
        }

        $header = trim($request->header('X-Tracking-Secret', ''));

        return $header === '' ? null : $header;
    }

    /**
     * Pose les en-têtes de dépréciation de l'ancien flux (référence seule)
     * sur une réponse de suivi servie à une commande legacy.
     *
     * @param  bool  $legacy  valeur retournée par {@see assertTrackable()}
     */
    public function withDeprecationHeaders(JsonResponse $response, bool $legacy): JsonResponse
    {
        if ($legacy) {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', self::LEGACY_SUNSET_HTTP);
        }

        return $response;
    }
}
