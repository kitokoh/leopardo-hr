<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Support;

/**
 * Recherche sémantique légère du catalogue public (#9240).
 *
 * ⚠️ Ce n'est PAS un modèle d'embedding : c'est un **scoring lexical pondéré**
 * (TF pondéré par champ + recouvrement de tokens + normalisation), sans
 * dépendance externe, sans appel réseau, sans coût par requête. Il répond au
 * besoin réel — « trouver la ressource qui parle de ça » quand l'utilisateur ne
 * connaît pas le titre exact — sans introduire un service d'embeddings, une
 * base vectorielle et un pipeline d'indexation.
 *
 * Pourquoi pas d'embeddings (décision documentée dans l'issue #9240) :
 *  - le catalogue public est petit (dizaines à centaines de ressources) : un
 *    scoring lexical en mémoire est instantané et suffisant ;
 *  - un service d'embeddings ajouterait une dépendance payante, un secret, un
 *    coût par requête et un risque de fuite de requêtes utilisateur ;
 *  - le scoring est **pur et testable** : on peut prouver qu'il classe
 *    correctement, ce qu'un modèle opaque ne permet pas.
 *
 * Le jour où le catalogue dépasse ce que le scoring lexical peut ordonner
 * utilement, la façade `LibrarySemanticSearch` reste la même : seule son
 * implémentation change.
 */
final class LibrarySemanticScorer
{
    /**
     * Poids par champ. Le titre porte l'intention, l'extrait la précise, la
     * description la développe ; les métadonnées bibliographiques pèsent peu
     * mais permettent de retrouver un auteur ou un éditeur.
     *
     * @var array<string, float>
     */
    private const FIELD_WEIGHTS = [
        'title' => 5.0,
        'excerpt' => 2.5,
        'description' => 1.5,
        'rights_holder' => 1.0,
        'publisher' => 1.0,
        'category' => 1.0,
    ];

    /**
     * Mots vides ignorés (français + anglais + turc). Ils n'apportent aucune
     * information discriminante et fausseraient le recouvrement.
     *
     * @var array<int, string>
     */
    private const STOP_WORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'a', 'au',
        'aux', 'en', 'dans', 'pour', 'par', 'sur', 'avec', 'sans', 'ce', 'cet',
        'cette', 'ces', 'son', 'sa', 'ses', 'il', 'elle', 'ils', 'elles', 'que',
        'qui', 'quoi', 'dont', 'est', 'sont', 'etre', 'avoir', 'plus', 'tout',
        'the', 'a', 'an', 'of', 'and', 'or', 'to', 'in', 'on', 'for', 'with',
        'without', 'this', 'that', 'these', 'those', 'is', 'are', 'be', 'have',
        've', 'bir', 've', 'ile', 'icin', 'bu', 'su', 'o', 'da', 'de',
    ];

    /**
     * Découpe un texte en tokens normalisés (minuscules, sans accents, sans
     * ponctuation, sans mots vides, longueur >= 2).
     *
     * @return array<int, string>
     */
    public static function tokenize(string $text): array
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }
            if (in_array($part, self::STOP_WORDS, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Normalise un texte : minuscules, accents retirés, espaces compactés.
     */
    public static function normalize(string $text): string
    {
        $lower = mb_strtolower(trim($text));

        // Retrait des diacritiques (é → e, ı → i, ş → s…). `transliterator`
        // n'est pas garanti disponible : on utilise une table explicite, qui
        // couvre les langues du produit (fr/en/tr/ar translittéré).
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ı' => 'i',
            'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ş' => 's', 'ğ' => 'g', 'ž' => 'z',
            'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
        ];

        $lower = strtr($lower, $map);

        return trim((string) preg_replace('/\s+/', ' ', $lower));
    }

    /**
     * Score de pertinence d'un document pour une requête.
     *
     * @param  array<string, string|null>  $fields  Champs indexables du document.
     * @return float Score >= 0 ; 0 signifie « aucune correspondance ».
     */
    public static function score(array $fields, string $query): float
    {
        $queryTokens = self::tokenize($query);

        if ($queryTokens === []) {
            return 0.0;
        }

        $total = 0.0;

        foreach (self::FIELD_WEIGHTS as $field => $weight) {
            $value = $fields[$field] ?? null;
            if (! is_string($value) || $value === '') {
                continue;
            }

            $fieldTokens = self::tokenize($value);
            if ($fieldTokens === []) {
                continue;
            }

            // Recouvrement : proportion des tokens de la requête présents dans
            // le champ. Un document qui contient TOUS les mots de la requête
            // dans son titre score bien plus haut qu'un document qui n'en
            // contient qu'un.
            $matches = count(array_intersect($queryTokens, $fieldTokens));
            if ($matches === 0) {
                continue;
            }

            $coverage = $matches / count($queryTokens);

            // Bonus de correspondance exacte de la requête entière dans le
            // champ (ex. « gestion de stock » présent tel quel dans le titre).
            $exact = str_contains(self::normalize($value), self::normalize($query)) ? 1.5 : 0.0;

            $total += $weight * ($coverage + $exact);
        }

        return round($total, 4);
    }

    /**
     * Classe des documents par pertinence décroissante.
     *
     * Les documents de score 0 sont EXCLUS : une recherche qui ne correspond à
     * rien doit retourner une liste vide, pas le catalogue entier.
     *
     * @template T
     *
     * @param  array<int, T>  $documents
     * @param  callable(T): array<string, string|null>  $fieldsOf
     * @return array<int, array{item: T, score: float}>
     */
    public static function rank(array $documents, string $query, callable $fieldsOf): array
    {
        $scored = [];

        foreach ($documents as $document) {
            $score = self::score($fieldsOf($document), $query);
            if ($score > 0.0) {
                $scored[] = ['item' => $document, 'score' => $score];
            }
        }

        // Tri stable : à score égal, l'ordre d'entrée est conservé (usort n'est
        // pas stable, on départage donc explicitement par l'index d'origine).
        $indexed = [];
        foreach ($scored as $position => $entry) {
            $indexed[] = $entry + ['position' => $position];
        }

        usort($indexed, static function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return $a['position'] <=> $b['position'];
            }

            return $b['score'] <=> $a['score'];
        });

        return array_map(
            static fn (array $entry): array => ['item' => $entry['item'], 'score' => $entry['score']],
            $indexed,
        );
    }
}
