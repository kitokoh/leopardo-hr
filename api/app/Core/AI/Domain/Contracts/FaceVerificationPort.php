<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Contracts;

use App\Core\AI\Domain\ValueObjects\FaceVerificationRequest;
use App\Core\AI\Domain\ValueObjects\FaceVerificationResult;

/**
 * Contrat de vérification faciale (BIO-001, #6762).
 *
 * Le domaine ne doit connaître ni le fournisseur, ni son format de réponse,
 * ni ses exceptions. L'implémentation (adaptateur) est remplaçable par
 * configuration :
 *
 *   - défaut (fail-closed) : `UnavailableFaceVerificationAdapter` — aucun
 *     fournisseur configuré → `provider_unavailable` ;
 *   - tests : `FakeFaceVerificationAdapter` (scénarios scriptables) ;
 *   - production : adaptateur réel branché par configuration, sans
 *     modification des agrégats métier.
 *
 * Invariant d'appel (responsabilité de l'APPELANT) : aucune vérification
 * faciale sans tenant et appareil authentifiés (kiosque non révoqué, token
 * valide). Les implémentations ne journalisent jamais la capture ni le
 * gabarit.
 */
interface FaceVerificationPort
{
    public function verify(FaceVerificationRequest $request): FaceVerificationResult;
}
