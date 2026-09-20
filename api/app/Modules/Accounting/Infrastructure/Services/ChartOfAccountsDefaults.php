<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

/**
 * Plan comptable par défaut — issues #5422 (socle PCG) et #7925 (seed par
 * référentiel pays).
 *
 * Le jeu de comptes provisionné au provisioning d'une entreprise est résolu
 * depuis le pays du tenant via la même logique de famille que le registre
 * AccountingChartOfAccounts (issue #7925) :
 *   - zone OHADA (AccountingChartOfAccounts::OHADA_COUNTRIES) → SYSCOHADA
 *     (AUDCIF révisé, classes 1→8) ;
 *   - TR → Tekdüzen Hesap Planı (Muhasebe Sistemi Uygulama Genel Tebliği
 *     sıra no 1, libellés localisés tr + fr) ;
 *   - CA → plan nord-américain usuel (aligné sur
 *     AccountingChartOfAccounts::CA_ACCOUNTS, confiance pilot) ;
 *   - sinon (DZ/MA/TN/FR + GB/US et pays inconnus) → socle PCG historique
 *     (non-régression — le seed dédié GB/US est hors périmètre #7925).
 *
 * Socle « famille PCG » : comptes de base des classes 1→8, alignés sur
 * les comptes effectivement utilisés par les moteurs d'écritures du module
 * (journal #5234 : 411/70/709/4457/512/53 ; paie DZ #5239 :
 * 641/645/421/431/4421/425 ; notes de frais #5235 : 6251/6256/6064/626/658)
 * et sur la nomenclature PCG française (comptes 1-8 abrégée).
 *
 * L'entreprise peut ensuite créer ses comptes analytiques et désactiver les
 * comptes inutilisés — le plan n'est jamais figé (paramétrable).
 */
final class ChartOfAccountsDefaults
{
    /**
     * @var list<array{code: string, label: string, type: string, class: int}>
     */
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
     * Seed SYSCOHADA (zone OHADA — AUDCIF révisé du 26/01/2017, plan
     * comptable SYSCOHADA classes 1→8 ; la classe 9 analytique n'est pas
     * seedée). Les subdivisions TVA 44571/44566 sont alignées sur le
     * registre AccountingChartOfAccounts::OHADA_ACCOUNTS (issue #5422)
     * pour que les moteurs d'écritures résolvent les mêmes codes.
     *
     * @var list<array{code: string, label: string, type: string, class: int}>
     */
    public const SYSCOHADA_ACCOUNTS = [
        // ── Classe 1 — Ressources durables ────────────────────────────
        ['code' => '101', 'label' => 'Capital social', 'type' => 'equity', 'class' => 1],
        ['code' => '11', 'label' => 'Réserves', 'type' => 'equity', 'class' => 1],
        ['code' => '12', 'label' => 'Report à nouveau', 'type' => 'equity', 'class' => 1],
        ['code' => '13', 'label' => 'Résultat net de l\'exercice', 'type' => 'equity', 'class' => 1],
        ['code' => '14', 'label' => 'Subventions d\'investissement', 'type' => 'equity', 'class' => 1],
        ['code' => '16', 'label' => 'Emprunts et dettes assimilées', 'type' => 'liability', 'class' => 1],
        ['code' => '162', 'label' => 'Emprunts et dettes auprès des établissements de crédit', 'type' => 'liability', 'class' => 1],

        // ── Classe 2 — Actif immobilisé ───────────────────────────────
        ['code' => '21', 'label' => 'Immobilisations incorporelles', 'type' => 'asset', 'class' => 2],
        ['code' => '22', 'label' => 'Terrains', 'type' => 'asset', 'class' => 2],
        ['code' => '23', 'label' => 'Bâtiments, installations techniques et agencements', 'type' => 'asset', 'class' => 2],
        ['code' => '24', 'label' => 'Matériel, mobilier et actifs biologiques', 'type' => 'asset', 'class' => 2],
        ['code' => '244', 'label' => 'Matériel et mobilier', 'type' => 'asset', 'class' => 2],
        ['code' => '245', 'label' => 'Matériel de transport', 'type' => 'asset', 'class' => 2],
        ['code' => '28', 'label' => 'Amortissements', 'type' => 'asset', 'class' => 2],

        // ── Classe 3 — Stocks ────────────────────────────────────────
        ['code' => '31', 'label' => 'Marchandises', 'type' => 'asset', 'class' => 3],
        ['code' => '32', 'label' => 'Matières premières et fournitures liées', 'type' => 'asset', 'class' => 3],
        ['code' => '36', 'label' => 'Produits finis', 'type' => 'asset', 'class' => 3],

        // ── Classe 4 — Tiers ─────────────────────────────────────────
        ['code' => '401', 'label' => 'Fournisseurs, dettes en compte', 'type' => 'liability', 'class' => 4],
        ['code' => '408', 'label' => 'Fournisseurs, factures non parvenues', 'type' => 'liability', 'class' => 4],
        ['code' => '411', 'label' => 'Clients', 'type' => 'asset', 'class' => 4],
        ['code' => '416', 'label' => 'Créances clientèle litigieuses ou douteuses', 'type' => 'asset', 'class' => 4],
        ['code' => '419', 'label' => 'Clients créditeurs', 'type' => 'liability', 'class' => 4],
        ['code' => '421', 'label' => 'Personnel, avances et acomptes', 'type' => 'asset', 'class' => 4],
        ['code' => '422', 'label' => 'Personnel, rémunérations dues', 'type' => 'liability', 'class' => 4],
        ['code' => '431', 'label' => 'Sécurité sociale', 'type' => 'liability', 'class' => 4],
        ['code' => '44', 'label' => 'État et collectivités publiques', 'type' => 'liability', 'class' => 4],
        ['code' => '441', 'label' => 'État, impôt sur les bénéfices', 'type' => 'liability', 'class' => 4],
        ['code' => '443', 'label' => 'État, TVA facturée', 'type' => 'liability', 'class' => 4],
        ['code' => '445', 'label' => 'État, TVA récupérable', 'type' => 'asset', 'class' => 4],
        ['code' => '44571', 'label' => 'État — TVA collectée', 'type' => 'liability', 'class' => 4],
        ['code' => '44566', 'label' => 'État — TVA déductible', 'type' => 'asset', 'class' => 4],
        ['code' => '447', 'label' => 'État, impôts retenus à la source', 'type' => 'liability', 'class' => 4],
        ['code' => '471', 'label' => 'Comptes d\'attente', 'type' => 'asset', 'class' => 4],

        // ── Classe 5 — Trésorerie ────────────────────────────────────
        ['code' => '521', 'label' => 'Banques', 'type' => 'asset', 'class' => 5],
        ['code' => '571', 'label' => 'Caisse', 'type' => 'asset', 'class' => 5],
        ['code' => '585', 'label' => 'Virements de fonds', 'type' => 'asset', 'class' => 5],

        // ── Classe 6 — Charges des activités ordinaires ───────────────────
        ['code' => '601', 'label' => 'Achats de marchandises', 'type' => 'expense', 'class' => 6],
        ['code' => '602', 'label' => 'Achats de matières premières et fournitures liées', 'type' => 'expense', 'class' => 6],
        ['code' => '605', 'label' => 'Autres achats', 'type' => 'expense', 'class' => 6],
        ['code' => '61', 'label' => 'Transports', 'type' => 'expense', 'class' => 6],
        ['code' => '622', 'label' => 'Locations et charges locatives', 'type' => 'expense', 'class' => 6],
        ['code' => '625', 'label' => 'Primes d\'assurance', 'type' => 'expense', 'class' => 6],
        ['code' => '627', 'label' => 'Publicité, publications, relations publiques', 'type' => 'expense', 'class' => 6],
        ['code' => '628', 'label' => 'Frais de télécommunications', 'type' => 'expense', 'class' => 6],
        ['code' => '631', 'label' => 'Frais bancaires', 'type' => 'expense', 'class' => 6],
        ['code' => '632', 'label' => 'Rémunérations d\'intermédiaires et de conseils', 'type' => 'expense', 'class' => 6],
        ['code' => '638', 'label' => 'Autres charges externes', 'type' => 'expense', 'class' => 6],
        ['code' => '64', 'label' => 'Impôts et taxes', 'type' => 'expense', 'class' => 6],
        ['code' => '661', 'label' => 'Rémunérations directes versées au personnel national', 'type' => 'expense', 'class' => 6],
        ['code' => '664', 'label' => 'Charges sociales', 'type' => 'expense', 'class' => 6],
        ['code' => '67', 'label' => 'Frais financiers et charges assimilées', 'type' => 'expense', 'class' => 6],
        ['code' => '681', 'label' => 'Dotations aux amortissements d\'exploitation', 'type' => 'expense', 'class' => 6],

        // ── Classe 7 — Produits des activités ordinaires ──────────────────
        ['code' => '701', 'label' => 'Ventes de marchandises', 'type' => 'revenue', 'class' => 7],
        ['code' => '702', 'label' => 'Ventes de produits finis', 'type' => 'revenue', 'class' => 7],
        ['code' => '706', 'label' => 'Services vendus', 'type' => 'revenue', 'class' => 7],
        ['code' => '707', 'label' => 'Produits accessoires', 'type' => 'revenue', 'class' => 7],
        ['code' => '71', 'label' => 'Subventions d\'exploitation', 'type' => 'revenue', 'class' => 7],
        ['code' => '77', 'label' => 'Revenus financiers et produits assimilés', 'type' => 'revenue', 'class' => 7],

        // ── Classe 8 — Autres charges et produits (HAO) ───────────────────
        ['code' => '81', 'label' => 'Valeurs comptables des cessions d\'immobilisations', 'type' => 'expense', 'class' => 8],
        ['code' => '82', 'label' => 'Produits des cessions d\'immobilisations', 'type' => 'revenue', 'class' => 8],
    ];

    /**
     * Seed Tekdüzen Hesap Planı (Turquie — Muhasebe Sistemi Uygulama Genel
     * Tebliği sıra no 1, Resmî Gazete 26/12/1992). Libellés localisés tr + fr
     * (critère #7925) — le libellé seedé est choisi selon la langue du tenant.
     * La « classe » stockée est le premier chiffre du groupe Tekdüzen
     * (1 dönen varlıklar … 7 maliyet hesapları).
     *
     * @var list<array{code: string, label: array{tr: string, fr: string}, type: string, class: int}>
     */
    public const TEKDUZEN_ACCOUNTS = [
        // ── 1 — Dönen varlıklar (actif circulant) ───────────────────────
        ['code' => '100', 'label' => ['tr' => 'Kasa', 'fr' => 'Caisse'], 'type' => 'asset', 'class' => 1],
        ['code' => '102', 'label' => ['tr' => 'Bankalar', 'fr' => 'Banques'], 'type' => 'asset', 'class' => 1],
        ['code' => '103', 'label' => ['tr' => 'Verilen çekler ve ödeme emirleri (-)', 'fr' => 'Chèques émis et ordres de paiement (-)'], 'type' => 'asset', 'class' => 1],
        ['code' => '120', 'label' => ['tr' => 'Alıcılar', 'fr' => 'Clients'], 'type' => 'asset', 'class' => 1],
        ['code' => '121', 'label' => ['tr' => 'Alacak senetleri', 'fr' => 'Effets à recevoir'], 'type' => 'asset', 'class' => 1],
        ['code' => '128', 'label' => ['tr' => 'Şüpheli ticari alacaklar', 'fr' => 'Créances commerciales douteuses'], 'type' => 'asset', 'class' => 1],
        ['code' => '150', 'label' => ['tr' => 'İlk madde ve malzeme', 'fr' => 'Matières premières et fournitures'], 'type' => 'asset', 'class' => 1],
        ['code' => '152', 'label' => ['tr' => 'Mamüller', 'fr' => 'Produits finis'], 'type' => 'asset', 'class' => 1],
        ['code' => '153', 'label' => ['tr' => 'Ticari mallar', 'fr' => 'Marchandises'], 'type' => 'asset', 'class' => 1],
        ['code' => '191', 'label' => ['tr' => 'İndirilecek KDV', 'fr' => 'TVA déductible (KDV)'], 'type' => 'asset', 'class' => 1],

        // ── 2 — Duran varlıklar (actif immobilisé) ──────────────────────
        ['code' => '252', 'label' => ['tr' => 'Binalar', 'fr' => 'Bâtiments'], 'type' => 'asset', 'class' => 2],
        ['code' => '253', 'label' => ['tr' => 'Tesis, makine ve cihazlar', 'fr' => 'Installations techniques, machines et appareils'], 'type' => 'asset', 'class' => 2],
        ['code' => '254', 'label' => ['tr' => 'Taşıtlar', 'fr' => 'Matériel de transport'], 'type' => 'asset', 'class' => 2],
        ['code' => '255', 'label' => ['tr' => 'Demirbaşlar', 'fr' => 'Mobilier et matériel'], 'type' => 'asset', 'class' => 2],
        ['code' => '257', 'label' => ['tr' => 'Birikmiş amortismanlar (-)', 'fr' => 'Amortissements cumulés (-)'], 'type' => 'asset', 'class' => 2],

        // ── 3 — Kısa vadeli yabancı kaynaklar (dettes court terme) ──────────
        ['code' => '300', 'label' => ['tr' => 'Banka kredileri', 'fr' => 'Emprunts bancaires'], 'type' => 'liability', 'class' => 3],
        ['code' => '320', 'label' => ['tr' => 'Satıcılar', 'fr' => 'Fournisseurs'], 'type' => 'liability', 'class' => 3],
        ['code' => '321', 'label' => ['tr' => 'Borç senetleri', 'fr' => 'Effets à payer'], 'type' => 'liability', 'class' => 3],
        ['code' => '335', 'label' => ['tr' => 'Personele borçlar', 'fr' => 'Personnel — rémunérations dues'], 'type' => 'liability', 'class' => 3],
        ['code' => '360', 'label' => ['tr' => 'Ödenecek vergi ve fonlar', 'fr' => 'Impôts et fonds à payer'], 'type' => 'liability', 'class' => 3],
        ['code' => '361', 'label' => ['tr' => 'Ödenecek sosyal güvenlik kesintileri', 'fr' => 'Cotisations sociales à payer (SGK)'], 'type' => 'liability', 'class' => 3],
        ['code' => '391', 'label' => ['tr' => 'Hesaplanan KDV', 'fr' => 'TVA collectée (KDV)'], 'type' => 'liability', 'class' => 3],

        // ── 4 — Uzun vadeli yabancı kaynaklar (dettes long terme) ───────────
        ['code' => '400', 'label' => ['tr' => 'Banka kredileri (uzun vadeli)', 'fr' => 'Emprunts bancaires (long terme)'], 'type' => 'liability', 'class' => 4],

        // ── 5 — Özkaynaklar (capitaux propres) ──────────────────────────
        ['code' => '500', 'label' => ['tr' => 'Sermaye', 'fr' => 'Capital social'], 'type' => 'equity', 'class' => 5],
        ['code' => '540', 'label' => ['tr' => 'Yasal yedekler', 'fr' => 'Réserves légales'], 'type' => 'equity', 'class' => 5],
        ['code' => '570', 'label' => ['tr' => 'Geçmiş yıllar kârları', 'fr' => 'Report à nouveau (bénéfices)'], 'type' => 'equity', 'class' => 5],
        ['code' => '580', 'label' => ['tr' => 'Geçmiş yıllar zararları (-)', 'fr' => 'Report à nouveau (pertes) (-)'], 'type' => 'equity', 'class' => 5],
        ['code' => '590', 'label' => ['tr' => 'Dönem net kârı', 'fr' => 'Résultat net de l\'exercice'], 'type' => 'equity', 'class' => 5],

        // ── 6 — Gelir tablosu hesapları (compte de résultat) ───────────────
        ['code' => '600', 'label' => ['tr' => 'Yurt içi satışlar', 'fr' => 'Ventes intérieures'], 'type' => 'revenue', 'class' => 6],
        ['code' => '601', 'label' => ['tr' => 'Yurt dışı satışlar', 'fr' => 'Ventes à l\'exportation'], 'type' => 'revenue', 'class' => 6],
        ['code' => '610', 'label' => ['tr' => 'Satıştan iadeler (-)', 'fr' => 'Retours sur ventes (-)'], 'type' => 'revenue', 'class' => 6],
        ['code' => '621', 'label' => ['tr' => 'Satılan ticari mallar maliyeti (-)', 'fr' => 'Coût des marchandises vendues (-)'], 'type' => 'expense', 'class' => 6],
        ['code' => '631', 'label' => ['tr' => 'Pazarlama, satış ve dağıtım giderleri (-)', 'fr' => 'Frais de marketing, vente et distribution (-)'], 'type' => 'expense', 'class' => 6],
        ['code' => '632', 'label' => ['tr' => 'Genel yönetim giderleri (-)', 'fr' => 'Frais généraux d\'administration (-)'], 'type' => 'expense', 'class' => 6],
        ['code' => '642', 'label' => ['tr' => 'Faiz gelirleri', 'fr' => 'Produits d\'intérêts'], 'type' => 'revenue', 'class' => 6],
        ['code' => '660', 'label' => ['tr' => 'Kısa vadeli borçlanma giderleri (-)', 'fr' => 'Charges d\'intérêts court terme (-)'], 'type' => 'expense', 'class' => 6],

        // ── 7 — Maliyet hesapları (comptes de coûts, option 7/A) ────────────
        ['code' => '720', 'label' => ['tr' => 'Direkt işçilik giderleri', 'fr' => 'Charges directes de main-d\'œuvre'], 'type' => 'expense', 'class' => 7],
        ['code' => '770', 'label' => ['tr' => 'Genel yönetim giderleri', 'fr' => 'Frais généraux d\'administration'], 'type' => 'expense', 'class' => 7],
    ];

    /**
     * Seed Canada — plan nord-américain usuel (pas de plan comptable
     * normalisé obligatoire au Canada ; numérotation 1000→6999 de pratique
     * courante, alignée sur AccountingChartOfAccounts::CA_ACCOUNTS).
     * Confiance pilot — à valider par un CPA avant généralisation.
     *
     * @var list<array{code: string, label: string, type: string, class: int}>
     */
    public const CA_ACCOUNTS = [
        ['code' => '1000', 'label' => 'Bank', 'type' => 'asset', 'class' => 1],
        ['code' => '1010', 'label' => 'Cash on hand', 'type' => 'asset', 'class' => 1],
        ['code' => '1100', 'label' => 'Accounts receivable', 'type' => 'asset', 'class' => 1],
        ['code' => '1200', 'label' => 'Inventory', 'type' => 'asset', 'class' => 1],
        ['code' => '1290', 'label' => 'GST/HST recoverable', 'type' => 'asset', 'class' => 1],
        ['code' => '1295', 'label' => 'QST recoverable', 'type' => 'asset', 'class' => 1],
        ['code' => '1500', 'label' => 'Property, plant and equipment', 'type' => 'asset', 'class' => 1],
        ['code' => '1600', 'label' => 'Accumulated depreciation', 'type' => 'asset', 'class' => 1],
        ['code' => '2000', 'label' => 'Accounts payable', 'type' => 'liability', 'class' => 2],
        ['code' => '2100', 'label' => 'Payroll liabilities', 'type' => 'liability', 'class' => 2],
        ['code' => '2250', 'label' => 'GST/HST payable', 'type' => 'liability', 'class' => 2],
        ['code' => '2260', 'label' => 'QST payable', 'type' => 'liability', 'class' => 2],
        ['code' => '2300', 'label' => 'Income tax payable', 'type' => 'liability', 'class' => 2],
        ['code' => '2500', 'label' => 'Long-term debt', 'type' => 'liability', 'class' => 2],
        ['code' => '3000', 'label' => 'Share capital', 'type' => 'equity', 'class' => 3],
        ['code' => '3500', 'label' => 'Retained earnings', 'type' => 'equity', 'class' => 3],
        ['code' => '4000', 'label' => 'Revenue', 'type' => 'revenue', 'class' => 4],
        ['code' => '4400', 'label' => 'Other income', 'type' => 'revenue', 'class' => 4],
        ['code' => '5000', 'label' => 'Purchases / cost of goods sold', 'type' => 'expense', 'class' => 5],
        ['code' => '6000', 'label' => 'Salaries and wages', 'type' => 'expense', 'class' => 6],
        ['code' => '6100', 'label' => 'Rent', 'type' => 'expense', 'class' => 6],
        ['code' => '6200', 'label' => 'General expenses', 'type' => 'expense', 'class' => 6],
        ['code' => '6300', 'label' => 'Insurance', 'type' => 'expense', 'class' => 6],
        ['code' => '6400', 'label' => 'Professional fees', 'type' => 'expense', 'class' => 6],
        ['code' => '6500', 'label' => 'Bank charges', 'type' => 'expense', 'class' => 6],
        ['code' => '6600', 'label' => 'Depreciation expense', 'type' => 'expense', 'class' => 6],
        ['code' => '6700', 'label' => 'Interest expense', 'type' => 'expense', 'class' => 6],
    ];

    /**
     * @return list<array{code: string, label: string, type: string, class: int}>
     */
    public static function all(): array
    {
        return self::ACCOUNTS;
    }

    /**
     * Jeu de comptes à provisionner pour un pays (issue #7925) — famille
     * résolue par AccountingChartOfAccounts::familyFor() (même logique que
     * le registre des comptes moteurs). `$language` (langue du tenant,
     * CountryDefaults) sélectionne le libellé localisé quand le référentiel
     * en propose plusieurs (Tekdüzen : tr + fr).
     *
     * @return list<array{code: string, label: string, type: string, class: int}>
     */
    public static function forCountry(?string $country, ?string $language = null): array
    {
        return match (AccountingChartOfAccounts::familyFor($country)) {
            AccountingChartOfAccounts::FAMILY_SYSCOHADA => self::SYSCOHADA_ACCOUNTS,
            AccountingChartOfAccounts::FAMILY_TEKDUZEN => self::localizedTekduzen($language),
            AccountingChartOfAccounts::FAMILY_CA => self::CA_ACCOUNTS,
            // GB/US : pas encore de seed dédié (hors périmètre #7925) — socle
            // PCG historique conservé, comme pour DZ/MA/TN/FR (non-régression).
            default => self::ACCOUNTS,
        };
    }

    /**
     * Libellés Tekdüzen localisés : français si le tenant est configuré en
     * fr, turc (langue du référentiel officiel) sinon.
     *
     * @return list<array{code: string, label: string, type: string, class: int}>
     */
    private static function localizedTekduzen(?string $language): array
    {
        $lang = strtolower(trim((string) $language)) === 'fr' ? 'fr' : 'tr';

        return array_values(array_map(
            static fn (array $account): array => [
                'code' => $account['code'],
                'label' => $account['label'][$lang],
                'type' => $account['type'],
                'class' => $account['class'],
            ],
            self::TEKDUZEN_ACCOUNTS,
        ));
    }
}
