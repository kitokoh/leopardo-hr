<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueType;
use App\Modules\SearchIntelligence\Domain\ValueObjects\PageAnalysis;

/**
 * GSI-2 (#8513) — analyse technique du HTML d'une page.
 *
 * Classe SANS dépendance à Laravel et sans accès réseau : elle prend du HTML
 * et un statut, elle rend une observation. C'est ce qui rend les règles
 * testables unitairement et réutilisables (le même analyseur servira au scan
 * planifié de la phase 2).
 *
 * Les seuils sont ceux du métier : un `<title>` au-delà de ~60 caractères est
 * tronqué dans les SERP, une meta description au-delà de ~155 également.
 */
final class SeoPageAnalyzer
{
    public const TITLE_MAX_LENGTH = 60;

    public const META_DESCRIPTION_MAX_LENGTH = 155;

    public function analyze(string $url, int $httpStatus, string $html): PageAnalysis
    {
        $title = $this->extractTitle($html);
        $metaDescription = $this->extractMetaDescription($html);
        $h1 = $this->extractFirstH1($html);
        $canonical = $this->extractCanonical($html);
        $hasStructuredData = $this->hasJsonLd($html);

        $issues = $this->rules(
            $httpStatus,
            $url,
            $title,
            $metaDescription,
            $h1,
            $canonical,
            $hasStructuredData,
        );

        return new PageAnalysis(
            url: $url,
            httpStatus: $httpStatus,
            title: $title,
            metaDescription: $metaDescription,
            h1: $h1,
            canonical: $canonical,
            hasStructuredData: $hasStructuredData,
            issues: $issues,
        );
    }

    /**
     * Les règles, dans l'ordre de gravité décroissante.
     *
     * @return list<SeoIssueType>
     */
    private function rules(
        int $httpStatus,
        string $url,
        ?string $title,
        ?string $metaDescription,
        ?string $h1,
        ?string $canonical,
        bool $hasStructuredData,
    ): array {
        $issues = [];

        // Une erreur HTTP rend toutes les autres règles sans objet : inutile de
        // reprocher un titre manquant sur une page qui renvoie 404.
        if ($httpStatus >= 400) {
            return [SeoIssueType::HttpError];
        }

        if ($title === null || $title === '') {
            $issues[] = SeoIssueType::TitleMissing;
        } elseif (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            $issues[] = SeoIssueType::TitleTooLong;
        }

        if ($metaDescription === null || $metaDescription === '') {
            $issues[] = SeoIssueType::MetaDescriptionMissing;
        } elseif (mb_strlen($metaDescription) > self::META_DESCRIPTION_MAX_LENGTH) {
            $issues[] = SeoIssueType::MetaDescriptionTooLong;
        }

        if ($h1 === null || $h1 === '') {
            $issues[] = SeoIssueType::H1Missing;
        }

        // Canonical divergente : une canonical explicite qui pointe ailleurs
        // que l'URL effectivement servie dilue le signal. Absente n'est PAS une
        // anomalie — beaucoup de sites sains s'en passent.
        if ($canonical !== null && $canonical !== '' && ! $this->sameUrl($canonical, $url)) {
            $issues[] = SeoIssueType::CanonicalDivergent;
        }

        if (! $hasStructuredData) {
            $issues[] = SeoIssueType::MissingStructuredData;
        }

        return $issues;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        return $this->clean($matches[1]);
    }

    private function extractMetaDescription(string $html): ?string
    {
        if (preg_match('/<meta[^>]+name\s*=\s*["\']description["\'][^>]*>/i', $html, $tag) !== 1) {
            return null;
        }

        if (preg_match('/content\s*=\s*["\'](.*?)["\']/is', $tag[0], $matches) !== 1) {
            return null;
        }

        return $this->clean($matches[1]);
    }

    private function extractFirstH1(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches) !== 1) {
            return null;
        }

        // Le H1 contient souvent des balises inline ; on ne garde que le texte.
        return $this->clean(strip_tags($matches[1]));
    }

    private function extractCanonical(string $html): ?string
    {
        if (preg_match('/<link[^>]+rel\s*=\s*["\']canonical["\'][^>]*>/i', $html, $tag) !== 1) {
            return null;
        }

        if (preg_match('/href\s*=\s*["\'](.*?)["\']/is', $tag[0], $matches) !== 1) {
            return null;
        }

        return $this->clean($matches[1]);
    }

    private function hasJsonLd(string $html): bool
    {
        return preg_match('/<script[^>]+type\s*=\s*["\']application\/ld\+json["\']/i', $html) === 1;
    }

    /**
     * Compare deux URL à la normalisation près (schéma, casse d'hôte, slash
     * final, fragment). Une canonical qui ne diffère que par `http` vs `https`
     * reste une divergence réelle — on ne normalise donc PAS le schéma.
     */
    private function sameUrl(string $canonical, string $url): bool
    {
        $normalize = static function (string $value): string {
            $value = trim($value);
            $hash = mb_strpos($value, '#');
            if ($hash !== false) {
                $value = mb_substr($value, 0, $hash);
            }

            return rtrim($value, '/');
        };

        $left = $normalize($canonical);
        $right = $normalize($url);

        if ($left === $right) {
            return true;
        }

        // Une canonical relative est résolue contre l'URL absolue.
        if (! str_contains($left, '://') && str_contains($right, '://')) {
            $parts = parse_url($right);
            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $origin = $parts['scheme'].'://'.$parts['host'];

                return $normalize($origin.'/'.ltrim($left, '/')) === $right;
            }
        }

        return false;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
