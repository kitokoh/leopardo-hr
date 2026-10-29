<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\DTOs;

use App\Core\Auth\Domain\Models\Employee;

/**
 * #7962 — résultat d'un flux Google SSO, indépendant du transport HTTP.
 *
 * Les trois flux Google (`redirect`, `callback`, `token`) partageaient la même
 * forme de sortie mais la construisaient chacun dans le contrôleur, avec des
 * `JsonResponse` mêlées à la logique métier. Ce DTO sépare les deux : l'Action
 * dit CE QUI s'est passé, le contrôleur décide du code HTTP et de l'enveloppe.
 *
 * `outcome` est un discriminant explicite plutôt qu'une combinaison de champs
 * nullables : un `Employee` non nul avec `mfaChallenge` non nul serait un état
 * impossible, et un état impossible finit toujours par être produit.
 */
final readonly class GoogleSsoResult
{
    private function __construct(
        public string $outcome,
        public ?string $redirectUrl = null,
        public ?Employee $employee = null,
        public ?string $token = null,
        public ?string $tokenType = null,
        public ?string $mfaChallengeToken = null,
        public ?int $mfaChallengeExpiresIn = null,
        public ?string $signupEmail = null,
        public ?string $signupFirstName = null,
        public ?string $signupLastName = null,
        public ?string $signupPlan = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public int $errorStatus = 0,
    ) {}

    /** L'URL de consentement Google a été construite. */
    public static function redirect(string $url): self
    {
        return new self(outcome: 'redirect', redirectUrl: $url);
    }

    /** L'identité Google est vérifiée mais le compte n'existe pas : on pré-remplit l'inscription. */
    public static function signup(string $email, string $firstName, string $lastName, ?string $plan): self
    {
        return new self(
            outcome: 'signup',
            signupEmail: $email,
            signupFirstName: $firstName,
            signupLastName: $lastName,
            signupPlan: $plan,
        );
    }

    /** 2FA activée : le token n'est pas délivré, un challenge est posé. */
    public static function mfaChallenge(string $token, int $expiresIn): self
    {
        return new self(
            outcome: 'mfa_challenge',
            mfaChallengeToken: $token,
            mfaChallengeExpiresIn: $expiresIn,
        );
    }

    /** Authentification complète : le token Sanctum est délivré. */
    public static function authenticated(Employee $employee, string $token, string $tokenType = 'Bearer'): self
    {
        return new self(
            outcome: 'authenticated',
            employee: $employee,
            token: $token,
            tokenType: $tokenType,
        );
    }

    /** Refus explicite, avec le code d'erreur public et le statut HTTP attendu. */
    public static function error(string $code, string $message, int $status): self
    {
        return new self(
            outcome: 'error',
            errorCode: $code,
            errorMessage: $message,
            errorStatus: $status,
        );
    }
}
