<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Survey;

use App\Core\Solutions\Survey\Contracts\SolutionSurvey;

/**
 * Questionnaire de pre-qualification de la verticale Trade — TRADE-002 (#8477).
 *
 * TRADUIT FIDELEMENT LE PARCOURS VITRINE en questions, dans cet ordre :
 *
 *   1. `scope` — « International » ou « Domestique » (les DEUX choix du bouton
 *      Trade de la vitrine, exigence d'acceptation n° 1) ;
 *   2. si International : `international_role` — Importateur, Exportateur, ou
 *      les DEUX (exigence n° 2 : une meme entreprise peut importer ET
 *      exporter, aucun enfermement dans un role) ;
 *   3. si Domestique : `domestic_role` — Grossiste ou Vente au detail
 *      (exigence n° 3).
 *
 * Les libelles (`label_key` / `reason_key`) sont des CLES i18n localisees par
 * le front : le backend reste la source de verite des questions et des regles.
 *
 * ⚠️ Le moteur de survey est DETERMINISTE (regles pures, aucun appel reseau,
 * aucune IA) : un quiz de positionnement n'a pas a etre devine par un modele.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md
 */
final class TradeSurvey implements SolutionSurvey
{
    public function code(): string
    {
        return 'trade';
    }

    public function name(): string
    {
        return 'International Trade';
    }

    public function questions(): array
    {
        return [
            // ── Q1 : le choix structurant de la vitrine ─────────────────────
            [
                'key' => 'scope',
                'type' => 'select',
                'label_key' => 'solutions.trade.question.scope',
                'options' => [
                    ['value' => 'international', 'label_key' => 'solutions.trade.question.scope.international'],
                    ['value' => 'domestic', 'label_key' => 'solutions.trade.question.scope.domestic'],
                ],
                'default' => 'international',
            ],
            // ── Q2 : sens des operations (International) ────────────────────
            [
                'key' => 'international_role',
                'type' => 'select',
                'label_key' => 'solutions.trade.question.international_role',
                'options' => [
                    ['value' => 'importer', 'label_key' => 'solutions.trade.question.international_role.importer'],
                    ['value' => 'exporter', 'label_key' => 'solutions.trade.question.international_role.exporter'],
                    ['value' => 'both', 'label_key' => 'solutions.trade.question.international_role.both'],
                ],
                'default' => 'importer',
            ],
            // ── Q3 : mode domestique ────────────────────────────────────────
            [
                'key' => 'domestic_role',
                'type' => 'select',
                'label_key' => 'solutions.trade.question.domestic_role',
                'options' => [
                    ['value' => 'wholesaler', 'label_key' => 'solutions.trade.question.domestic_role.wholesaler'],
                    ['value' => 'retailer', 'label_key' => 'solutions.trade.question.domestic_role.retailer'],
                ],
                'default' => 'wholesaler',
            ],
            // ── Q4 : contexte geographique ──────────────────────────────────
            [
                'key' => 'hq_country',
                'type' => 'select',
                'label_key' => 'solutions.trade.question.hq_country',
                'options' => [
                    ['value' => 'CM', 'label_key' => 'solutions.trade.question.hq_country.cm'],
                    ['value' => 'CI', 'label_key' => 'solutions.trade.question.hq_country.ci'],
                    ['value' => 'SN', 'label_key' => 'solutions.trade.question.hq_country.sn'],
                    ['value' => 'OTHER', 'label_key' => 'solutions.trade.question.hq_country.other'],
                ],
                'default' => 'CM',
            ],
            [
                'key' => 'markets',
                'type' => 'select',
                'label_key' => 'solutions.trade.question.markets',
                'options' => [
                    ['value' => 'africa', 'label_key' => 'solutions.trade.question.markets.africa'],
                    ['value' => 'europe', 'label_key' => 'solutions.trade.question.markets.europe'],
                    ['value' => 'asia', 'label_key' => 'solutions.trade.question.markets.asia'],
                    ['value' => 'americas', 'label_key' => 'solutions.trade.question.markets.americas'],
                    ['value' => 'worldwide', 'label_key' => 'solutions.trade.question.markets.worldwide'],
                ],
                'default' => 'africa',
            ],
            // ── Q5 : ce qui doit etre pret a l'ouverture de l'espace ────────
            [
                'key' => 'needs_customs_help',
                'type' => 'bool',
                'label_key' => 'solutions.trade.question.needs_customs_help',
                'default' => true,
            ],
            [
                'key' => 'needs_freight_quotes',
                'type' => 'bool',
                'label_key' => 'solutions.trade.question.needs_freight_quotes',
                'default' => true,
            ],
            [
                'key' => 'needs_quality_control',
                'type' => 'bool',
                'label_key' => 'solutions.trade.question.needs_quality_control',
                'default' => false,
            ],
        ];
    }

    public function packages(): array
    {
        return [
            // L'espace de travail Trade lui-meme : toujours require.
            'trade_workspace' => [
                'key' => 'trade_workspace',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.trade_workspace',
                'download' => null,
                'required' => true,
            ],
            // Espace ACHETEUR : RFQ multi-fournisseurs, comparaison d'offres.
            'importer_workspace' => [
                'key' => 'importer_workspace',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.importer_workspace',
                'download' => null,
            ],
            // Espace VENDEUR : catalogue, offres, pro forma, suivi clients.
            'exporter_workspace' => [
                'key' => 'exporter_workspace',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.exporter_workspace',
                'download' => null,
            ],
            // Logique B2B domestique (grossiste) : catalogue, clients pro, offres.
            'wholesale_workspace' => [
                'key' => 'wholesale_workspace',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.wholesale_workspace',
                'download' => null,
            ],
            // Vente au detail locale.
            'retail_workspace' => [
                'key' => 'retail_workspace',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.retail_workspace',
                'download' => null,
            ],
            'freight_quotes' => [
                'key' => 'freight_quotes',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.freight_quotes',
                'download' => null,
            ],
            'customs_assist' => [
                'key' => 'customs_assist',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.customs_assist',
                'download' => null,
            ],
            'quality_control' => [
                'key' => 'quality_control',
                'type' => 'module',
                'label_key' => 'solutions.trade.package.quality_control',
                'download' => null,
            ],
            // App mobile : le transporteur et le commercial terrain. Contrats
            // API prets, app NON livree — le survey la declare donc SANS
            // `required` et le front doit l'afficher « bientot disponible ».
            'mobile_field' => [
                'key' => 'mobile_field',
                'type' => 'mobile',
                'label_key' => 'solutions.trade.package.mobile_field',
                'app' => 'trade_field',
                'download' => null,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            // L'espace de travail est le socle : toujours present.
            [
                'package' => 'trade_workspace',
                'priority' => 100,
                'when' => static fn (array $answers): bool => true,
                'reason_key' => 'solutions.trade.reason.base',
            ],

            // ── Espaces selon le choix vitrine ──────────────────────────────
            // Importateur : espace acheteur (RFQ, comparaison d'offres).
            [
                'package' => 'importer_workspace',
                'priority' => 90,
                'when' => static fn (array $answers): bool => self::isInternational($answers)
                    && in_array((string) ($answers['international_role'] ?? 'importer'), ['importer', 'both'], true),
                'reason_key' => 'solutions.trade.reason.importer',
            ],
            // Exportateur : espace vendeur (catalogue, offres, pro forma).
            [
                'package' => 'exporter_workspace',
                'priority' => 90,
                'when' => static fn (array $answers): bool => self::isInternational($answers)
                    && in_array((string) ($answers['international_role'] ?? 'importer'), ['exporter', 'both'], true),
                'reason_key' => 'solutions.trade.reason.exporter',
            ],
            // Le mode « les deux » est un cas de PREMIER PLAN : il declenche les
            // deux espaces, sans qu'aucun des deux ne prenne le pas.
            [
                'package' => 'exporter_workspace',
                'priority' => 95,
                'when' => static fn (array $answers): bool => self::isInternational($answers)
                    && (string) ($answers['international_role'] ?? '') === 'both',
                'reason_key' => 'solutions.trade.reason.both',
            ],

            // ── Mode domestique ────────────────────────────────────────────
            [
                'package' => 'wholesale_workspace',
                'priority' => 90,
                'when' => static fn (array $answers): bool => ! self::isInternational($answers)
                    && (string) ($answers['domestic_role'] ?? 'wholesaler') === 'wholesaler',
                'reason_key' => 'solutions.trade.reason.wholesaler',
            ],
            [
                'package' => 'retail_workspace',
                'priority' => 90,
                'when' => static fn (array $answers): bool => ! self::isInternational($answers)
                    && (string) ($answers['domestic_role'] ?? 'wholesaler') === 'retailer',
                'reason_key' => 'solutions.trade.reason.retailer',
            ],

            // ── Besoins operationnels ──────────────────────────────────────
            // Cotation transport : pertinente pour tout flux international OU
            // grossiste B2B (livraisons en volume).
            [
                'package' => 'freight_quotes',
                'priority' => 80,
                'when' => static fn (array $answers): bool => self::isInternational($answers)
                    || (string) ($answers['domestic_role'] ?? '') === 'wholesaler',
                'reason_key' => 'solutions.trade.reason.freight',
            ],
            [
                'package' => 'customs_assist',
                'priority' => 80,
                // Le doute est traité comme un OUI : mieux vaut proposer une aide
                // a la preparation douaniere que la laisser decouvrir trop tard.
                'when' => static fn (array $answers): bool => self::isInternational($answers)
                    && (bool) ($answers['needs_customs_help'] ?? true),
                'reason_key' => 'solutions.trade.reason.customs',
            ],
            [
                'package' => 'quality_control',
                'priority' => 70,
                'when' => static fn (array $answers): bool => (bool) ($answers['needs_quality_control'] ?? false),
                'reason_key' => 'solutions.trade.reason.quality',
            ],
        ];
    }

    /**
     * Le mode « International » est le DEFAUT explicite : une reponse absente ne
     * doit pas basculer silencieusement le dossier en domestique.
     *
     * @param  array<string, mixed>  $answers
     */
    private static function isInternational(array $answers): bool
    {
        return (string) ($answers['scope'] ?? 'international') === 'international';
    }
}
