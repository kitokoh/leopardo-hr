<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Services;

/**
 * #8523 (point 2) — attribution d'acquisition d'un essai self-service.
 *
 * Le problème d'origine : `source` n'était écrit que dans un `Log::info()` du
 * provisioning. Une fois les logs purgés (politique de rétention), la
 * provenance d'un essai — campagne, partenaire, referral, import — était
 * définitivement perdue et aucune analyse d'acquisition n'était possible.
 *
 * Cette classe isole la normalisation de l'attribution du reste du
 * provisioning, qui est volumineux et dépend de la base. Elle est donc
 * testable unitairement, sans PostgreSQL ni tenant provisionné.
 *
 * Choix de conception : on ne crée PAS de colonne `companies.signup_source`.
 * `companies.metadata` est déjà un jsonb indexé GIN, additif et non
 * destructif ; une migration de colonne obligerait à un backfill et à un
 * déploiement coordonné pour une donnée purement descriptive.
 */
final class TrialAcquisitionAttribution
{
    /**
     * Attribution de repli quand aucune provenance n'est déclarée : un essai
     * auto-servi reste attribuable au canal self-service plutôt que de rester
     * anonyme.
     */
    public const DEFAULT_SOURCE = 'self_service_trial';

    /**
     * Clés UTM conservées. Liste fermée pour éviter qu'un payload client
     * arbitraire ne vienne gonfler le jsonb.
     *
     * @var list<string>
     */
    private const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /** Longueur maximale d'une valeur conservée. */
    private const MAX_LENGTH = 120;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{source: string, referrer: string|null, landing_path: string|null, utm: array<string, string>}
     */
    public function fromPayload(array $payload): array
    {
        $utm = [];
        foreach (self::UTM_KEYS as $key) {
            $value = $this->normalize($payload[$key] ?? null);
            if ($value !== null) {
                $utm[$key] = $value;
            }
        }

        return [
            'source' => $this->normalize($payload['source'] ?? null) ?? self::DEFAULT_SOURCE,
            'referrer' => $this->normalize($payload['referrer'] ?? $payload['referrer_url'] ?? null),
            'landing_path' => $this->normalize($payload['landing_path'] ?? $payload['landing_page'] ?? null),
            'utm' => $utm,
        ];
    }

    /**
     * Normalise une valeur de provenance : chaîne non vide, bornée en
     * longueur, `null` sinon. Une valeur non scalaire (tableau, objet) est
     * ignorée plutôt que convertie en `"Array"`.
     */
    private function normalize(mixed $value): ?string
    {
        if (! \is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, self::MAX_LENGTH);
    }
}
