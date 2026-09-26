<?php

declare(strict_types=1);

namespace App\AI\Privacy;

/**
 * Minimisation RGPD avant envoi vers un driver LLM cloud (issue #6853, P0).
 *
 * Redacte les PII identifiables présentes dans TOUS les payloads sortants
 * (conversation + message courant + résultats d'outils) : emails, téléphones,
 * identifiants nationaux labellisés. La sanitization est RÉCURSIVE (BOS-001
 * #8141) : la branche Claude envoie ses `tool_result` sous forme de TABLEAUX
 * de blocs (`[{type: tool_result, content: …}]`) ; ne traiter que les strings
 * de premier niveau laissait fuiter les PII des résultats d'outils vers
 * Anthropic. Les CLÉS de structure (`role`, `type`, `tool_use_id`, …) ne sont
 * jamais réécrites, seules les valeurs textuelles le sont. Le prompt système
 * et les schémas d'outils ne sont jamais modifiés.
 *
 * Limites assumées (v1) : la minimisation STRUCTURELLE (agrégats plutôt que
 * lignes brutes, jamais de salaire/donnée de santé en clair) est garantie en
 * amont par les `outputSchema` des outils et les ressources API (whitelist
 * EmployeeResource, #6546) — hors périmètre de ce sanitizer textuel.
 */
final class PrivacySanitizer
{
    private const REDACT_EMAIL = '[email]';

    private const REDACT_PHONE = '[téléphone]';

    private const REDACT_ID = '[identifiant national]';

    /** @var list<array{pattern: string, replace: string}> */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            // Emails.
            ['pattern' => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', 'replace' => self::REDACT_EMAIL],
            // Identifiants nationaux labellisés (fr/en) — on masque le libellé +
            // valeur proche. DOIT précéder la règle téléphone : une valeur
            // labellisée (ex. « numéro de sécurité sociale 188127512345678 »)
            // ne doit pas être partiellement consommée comme numéro de
            // téléphone avant que son libellé ne soit masqué.
            // Modificateur /u OBLIGATOIRE : sans lui, PCRE traite le pattern en
            // octets et « [ée] » ne peut jamais matcher un « é » UTF-8 (2 octets)
            // → les libellés accentués français ne sont jamais masqués
            // (régression CI 2026-09-06, PrivacySanitizerTest rouge sur main,
            // issue #6928).
            ['pattern' => '/\b(?:national\s?id|nin|num[ée]ro\s+de\s+s[ée]curit[ée]\s+(?:sociale|nationale)|num[ée]ro\s+national)\b[^\n]{0,40}/iu', 'replace' => self::REDACT_ID],
            // Téléphones (E.164 ou national long) — 9 à 15 chiffres, + optionnel.
            ['pattern' => '/\+?[0-9][0-9 .-]{8,14}[0-9]/u', 'replace' => self::REDACT_PHONE],
        ];
    }

    /**
     * Nettoie un texte libre (conversation, message courant).
     */
    public function sanitize(string $text): string
    {
        $out = $text;
        foreach ($this->rules as $rule) {
            $out = preg_replace($rule['pattern'], $rule['replace'], $out) ?? $out;
        }

        return $out;
    }

    /**
     * Nettoie une liste de messages LLM ({role, content}) en copie — tout le
     * contenu textuel est passé au sanitizer, y compris les chaînes imbriquées
     * dans les tableaux (`tool_use.input`, `tool_result.content`, blocs
     * multi-part), sur les 3 branches provider.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array<int, array{role: string, content: mixed}>
     */
    public function sanitizeMessages(array $messages): array
    {
        $cleaned = [];
        foreach ($messages as $message) {
            $copy = $message;
            $copy['content'] = $this->sanitizeStructure($copy['content']);
            $cleaned[] = $copy;
        }

        return $cleaned;
    }

    /**
     * BOS-001 (#8141) — sanitization RÉCURSIVE d'une valeur de message :
     * - string  → règles PII ;
     * - tableau → chaque VALEUR est nettoyée récursivement, les CLÉS de
     *   structure sont préservées (réécrire une clé casserait le protocole
     *   provider : `role`, `type`, `tool_use_id`, `input`, …).
     */
    private function sanitizeStructure(mixed $content): mixed
    {
        if (is_string($content)) {
            return $this->sanitize($content);
        }

        if (! is_array($content)) {
            return $content;
        }

        $cleaned = [];
        foreach ($content as $key => $value) {
            $cleaned[$key] = $this->sanitizeStructure($value);
        }

        return $cleaned;
    }
}
