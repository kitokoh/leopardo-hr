<?php

declare(strict_types=1);

namespace App\Core\Mail;

use App\Support\I18nCatalog;
use InvalidArgumentException;

/**
 * #7347 — résout le contenu d'un e-mail : surcharge admin (base) sinon défaut
 * du catalogue de traduction, avec substitution des variables autorisées.
 *
 * Règle d'or : **un template non modifié rend exactement ce qu'il rendait
 * avant**. La surcharge est toujours facultative, champ par champ ; un champ
 * vidé dans l'admin revient au défaut (on ne peut pas « effacer » un texte
 * obligatoire par accident).
 */
final class EmailTemplateResolver
{
    public function __construct(
        private readonly EmailTemplateRepository $repository,
    ) {}

    /**
     * @param  array<string, string>  $variables  ex. [':company' => 'Acme SARL']
     */
    public function resolve(string $key, ?string $locale = null, array $variables = []): ResolvedEmailTemplate
    {
        $definition = EmailTemplateRegistry::definition($key);

        if ($definition === null) {
            throw new InvalidArgumentException('Template e-mail inconnu : '.$key);
        }

        $locale = I18nCatalog::normalizeLocale($locale ?? app()->getLocale());
        // `(array)` : la ligne vient de `DB::table()` (stdClass) ; un accès
        // typé en tableau évite tout accès de propriété dynamique (PHPStan 8).
        $override = (array) $this->repository->find($key, $locale);
        $variables = $this->allowedVariables($definition['variables'], $variables);

        $overridden = [
            'subject' => false,
            'heading' => false,
            'body' => false,
            'cta_label' => false,
        ];

        $subject = $this->field($override['subject'] ?? null, $overridden, 'subject');
        $heading = $this->field($override['heading'] ?? null, $overridden, 'heading');
        $ctaLabel = $this->field($override['cta_label'] ?? null, $overridden, 'cta_label');
        $body = $this->field($override['body'] ?? null, $overridden, 'body');

        // `__()` est declaree `string|array|null` par le framework : le cast
        // explicite est la convention du depot (cf. app/AI/...) et garantit au
        // niveau 8 que ces champs restent des chaines.
        $subject ??= (string) __($definition['subject'], [], $locale);
        $heading ??= (string) __($definition['heading'], [], $locale);
        $ctaLabel ??= $definition['cta_label'] !== null
            ? (string) __($definition['cta_label'], [], $locale)
            : null;
        $body ??= $this->defaultBody($definition['body'], $locale);

        return new ResolvedEmailTemplate(
            key: $key,
            locale: $locale,
            subject: strtr($subject, $variables),
            heading: strtr($heading, $variables),
            body: strtr($body, $variables),
            ctaLabel: $ctaLabel !== null ? strtr($ctaLabel, $variables) : null,
            overridden: $overridden,
        );
    }

    /**
     * Valeur par défaut d'un corps : les clés du registre, séparées par une ligne
     * vide (elles forment des paragraphes distincts dans le layout).
     *
     * @param  array<int, string>  $keys
     */
    private function defaultBody(array $keys, string $locale): string
    {
        $lines = [];

        foreach ($keys as $line) {
            $lines[] = (string) __($line, [], $locale);
        }

        return implode("\n\n", $lines);
    }

    /**
     * Ne garde que les variables DÉCLARÉES par le registre : une valeur inconnue
     * ne peut pas être injectée dans le texte.
     *
     * @param  array<int, string>  $allowed
     * @param  array<string, string>  $provided
     * @return array<string, string>
     */
    private function allowedVariables(array $allowed, array $provided): array
    {
        $kept = [];

        foreach ($allowed as $name) {
            $value = $provided[$name] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                $kept[$name] = (string) $value;
            }
        }

        return $kept;
    }

    /**
     * @param  array<string, bool>  $overridden
     */
    private function field(?string $value, array &$overridden, string $field): ?string
    {
        $trimmed = $value !== null ? trim($value) : '';

        if ($trimmed === '') {
            return null;
        }

        $overridden[$field] = true;

        return $trimmed;
    }
}
