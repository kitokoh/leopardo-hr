<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-2 (#8513) — nature d'une anomalie SEO détectée par le scanner.
 *
 * Le `value` est stable et sert de clé dans les règles du scanner, le
 * `recommendation()` porte la correction proposée à l'humain (statut
 * `recommended`). Séparer les deux permet d'ajouter une règle sans toucher
 * au workflow de traitement.
 */
enum SeoIssueType: string
{
    case TitleMissing = 'title_missing';
    case TitleTooLong = 'title_too_long';
    case MetaDescriptionMissing = 'meta_description_missing';
    case MetaDescriptionTooLong = 'meta_description_too_long';
    case H1Missing = 'h1_missing';
    case CanonicalDivergent = 'canonical_divergent';
    case HttpError = 'http_error';
    case MissingStructuredData = 'missing_structured_data';

    /**
     * Gravité par défaut de la règle. Une erreur HTTP est bloquante (la page
     * n'est pas rendue), un titre manquant dégrade le classement sans empêcher
     * l'accès : les deux ne se traitent pas au même rythme.
     */
    public function defaultSeverity(): SeoIssueSeverity
    {
        return match ($this) {
            self::HttpError => SeoIssueSeverity::Critical,
            self::TitleMissing, self::CanonicalDivergent => SeoIssueSeverity::High,
            self::MetaDescriptionMissing, self::H1Missing => SeoIssueSeverity::Medium,
            self::TitleTooLong, self::MetaDescriptionTooLong => SeoIssueSeverity::Low,
            self::MissingStructuredData => SeoIssueSeverity::Info,
        };
    }

    /**
     * Correction proposée. Formulée à l'impératif et actionnable : le statut
     * `recommended` doit dire quoi faire, pas seulement ce qui ne va pas.
     */
    public function recommendation(): string
    {
        return match ($this) {
            self::TitleMissing => 'Ajouter une balise <title> unique décrivant la page.',
            self::TitleTooLong => 'Raccourcir la balise <title> à 60 caractères pour éviter la troncature dans les résultats.',
            self::MetaDescriptionMissing => 'Ajouter une meta description de 120 à 155 caractères.',
            self::MetaDescriptionTooLong => 'Raccourcir la meta description à 155 caractères.',
            self::H1Missing => 'Ajouter un <h1> unique reflétant le sujet principal de la page.',
            self::CanonicalDivergent => 'Aligner la balise canonical sur l’URL publique effective de la page.',
            self::HttpError => 'Corriger l’erreur HTTP : la page n’est pas accessible aux robots.',
            self::MissingStructuredData => 'Ajouter un bloc JSON-LD décrivant l’entité de la page.',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TitleMissing => 'Titre manquant',
            self::TitleTooLong => 'Titre trop long',
            self::MetaDescriptionMissing => 'Meta description manquante',
            self::MetaDescriptionTooLong => 'Meta description trop longue',
            self::H1Missing => 'H1 manquant',
            self::CanonicalDivergent => 'Canonical divergente',
            self::HttpError => 'Erreur HTTP',
            self::MissingStructuredData => 'Données structurées absentes',
        };
    }
}
