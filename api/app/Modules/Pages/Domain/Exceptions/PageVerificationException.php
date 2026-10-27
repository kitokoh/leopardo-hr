<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * #8725 — refus métier du dossier de vérification.
 *
 * Une exception, des fabriques nommées : le code d'erreur rendu au client est
 * explicite et stable (`PAGE_VERIFICATION_*`), ce qui permet à l'interface de
 * dire *pourquoi* une demande est refusée au lieu d'un 422 générique. La leçon
 * #8247 rappelle qu'un `abort()` ne transmet jamais son code — d'où une
 * exception de domaine avec un code en propriété.
 */
final class PageVerificationException extends DomainException
{
    public static function methodDisabled(string $method): self
    {
        return new self(
            __('pages.verification_method_disabled', ['method' => $method]),
            422,
            'PAGE_VERIFICATION_METHOD_DISABLED',
        );
    }

    public static function requestAlreadyOpen(): self
    {
        return new self(
            __('pages.verification_already_open'),
            409,
            'PAGE_VERIFICATION_ALREADY_OPEN',
        );
    }

    public static function requestClosed(): self
    {
        return new self(
            __('pages.verification_closed'),
            409,
            'PAGE_VERIFICATION_CLOSED',
        );
    }

    public static function suspendedPage(): self
    {
        return new self(
            __('pages.verification_suspended'),
            422,
            'PAGE_VERIFICATION_SUSPENDED',
        );
    }

    public static function wrongMethod(string $expected, string $actual): self
    {
        return new self(
            __('pages.verification_wrong_method', ['expected' => $expected, 'actual' => $actual]),
            422,
            'PAGE_VERIFICATION_WRONG_METHOD',
        );
    }

    public static function cooldown(int $seconds): self
    {
        return new self(
            __('pages.verification_cooldown', ['seconds' => max(1, $seconds)]),
            429,
            'PAGE_VERIFICATION_COOLDOWN',
        );
    }

    public static function codeNotFound(): self
    {
        return new self(
            __('pages.verification_code_not_found'),
            422,
            'PAGE_VERIFICATION_CODE_NOT_FOUND',
        );
    }

    /**
     * @param  string  $state  `expired` | `consumed` | `locked`
     */
    public static function codeUnusable(string $state): self
    {
        $message = match ($state) {
            'expired' => __('pages.verification_code_expired'),
            'consumed' => __('pages.verification_code_consumed'),
            default => __('pages.verification_code_locked'),
        };

        return new self($message, 422, 'PAGE_VERIFICATION_CODE_'.strtoupper($state));
    }

    public static function codeMismatch(int $remaining): self
    {
        return new self(
            __('pages.verification_code_mismatch', ['remaining' => max(0, $remaining)]),
            422,
            'PAGE_VERIFICATION_CODE_MISMATCH',
        );
    }

    public static function noEvidence(): self
    {
        return new self(
            __('pages.verification_no_evidence'),
            422,
            'PAGE_VERIFICATION_NO_EVIDENCE',
        );
    }

    /**
     * @param  list<string>  $errors
     */
    public static function invalidEvidence(array $errors): self
    {
        return new self(
            __('pages.verification_evidence_invalid', ['errors' => implode(' ', $errors)]),
            422,
            'PAGE_VERIFICATION_EVIDENCE_INVALID',
        );
    }

    public static function publicPath(): self
    {
        return new self(
            __('pages.verification_evidence_path_public'),
            422,
            'PAGE_VERIFICATION_EVIDENCE_PATH_PUBLIC',
        );
    }

    public static function invalidReason(string $reason): self
    {
        return new self(
            __('pages.verification_reason_invalid', ['reason' => $reason]),
            422,
            'PAGE_VERIFICATION_REASON_INVALID',
        );
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            __('pages.verification_transition_invalid', ['from' => $from, 'to' => $to]),
            409,
            'PAGE_VERIFICATION_TRANSITION_INVALID',
        );
    }
}
