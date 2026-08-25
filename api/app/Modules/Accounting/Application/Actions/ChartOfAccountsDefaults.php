<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

/**
 * Plan comptable par défaut — issue #5422.
 *
 * Socle « famille PCG/SCF » : comptes de base des classes 1→8, alignés sur
 * les comptes effectivement utilisés par les moteurs d'écritures du module
 * (journal #5234 : 411/70/709/4457/512/53 ; paie DZ #5239 :
 * 641/645/421/431/4421/425 ; notes de frais #5235 : 6251/6256/6064/626/658)
 * et sur la nomenclature PCG française (comptes 1-8 abrégée, réputée
 * utilisable telle quelle en zone OHADA/SYSCOHADA francophone).
 *
 * L'entreprise peut ensuite créer ses comptes analytiques et désactiver les
 * comptes inutilisés — le plan n'est jamais figé (paramétrable).
 *
 * @var list<array{code: string, label: string, type: string, class: int}>
 */
final class ChartOfAccountsDefaults
{
    public const ACCOUNTS = [
        // ── Classe 1 — Comptes de capitaux ────────────────────────────────
        ['code' => '101', 'label' => 'Capital social', 'type' => 'equity', 'class' => 1],
        ['code' => '106', 'label' => 'Réserves', 'type' => 'equity', 'class' => 1],
        ['code' => '11', 'label' => 'Report à nouveau', 'type' => 'equity', 'class' => 1],
        ['code' => '12', 'label' => 'Résultat de l\'exercice', 'type' => 'equity', 'class' => 1],
        ['code' => '13', 'label' => 'Subventions d\'investissement', 'type' => 'equity', 'class' => 1],
        ['code' => '16', 'label' => 'Emprunts et dettes assimilées', 'type' => 'liability', 'class' => 1],
        ['code' => '164', 'label' => 'Emprunts auprès des établissements de crédit', 'type' => 'liability', 'class' => 1],
        ['code' => '18', 'label' => 'Comptes de liaison des établissements', 'type' => 'liability', 'class' => 1],

        // ── Classe 2 — Immobilisations (actif durable) ────────────────────
        ['code' => '20', 'label' => 'Immobilisations incorporelles', 'type' => 'asset', 'class' => 2],
        ['code' => '21', 'label' => 'Immobilisations corporelles', 'type' => 'asset', 'class' => 2],
        ['code' => '218', 'label' => 'Mobilier, matériel de bureau et informatique', 'type' => 'asset', 'class' => 2],
        ['code' => '23', 'label' => 'Immobilisations en cours', 'type' => 'asset', 'class' => 2],
        ['code' => '28', 'label' => 'Amortissements des immobilisations', 'type' => 'asset', 'class' => 2],

        // ── Classe 3 — Stocks et en-cours ─────────────────────────────────
        ['code' => '31', 'label' => 'Matières premières', 'type' => 'asset', 'class' => 3],
        ['code' => '33', 'label' => 'Produits finis', 'type' => 'asset', 'class' => 3],
        ['code' => '38', 'label' => 'Stocks en cours de route', 'type' => 'asset', 'class' => 3],

        // ── Classe 4 — Comptes de tiers ───────────────────────────────────
        ['code' => '401', 'label' => 'Fournisseurs', 'type' => 'liability', 'class' => 4],
        ['code' => '404', 'label' => 'Fournisseurs d\'immobilisations', 'type' => 'liability', 'class' => 4],
        ['code' => '411', 'label' => 'Clients', 'type' => 'asset', 'class' => 4],
        ['code' => '416', 'label' => 'Clients douteux ou litigieux', 'type' => 'asset', 'class' => 4],
        ['code' => '419', 'label' => 'Clients — avoirs à établir', 'type' => 'liability', 'class' => 4],
        ['code' => '421', 'label' => 'Personnel — rémunérations dues', 'type' => 'liability', 'class' => 4],
        ['code' => '425', 'label' => 'Personnel — avances et acomptes', 'type' => 'liability', 'class' => 4],
        ['code' => '428', 'label' => 'Personnel — charges à payer', 'type' => 'liability', 'class' => 4],
        ['code' => '431', 'label' => 'Sécurité sociale', 'type' => 'liability', 'class' => 4],
        ['code' => '437', 'label' => 'Autres organismes sociaux', 'type' => 'liability', 'class' => 4],
        ['code' => '4421', 'label' => 'État — impôt retenu à la source', 'type' => 'liability', 'class' => 4],
        ['code' => '444', 'label' => 'État — impôts sur les bénéfices', 'type' => 'liability', 'class' => 4],
        ['code' => '4452', 'label' => 'État — TVA due intracommunautaire', 'type' => 'liability', 'class' => 4],
        ['code' => '4455', 'label' => 'État — TVA à décaisser', 'type' => 'liability', 'class' => 4],
        ['code' => '4456', 'label' => 'État — TVA déductible', 'type' => 'asset', 'class' => 4],
        ['code' => '4457', 'label' => 'État — TVA collectée', 'type' => 'liability', 'class' => 4],
        ['code' => '4458', 'label' => 'État — TVA à régulariser', 'type' => 'liability', 'class' => 4],
        ['code' => '447', 'label' => 'Autres impôts, taxes et versements assimilés', 'type' => 'liability', 'class' => 4],
        ['code' => '45', 'label' => 'Groupe et associés', 'type' => 'liability', 'class' => 4],
        ['code' => '467', 'label' => 'Autres comptes débiteurs ou créditeurs', 'type' => 'asset', 'class' => 4],
        ['code' => '471', 'label' => 'Comptes d\'attente', 'type' => 'asset', 'class' => 4],

        // ── Classe 5 — Comptes financiers ─────────────────────────────────
        ['code' => '512', 'label' => 'Banques', 'type' => 'asset', 'class' => 5],
        ['code' => '53', 'label' => 'Caisse', 'type' => 'asset', 'class' => 5],
        ['code' => '58', 'label' => 'Virements internes', 'type' => 'asset', 'class' => 5],

        // ── Classe 6 — Charges (compte de résultat) ───────────────────────
        ['code' => '60', 'label' => 'Achats', 'type' => 'expense', 'class' => 6],
        ['code' => '606', 'label' => 'Achats non stockés de matières et fournitures', 'type' => 'expense', 'class' => 6],
        ['code' => '6064', 'label' => 'Fournitures administratives', 'type' => 'expense', 'class' => 6],
        ['code' => '61', 'label' => 'Services extérieurs', 'type' => 'expense', 'class' => 6],
        ['code' => '613', 'label' => 'Locations', 'type' => 'expense', 'class' => 6],
        ['code' => '616', 'label' => 'Primes d\'assurance', 'type' => 'expense', 'class' => 6],
        ['code' => '62', 'label' => 'Autres services extérieurs', 'type' => 'expense', 'class' => 6],
        ['code' => '622', 'label' => 'Rémunérations d\'intermédiaires et honoraires', 'type' => 'expense', 'class' => 6],
        ['code' => '623', 'label' => 'Publicité, publications, relations publiques', 'type' => 'expense', 'class' => 6],
        ['code' => '624', 'label' => 'Transports de biens et transports collectifs du personnel', 'type' => 'expense', 'class' => 6],
        ['code' => '6251', 'label' => 'Voyages et déplacements', 'type' => 'expense', 'class' => 6],
        ['code' => '6256', 'label' => 'Missions (repas, hébergement)', 'type' => 'expense', 'class' => 6],
        ['code' => '626', 'label' => 'Frais postaux et de télécommunications', 'type' => 'expense', 'class' => 6],
        ['code' => '627', 'label' => 'Services bancaires et assimilés', 'type' => 'expense', 'class' => 6],
        ['code' => '63', 'label' => 'Impôts, taxes et versements assimilés', 'type' => 'expense', 'class' => 6],
        ['code' => '635', 'label' => 'Autres impôts, taxes et versements assimilés', 'type' => 'expense', 'class' => 6],
        ['code' => '64', 'label' => 'Charges de personnel', 'type' => 'expense', 'class' => 6],
        ['code' => '641', 'label' => 'Salaires et appointements', 'type' => 'expense', 'class' => 6],
        ['code' => '645', 'label' => 'Charges de sécurité sociale et de prévoyance', 'type' => 'expense', 'class' => 6],
        ['code' => '65', 'label' => 'Autres charges de gestion courante', 'type' => 'expense', 'class' => 6],
        ['code' => '658', 'label' => 'Charges diverses de gestion courante', 'type' => 'expense', 'class' => 6],
        ['code' => '66', 'label' => 'Charges financières', 'type' => 'expense', 'class' => 6],
        ['code' => '661', 'label' => 'Intérêts des emprunts', 'type' => 'expense', 'class' => 6],
        ['code' => '67', 'label' => 'Charges exceptionnelles', 'type' => 'expense', 'class' => 6],
        ['code' => '681', 'label' => 'Dotations aux amortissements', 'type' => 'expense', 'class' => 6],

        // ── Classe 7 — Produits (compte de résultat) ──────────────────────
        ['code' => '70', 'label' => 'Ventes de produits', 'type' => 'revenue', 'class' => 7],
        ['code' => '706', 'label' => 'Prestations de services', 'type' => 'revenue', 'class' => 7],
        ['code' => '707', 'label' => 'Ventes de marchandises', 'type' => 'revenue', 'class' => 7],
        ['code' => '709', 'label' => 'Rabais, remises et ristournes', 'type' => 'revenue', 'class' => 7],
        ['code' => '74', 'label' => 'Subventions d\'exploitation', 'type' => 'revenue', 'class' => 7],
        ['code' => '75', 'label' => 'Autres produits de gestion courante', 'type' => 'revenue', 'class' => 7],
        ['code' => '76', 'label' => 'Produits financiers', 'type' => 'revenue', 'class' => 7],
        ['code' => '77', 'label' => 'Produits exceptionnels', 'type' => 'revenue', 'class' => 7],

        // ── Classe 8 — Comptes spéciaux ───────────────────────────────────
        ['code' => '80', 'label' => 'Engagements', 'type' => 'liability', 'class' => 8],
        ['code' => '89', 'label' => 'Bilan', 'type' => 'equity', 'class' => 8],
    ];

    /**
     * @return list<array{code: string, label: string, type: string, class: int}>
     */
    public static function all(): array
    {
        return self::ACCOUNTS;
    }
}
