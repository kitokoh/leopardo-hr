import type { AppLocale } from '@/lib/i18n';

/**
 * #7869 — Pages SEO d'interception « Alternative à X » (/alternatives/[slug]).
 *
 * Règles de contenu (bloquantes, cf. docs/GOTO_MARKET/STRATEGIE_ACQUISITION_2026.md §3.3) :
 * - Faits concurrents vérifiables uniquement ; en cas de doute → « Non documenté ».
 * - Statuts Leopardo honnêtes : règles de paie pays = pilot (jamais « conformité validée »).
 * - Métriques datées (docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md).
 * - Motifs de catégorie interdits par check-naming-drift.sh proscrits (#7428) —
 *   utiliser « SIRH », « suite métier », « solution de gestion RH ».
 * - Ton : comparaison honnête, jamais de dénigrement — chaque page dit clairement
 *   quand le concurrent est le bon choix.
 *
 * Pattern i18n identique à blog.ts : base FR + overrides par locale (fallback FR).
 */

export interface AlternativeCriterion {
  label: string;
  competitor: string;
  leopardo: string;
}

export interface AlternativeFaq {
  question: string;
  answer: string;
}

export interface AlternativePage {
  slug: string;
  /** Nom public du concurrent (marque citée à titre comparatif). */
  competitor: string;
  /** Titre H1 / <title>. */
  title: string;
  /** Meta description (~150-160 caractères). */
  metaDescription: string;
  /** Chapeau : qui est le concurrent, pour qui il est pertinent. */
  intro: string;
  /** Section honnête « Quand choisir [concurrent] ». */
  competitorStrengths: string[];
  /** Section « Quand choisir Leopardo ». */
  leopardoStrengths: string[];
  /** Tableau comparatif. */
  criteria: AlternativeCriterion[];
  faqs: AlternativeFaq[];
  /** Date de rédaction/vérification des informations concurrent. */
  reviewedAt: Date;
}

/**
 * Ligne récurrente : rappel honnête du statut pilot de la paie multi-pays.
 * (Promesse interdite : « conformité légale validée » — garde #7058.)
 */
const PAYROLL_PILOT_NOTE =
  'Règles de paie disponibles pour 21 pays (mesuré 2026-09-09), en statut pilote : les barèmes sont fournis et testés, la validation finale reste de la responsabilité du gestionnaire de paie.';

const COMMON_FAQ_SELF_HOST: AlternativeFaq = {
  question: 'Leopardo est-il vraiment gratuit en auto-hébergement ?',
  answer:
    "Oui. Le code de Leopardo est publié sous licence MIT : vous pouvez l'auto-héberger sans redevance. L'offre SaaS hébergée, facturée par employé et par mois, existe pour les équipes qui ne veulent pas gérer l'infrastructure.",
};

const COMMON_FAQ_TRIAL: AlternativeFaq = {
  question: "Comment tester Leopardo avant de migrer ?",
  answer:
    "L'essai gratuit dure 14 jours, sans carte bancaire, avec une démo guidée et un onboarding en moins de 30 minutes. Vous pouvez importer vos employés par fichier et vérifier vos calculs de paie en parallèle de votre outil actuel.",
};

export const alternativePages: AlternativePage[] = [
  {
    slug: 'odoo',
    competitor: 'Odoo',
    title: 'Alternative à Odoo — Leopardo, la suite métier des entreprises de terrain',
    metaDescription:
      "Vous cherchez une alternative à Odoo pour la RH, la paie, le pointage et les opérations terrain ? Comparez Odoo et Leopardo : open source, paie Afrique francophone, biométrie, mode hors ligne.",
    intro:
      "Odoo est un ERP open source très complet (ventes, achats, stock, comptabilité, RH…), largement déployé dans le monde et au Maghreb via un réseau d'intégrateurs. Sa force est sa couverture fonctionnelle généraliste ; sa mise en œuvre passe souvent par l'édition Enterprise payante et un projet d'intégration. Leopardo prend l'angle inverse : une suite métier prête à l'emploi pour les entreprises de terrain — RH & paie, pointage, absences, CRM, comptabilité et opérations — pensée d'abord pour l'Afrique francophone, le Maghreb et la Turquie.",
    competitorStrengths: [
      "Vous avez besoin d'un ERP généraliste profond (achats, stock, fabrication, e-commerce) au-delà de la gestion des équipes et de la paie.",
      "Vous disposez d'un intégrateur et d'un budget projet pour configurer la solution à votre organisation.",
      "Vos processus sont majoritairement de bureau, sans forte contrainte de terrain ou de connectivité.",
    ],
    leopardoStrengths: [
      "Vos équipes sont sur le terrain : pointage QR, GPS et bornes biométriques ZKTeco natifs, avec mode hors ligne.",
      `Vous payez des salariés en Afrique francophone ou au Maghreb : ${PAYROLL_PILOT_NOTE}`,
      "Vous voulez une suite prête à l'emploi, sans projet d'intégration : essai 14 jours, onboarding en moins de 30 minutes.",
      "Vous exploitez un secteur couvert par les solutions verticales activables : agence de voyage, restauration et livraison, station-service, école (statut pilote).",
      "Vous tenez à l'open source de bout en bout : licence MIT, auto-hébergement sans édition « Enterprise » fermée.",
    ],
    criteria: [
      { label: 'Licence & auto-hébergement', competitor: 'Community LGPL + édition Enterprise propriétaire payante', leopardo: 'MIT, 100 % open source, auto-hébergeable' },
      { label: 'Paie Afrique francophone (DZ, MA, TN, SN, CI…)', competitor: 'Via localisations et modules d\'intégrateurs, selon pays', leopardo: '21 pays couverts (mesuré 2026-09-09, statut pilote)' },
      { label: 'Pointage terrain (QR, GPS, biométrie)', competitor: 'Présences de base ; biométrie via modules tiers', leopardo: 'QR, GPS et bornes ZKTeco natifs' },
      { label: 'Mode hors ligne', competitor: 'Non documenté', leopardo: 'Mode edge hors ligne avec synchronisation' },
      { label: 'CRM & comptabilité intégrés', competitor: 'Oui, très complets', leopardo: 'Oui, orientés PME de terrain' },
      { label: 'Solutions verticales (voyage, resto, carburant, école)', competitor: 'Configurables via apps et intégrateurs', leopardo: 'Packs activables par tenant (statut pilote)' },
      { label: 'Applications mobiles', competitor: 'App Odoo générique', leopardo: '7 apps Flutter dédiées par rôle (mesuré 2026-09-18)' },
      { label: 'API ouverte', competitor: 'Oui (XML-RPC / REST selon version)', leopardo: 'OpenAPI documentée — 798 endpoints (mesuré 2026-09-18)' },
      { label: 'Mise en œuvre', competitor: 'Projet d\'intégration recommandé', leopardo: 'Self-service : essai 14 j sans CB, onboarding < 30 min' },
    ],
    faqs: [
      {
        question: 'Peut-on migrer ses données Odoo vers Leopardo ?',
        answer:
          "Oui pour les données de gestion des équipes : employés, contrats et soldes de congés s'importent par fichiers (CSV/Excel). Pour un périmètre ERP complet (stock, fabrication), Leopardo ne remplace pas Odoo : la comparaison porte sur la gestion des équipes, de la paie et des opérations de terrain.",
      },
      COMMON_FAQ_SELF_HOST,
      COMMON_FAQ_TRIAL,
    ],
    reviewedAt: new Date('2026-09-20'),
  },
  {
    slug: 'sage',
    competitor: 'Sage',
    title: 'Alternative à Sage Paie & RH — Leopardo pour les PME d\'Afrique francophone',
    metaDescription:
      "Alternative à Sage pour la paie et la gestion des équipes en Afrique francophone : Leopardo est open source, mobile-first, avec pointage biométrique et mode hors ligne. Essai 14 jours.",
    intro:
      "Sage est l'éditeur historique de la comptabilité et de la paie pour PME, très implanté en France et en Afrique francophone à travers un réseau de revendeurs et d'intégrateurs. Ses produits sont éprouvés, souvent déployés sur site avec licence et prestation. Leopardo propose une approche différente : une suite métier open source et mobile-first qui réunit RH & paie, pointage, absences, CRM, comptabilité et opérations — sans licence propriétaire ni dépendance à un revendeur.",
    competitorStrengths: [
      'Vous voulez un éditeur établi de longue date, avec un réseau local de revendeurs et de cabinets formés à ses produits.',
      'Votre priorité est une comptabilité générale profonde et éprouvée, davantage que la gestion des équipes de terrain.',
      'Votre organisation exige un fournisseur historique référencé dans les appels d\'offres.',
    ],
    leopardoStrengths: [
      "Vous voulez sortir du modèle licence + intégrateur : Leopardo est open source (MIT), auto-hébergeable, ou en SaaS par employé/mois avec essai 14 jours sans CB.",
      `Vous gérez la paie dans plusieurs pays africains à la fois : ${PAYROLL_PILOT_NOTE}`,
      'Vos équipes pointent sur le terrain : QR, GPS, bornes biométriques ZKTeco et mode hors ligne natifs.',
      'Vous voulez des applications mobiles par rôle (employé, manager, RH…) plutôt qu\'un poste de travail fixe.',
      'Vous voulez une API ouverte et documentée pour intégrer vos autres outils (798 endpoints OpenAPI, mesuré 2026-09-18).',
    ],
    criteria: [
      { label: 'Licence & auto-hébergement', competitor: 'Propriétaire (licences / abonnement)', leopardo: 'MIT, open source, auto-hébergeable' },
      { label: 'Paie multi-pays Afrique francophone', competitor: 'Produits paie par pays, via revendeurs locaux', leopardo: '21 pays dans une seule suite (mesuré 2026-09-09, statut pilote)' },
      { label: 'Pointage terrain (QR, GPS, biométrie)', competitor: 'Non documenté sur les gammes PME', leopardo: 'QR, GPS et bornes ZKTeco natifs' },
      { label: 'Mode hors ligne', competitor: 'Applications desktop historiques ; offline mobile non documenté', leopardo: 'Mode edge hors ligne avec synchronisation' },
      { label: 'CRM intégré', competitor: 'Produits séparés selon gamme', leopardo: 'Inclus dans la suite' },
      { label: 'Applications mobiles', competitor: 'Selon produit', leopardo: '7 apps Flutter dédiées par rôle (mesuré 2026-09-18)' },
      { label: 'API ouverte', competitor: 'Selon produit', leopardo: 'OpenAPI documentée' },
      { label: 'Modèle de prix', competitor: 'Licence + maintenance + prestation revendeur', leopardo: 'Self-host gratuit ou SaaS par employé/mois' },
      { label: 'Mise en œuvre', competitor: 'Déploiement par revendeur/intégrateur', leopardo: 'Self-service : essai 14 j sans CB, onboarding < 30 min' },
    ],
    faqs: [
      {
        question: 'Peut-on reprendre l\'historique de paie Sage dans Leopardo ?',
        answer:
          "Les dossiers salariés, contrats et cumuls s'importent par fichiers (CSV/Excel). La bonne pratique est de démarrer Leopardo en début de mois ou d'exercice, en double calcul sur une période de paie pour vérifier les écarts avant de basculer.",
      },
      COMMON_FAQ_SELF_HOST,
      COMMON_FAQ_TRIAL,
    ],
    reviewedAt: new Date('2026-09-20'),
  },
  {
    slug: 'payfit',
    competitor: 'PayFit',
    title: 'Alternative à PayFit — Leopardo, paie et équipes terrain multi-pays',
    metaDescription:
      "Vous cherchez une alternative à PayFit hors de France ou pour des équipes terrain ? Leopardo : open source, paie multi-pays (Afrique, Turquie), pointage biométrique, CRM et comptabilité.",
    intro:
      "PayFit est une référence du SaaS de paie pour les PME en France (et sur quelques marchés européens) : excellente expérience utilisateur et forte automatisation des déclarations françaises. Si votre besoin est la paie française clé en main, c'est un choix solide. Leopardo répond à un autre besoin : une suite métier complète — RH & paie, pointage, absences, CRM, comptabilité, opérations — pour des entreprises de terrain présentes en Afrique francophone, au Maghreb, en Turquie ou sur plusieurs pays à la fois.",
    competitorStrengths: [
      'Vos salariés sont exclusivement en France (ou sur les marchés couverts par PayFit) et vous voulez une paie déléguée très automatisée.',
      'Vous n\'avez pas besoin de pointage terrain, de CRM ni de comptabilité intégrés.',
      'Vous préférez un SaaS fermé avec accompagnement éditeur, sans option d\'auto-hébergement.',
    ],
    leopardoStrengths: [
      `Vos effectifs sont en Afrique francophone, au Maghreb ou en Turquie : ${PAYROLL_PILOT_NOTE}`,
      'Vous gérez des équipes de terrain multi-sites : pointage QR/GPS/biométrique, planning et mode hors ligne natifs.',
      'Vous voulez plus que la paie : CRM, comptabilité, notes de frais et opérations dans la même suite.',
      'Vous voulez la liberté open source : code MIT, auto-hébergement possible, pas de dépendance éditeur.',
      'Votre budget est serré : self-host gratuit, ou SaaS facturé par employé/mois adapté aux PME.',
    ],
    criteria: [
      { label: 'Licence & auto-hébergement', competitor: 'SaaS propriétaire uniquement', leopardo: 'MIT, open source, auto-hébergeable' },
      { label: 'Couverture paie', competitor: 'France et quelques pays européens', leopardo: '21 pays dont Afrique francophone et Turquie (mesuré 2026-09-09, statut pilote)' },
      { label: 'Automatisation déclarative France (DSN)', competitor: 'Oui, cœur du produit', leopardo: 'Préparation de paie et exports ; déclarations FR non automatisées à ce jour' },
      { label: 'Pointage terrain (QR, GPS, biométrie)', competitor: 'Suivi des temps de base ; biométrie non documentée', leopardo: 'QR, GPS et bornes ZKTeco natifs' },
      { label: 'Mode hors ligne', competitor: 'Non documenté', leopardo: 'Mode edge hors ligne avec synchronisation' },
      { label: 'CRM & comptabilité intégrés', competitor: 'Non (intégrations tierces)', leopardo: 'Inclus dans la suite' },
      { label: 'Applications mobiles', competitor: 'App employé', leopardo: '7 apps Flutter par rôle (mesuré 2026-09-18)' },
      { label: 'API ouverte', competitor: 'API partenaires', leopardo: 'OpenAPI documentée — 798 endpoints (mesuré 2026-09-18)' },
      { label: 'Modèle de prix', competitor: 'Abonnement par salarié, France', leopardo: 'Self-host gratuit ou SaaS par employé/mois' },
    ],
    faqs: [
      {
        question: 'Leopardo automatise-t-il les déclarations sociales françaises comme PayFit ?',
        answer:
          "Non, pas au niveau de PayFit sur la France : Leopardo prépare la paie (bulletins, exports, journaux) et couvre des règles multi-pays en statut pilote. Si votre unique besoin est la DSN française clé en main, PayFit reste pertinent ; si vous opérez sur plusieurs pays ou avec des équipes terrain, Leopardo est conçu pour cela.",
      },
      COMMON_FAQ_SELF_HOST,
      COMMON_FAQ_TRIAL,
    ],
    reviewedAt: new Date('2026-09-20'),
  },
  {
    slug: 'orangehrm',
    competitor: 'OrangeHRM',
    title: 'Alternative à OrangeHRM — Leopardo, la suite open source qui va jusqu\'à la paie',
    metaDescription:
      "Alternative open source à OrangeHRM : Leopardo ajoute la paie multi-pays, le pointage biométrique, le CRM et la comptabilité — MIT, self-host ou SaaS. Essai 14 jours.",
    intro:
      "OrangeHRM est l'un des SIRH open source les plus connus au monde : sa version Starter gratuite couvre le dossier salarié, les congés et le suivi des temps, avec des éditions payantes plus complètes. C'est un bon point d'entrée RH généraliste. Leopardo joue dans une autre catégorie : une suite métier open source (MIT) qui va du dossier salarié jusqu'à la préparation de paie multi-pays, au pointage biométrique de terrain, au CRM et à la comptabilité.",
    competitorStrengths: [
      'Vous cherchez un SIRH généraliste éprouvé, avec une grande communauté internationale et une longue histoire.',
      'Votre besoin se limite au dossier salarié, aux congés et aux entretiens — sans paie ni opérations de terrain.',
      'Vous voulez une solution disponible en de nombreuses langues, orientée processus RH classiques de bureau.',
    ],
    leopardoStrengths: [
      `Vous voulez aller jusqu'à la paie dans le même outil : ${PAYROLL_PILOT_NOTE}`,
      'Vos équipes sont sur le terrain : pointage QR, GPS, bornes biométriques ZKTeco et mode hors ligne natifs.',
      'Vous voulez une suite complète : CRM, comptabilité, notes de frais et solutions verticales activables (voyage, resto, carburant, école — statut pilote).',
      'Vous voulez une licence MIT sans édition fermée : tout le code est open source, y compris les modules avancés.',
      'Vous êtes en Afrique francophone, au Maghreb ou en Turquie : langues FR/EN/TR/AR et paiements locaux.',
    ],
    criteria: [
      { label: 'Licence', competitor: 'Open source (Starter) + éditions propriétaires payantes', leopardo: 'MIT — tout le code est ouvert' },
      { label: 'Paie', competitor: 'Non incluse dans la version open source', leopardo: 'Préparation de paie multi-pays — 21 pays (mesuré 2026-09-09, statut pilote)' },
      { label: 'Pointage terrain (QR, GPS, biométrie)', competitor: 'Suivi des temps de base ; biométrie non documentée', leopardo: 'QR, GPS et bornes ZKTeco natifs' },
      { label: 'Mode hors ligne', competitor: 'Non documenté', leopardo: 'Mode edge hors ligne avec synchronisation' },
      { label: 'CRM & comptabilité', competitor: 'Non', leopardo: 'Inclus dans la suite' },
      { label: 'Solutions verticales', competitor: 'Non', leopardo: 'Packs activables par tenant (statut pilote)' },
      { label: 'Applications mobiles', competitor: 'App mobile éditeur', leopardo: '7 apps Flutter par rôle (mesuré 2026-09-18)' },
      { label: 'API ouverte', competitor: 'Selon édition', leopardo: 'OpenAPI documentée — 798 endpoints (mesuré 2026-09-18)' },
      { label: 'Modèle de prix', competitor: 'Starter gratuit, éditions payantes sur devis', leopardo: 'Self-host gratuit (MIT) ou SaaS par employé/mois' },
    ],
    faqs: [
      {
        question: 'Peut-on migrer d\'OrangeHRM vers Leopardo ?',
        answer:
          "Oui : les dossiers salariés, congés et données de présence s'exportent d'OrangeHRM (CSV) puis s'importent dans Leopardo. L'essai gratuit de 14 jours permet de valider la reprise avant de basculer.",
      },
      COMMON_FAQ_SELF_HOST,
      COMMON_FAQ_TRIAL,
    ],
    reviewedAt: new Date('2026-09-20'),
  },
  {
    slug: 'connecteam',
    competitor: 'Connecteam',
    title: 'Alternative à Connecteam — Leopardo, du pointage terrain jusqu\'à la paie',
    metaDescription:
      "Alternative à Connecteam pour les équipes terrain : Leopardo ajoute la paie multi-pays, la comptabilité et le CRM au pointage GPS et biométrique. Open source, essai 14 jours.",
    intro:
      "Connecteam est une application SaaS appréciée pour la gestion des équipes sans bureau : planning, pointage GPS, communication interne, formulaires et checklists, avec une très bonne expérience mobile. Leopardo partage cet ADN terrain — pointage QR/GPS/biométrique, planning multi-sites, mode hors ligne — mais va au bout de la chaîne : préparation de paie multi-pays, comptabilité, CRM et solutions verticales, en open source auto-hébergeable.",
    competitorStrengths: [
      'Votre besoin premier est la communication interne et les checklists opérationnelles, avec une prise en main immédiate.',
      'Vous êtes sur un marché anglophone et vos règles de paie sont gérées ailleurs (comptable, autre outil).',
      'Vous préférez un SaaS fermé tout-en-un sans vous soucier d\'hébergement ni de code.',
    ],
    leopardoStrengths: [
      `Vous voulez relier le pointage à la paie sans réexporter : ${PAYROLL_PILOT_NOTE}`,
      'Vous utilisez des bornes biométriques : intégration ZKTeco native, en plus du QR et du GPS.',
      'Votre connectivité est irrégulière : mode edge hors ligne avec synchronisation.',
      'Vous voulez aussi le CRM, la comptabilité et les notes de frais dans la même suite.',
      'Vous tenez à la souveraineté des données : open source MIT, auto-hébergement possible, langues FR/EN/TR/AR.',
    ],
    criteria: [
      { label: 'Licence & auto-hébergement', competitor: 'SaaS propriétaire uniquement', leopardo: 'MIT, open source, auto-hébergeable' },
      { label: 'Pointage terrain (QR, GPS)', competitor: 'Oui, cœur du produit', leopardo: 'Oui, natif' },
      { label: 'Bornes biométriques', competitor: 'Non documenté', leopardo: 'ZKTeco natif (kiosque dédié)' },
      { label: 'Paie', competitor: 'Non (exports vers outils de paie)', leopardo: 'Préparation de paie 21 pays (mesuré 2026-09-09, statut pilote)' },
      { label: 'CRM & comptabilité', competitor: 'Non', leopardo: 'Inclus dans la suite' },
      { label: 'Mode hors ligne', competitor: 'Partiel selon fonctionnalités', leopardo: 'Mode edge hors ligne avec synchronisation' },
      { label: 'Langues FR/AR/TR', competitor: 'Interface principalement EN', leopardo: 'FR, EN, TR, AR' },
      { label: 'API ouverte', competitor: 'API selon plan', leopardo: 'OpenAPI documentée — 798 endpoints (mesuré 2026-09-18)' },
      { label: 'Modèle de prix', competitor: 'Abonnement par utilisateur (paliers)', leopardo: 'Self-host gratuit ou SaaS par employé/mois' },
    ],
    faqs: [
      {
        question: 'Leopardo couvre-t-il la communication interne comme Connecteam ?',
        answer:
          "Leopardo inclut les notifications et les flux opérationnels liés aux équipes (plannings, approbations, documents). Si votre besoin central est un réseau social d'entreprise complet, Connecteam reste très bon sur ce point ; Leopardo se distingue quand le pointage doit alimenter la paie et la comptabilité.",
      },
      COMMON_FAQ_SELF_HOST,
      COMMON_FAQ_TRIAL,
    ],
    reviewedAt: new Date('2026-09-20'),
  },
  {
    slug: 'talenteo',
    competitor: 'Talenteo',
    title: 'Alternative à Talenteo — Leopardo, suite métier open source pour l\'Algérie et au-delà',
    metaDescription:
      "Alternative à Talenteo en Algérie : Leopardo couvre RH & paie (IRG, CNAS), pointage biométrique, CRM et comptabilité — open source, self-host ou SaaS, essai 14 jours.",
    intro:
      "Talenteo est un SIRH algérien en SaaS qui couvre la gestion RH et la paie locale (IRG, CNAS) pour les entreprises en Algérie. C'est un acteur local pertinent pour un besoin RH/paie centré sur le marché algérien. Leopardo adresse le même terrain — la paie algérienne fait partie des règles couvertes — mais avec un périmètre plus large : pointage biométrique et GPS, CRM, comptabilité, opérations et solutions verticales, en open source, pour les entreprises présentes en Algérie et dans d'autres pays.",
    competitorStrengths: [
      'Vous voulez un éditeur 100 % local, avec un accompagnement de proximité en Algérie.',
      'Votre besoin se limite à la RH et à la paie algérienne, sans pointage terrain ni comptabilité intégrée.',
      'Vous préférez un SaaS fermé géré de bout en bout par l\'éditeur.',
    ],
    leopardoStrengths: [
      `Vous opérez en Algérie ET ailleurs (Maroc, Tunisie, Sénégal, Côte d'Ivoire, Turquie…) : ${PAYROLL_PILOT_NOTE}`,
      'Vos équipes pointent sur sites : QR, GPS, bornes biométriques ZKTeco et mode hors ligne natifs.',
      'Vous voulez une suite complète : CRM, comptabilité, notes de frais, opérations et verticaux activables.',
      'Vous tenez à la souveraineté des données : code MIT auditable, auto-hébergement possible sur votre infrastructure.',
      'Vous payez en dinars via Chargily sur l\'offre SaaS, ou rien du tout en auto-hébergement.',
    ],
    criteria: [
      { label: 'Licence & auto-hébergement', competitor: 'SaaS propriétaire', leopardo: 'MIT, open source, auto-hébergeable' },
      { label: 'Paie Algérie (IRG, CNAS)', competitor: 'Oui, cœur du produit', leopardo: 'Oui (statut pilote — vérification par le gestionnaire de paie)' },
      { label: 'Paie autres pays (MA, TN, SN, CI, TR…)', competitor: 'Non documenté', leopardo: '21 pays (mesuré 2026-09-09, statut pilote)' },
      { label: 'Pointage terrain (QR, GPS, biométrie)', competitor: 'Non documenté', leopardo: 'QR, GPS et bornes ZKTeco natifs' },
      { label: 'Mode hors ligne', competitor: 'Non documenté', leopardo: 'Mode edge hors ligne avec synchronisation' },
      { label: 'CRM & comptabilité', competitor: 'Non documenté', leopardo: 'Inclus dans la suite' },
      { label: 'Applications mobiles', competitor: 'Non documenté', leopardo: '7 apps Flutter par rôle (mesuré 2026-09-18)' },
      { label: 'API ouverte', competitor: 'Non documenté', leopardo: 'OpenAPI documentée — 798 endpoints (mesuré 2026-09-18)' },
      { label: 'Modèle de prix', competitor: 'Abonnement SaaS (sur demande)', leopardo: 'Self-host gratuit ou SaaS par employé/mois, paiement local (Chargily)' },
    ],
    faqs: [
      {
        question: 'Leopardo gère-t-il les spécificités de la paie algérienne ?',
        answer:
          "Les barèmes algériens (IRG, cotisations CNAS) font partie des règles de paie couvertes, en statut pilote : les calculs sont fournis et testés, et le gestionnaire de paie garde la main sur la validation finale. L'essai de 14 jours permet de comparer vos bulletins actuels avec ceux produits par Leopardo.",
      },
      COMMON_FAQ_SELF_HOST,
      COMMON_FAQ_TRIAL,
    ],
    reviewedAt: new Date('2026-09-20'),
  },
];

/**
 * Overrides localisés (pattern blog.ts). V1 : title/metaDescription EN pour le
 * SEO international ; le corps reste FR (marchés prioritaires francophones).
 * TODO (#7869 lot 2) : contenu EN complet selon les données Search Console.
 */
const localizedAlternativePages: Partial<
  Record<AppLocale, Record<string, Partial<AlternativePage>>>
> = {
  en: {
    odoo: {
      title: 'Odoo alternative — Leopardo, the business suite for field-based companies',
      metaDescription:
        'Looking for an Odoo alternative for HR, payroll, attendance and field operations? Compare Odoo and Leopardo: open source, African payroll rules, biometric kiosks, offline mode.',
    },
    sage: {
      title: 'Sage alternative — Leopardo for SMEs in French-speaking Africa',
      metaDescription:
        'An open-source alternative to Sage for payroll and workforce management in French-speaking Africa: mobile-first, biometric attendance, offline mode. 14-day free trial.',
    },
    payfit: {
      title: 'PayFit alternative — Leopardo, multi-country payroll and field teams',
      metaDescription:
        'Looking for a PayFit alternative outside France or for field teams? Leopardo: open source, multi-country payroll (Africa, Turkey), biometric attendance, CRM and accounting.',
    },
    orangehrm: {
      title: 'OrangeHRM alternative — Leopardo, the open-source suite that includes payroll',
      metaDescription:
        'An open-source OrangeHRM alternative: Leopardo adds multi-country payroll, biometric attendance, CRM and accounting — MIT licensed, self-hosted or SaaS. 14-day trial.',
    },
    connecteam: {
      title: 'Connecteam alternative — Leopardo, from field attendance to payroll',
      metaDescription:
        'A Connecteam alternative for field teams: Leopardo adds multi-country payroll, accounting and CRM to GPS and biometric attendance. Open source, 14-day free trial.',
    },
    talenteo: {
      title: 'Talenteo alternative — Leopardo, the open-source business suite for Algeria and beyond',
      metaDescription:
        'A Talenteo alternative in Algeria: Leopardo covers HR & payroll (IRG, CNAS), biometric attendance, CRM and accounting — open source, self-hosted or SaaS. 14-day trial.',
    },
  },
};

export function getAlternativePages(locale: AppLocale): AlternativePage[] {
  const overrides = localizedAlternativePages[locale];

  if (!overrides) {
    return alternativePages;
  }

  return alternativePages.map((page) => ({
    ...page,
    ...(overrides[page.slug] ?? {}),
  }));
}

export function getAlternativePage(
  slug: string,
  locale: AppLocale
): AlternativePage | undefined {
  return getAlternativePages(locale).find((page) => page.slug === slug);
}

/**
 * Libellés UI localisés des pages /alternatives (pattern caseStudyUiCopy :
 * le COPY vit dans /vitrine/data/, catalogue inline exempté par la garde
 * check-i18n-diff.js — les pages src/app/** ne portent aucun littéral).
 */
export const alternativesUiCopy: Record<
  AppLocale,
  {
    backLink: string;
    dateLocale: string;
    reviewedAt: string;
    tableTitle: string;
    tableCriterion: string;
    whenCompetitor: (c: string) => string;
    whenLeopardo: string;
    faqTitle: string;
    ctaTitle: string;
    ctaSubtitle: string;
    ctaTrial: string;
    ctaDemo: string;
    otherComparisons: string;
    disclaimer: string;
  }
> = {
  fr: {
    backLink: 'Tous les comparatifs',
    dateLocale: 'fr-FR',
    reviewedAt: 'Informations vérifiées le',
    tableTitle: 'Comparaison point par point',
    tableCriterion: 'Critère',
    whenCompetitor: (c) => `Quand choisir ${c}`,
    whenLeopardo: 'Quand choisir Leopardo',
    faqTitle: 'Questions fréquentes',
    ctaTitle: 'Jugez sur pièces',
    ctaSubtitle:
      'Essai gratuit 14 jours, sans carte bancaire — démo guidée et onboarding en moins de 30 minutes.',
    ctaTrial: "Commencer l'essai gratuit",
    ctaDemo: 'Demander une démo',
    otherComparisons: 'Autres comparatifs',
    disclaimer:
      'Les marques citées appartiennent à leurs propriétaires respectifs. Comparatif informatif : les informations concurrents proviennent de leurs sites et documentations publics à la date de vérification ; signalez-nous toute inexactitude.',
  },
  en: {
    backLink: 'All comparisons',
    dateLocale: 'en-US',
    reviewedAt: 'Information checked on',
    tableTitle: 'Side-by-side comparison',
    tableCriterion: 'Criterion',
    whenCompetitor: (c) => `When to choose ${c}`,
    whenLeopardo: 'When to choose Leopardo',
    faqTitle: 'Frequently asked questions',
    ctaTitle: 'See for yourself',
    ctaSubtitle:
      '14-day free trial, no credit card — guided demo and onboarding in under 30 minutes.',
    ctaTrial: 'Start the free trial',
    ctaDemo: 'Request a demo',
    otherComparisons: 'Other comparisons',
    disclaimer:
      'Trademarks belong to their respective owners. Informational comparison: competitor information comes from their public websites and documentation as of the verification date; please report any inaccuracy.',
  },
  tr: {
    backLink: 'Tüm karşılaştırmalar',
    dateLocale: 'tr-TR',
    reviewedAt: 'Bilgiler şu tarihte doğrulandı:',
    tableTitle: 'Madde madde karşılaştırma',
    tableCriterion: 'Kriter',
    whenCompetitor: (c) => `${c} ne zaman seçilmeli`,
    whenLeopardo: 'Leopardo ne zaman seçilmeli',
    faqTitle: 'Sık sorulan sorular',
    ctaTitle: 'Kendiniz deneyin',
    ctaSubtitle:
      '14 gün ücretsiz deneme, kredi kartı gerekmez — rehberli demo ve 30 dakikadan kısa kurulum.',
    ctaTrial: 'Ücretsiz denemeyi başlat',
    ctaDemo: 'Demo isteyin',
    otherComparisons: 'Diğer karşılaştırmalar',
    disclaimer:
      'Markalar ilgili sahiplerine aittir. Bilgilendirme amaçlı karşılaştırma: rakip bilgileri doğrulama tarihindeki resmi sitelerden alınmıştır; hataları bildirin.',
  },
  ar: {
    backLink: 'جميع المقارنات',
    dateLocale: 'ar',
    reviewedAt: 'تم التحقق من المعلومات في',
    tableTitle: 'مقارنة بندًا ببند',
    tableCriterion: 'المعيار',
    whenCompetitor: (c) => `متى تختار ${c}`,
    whenLeopardo: 'متى تختار ليوباردو',
    faqTitle: 'الأسئلة الشائعة',
    ctaTitle: 'جرّب بنفسك',
    ctaSubtitle:
      'تجربة مجانية لمدة 14 يومًا دون بطاقة بنكية — عرض موجّه وإعداد في أقل من 30 دقيقة.',
    ctaTrial: 'ابدأ التجربة المجانية',
    ctaDemo: 'اطلب عرضًا',
    otherComparisons: 'مقارنات أخرى',
    disclaimer:
      'العلامات التجارية ملك لأصحابها. مقارنة معلوماتية: معلومات المنافسين مأخوذة من مواقعهم الرسمية في تاريخ التحقق؛ يرجى الإبلاغ عن أي خطأ.',
  },
};

export const alternativesHubCopy: Record<
  AppLocale,
  {
    eyebrow: string;
    title: string;
    subtitle: string;
    cardCta: string;
    disclaimer: string;
  }
> = {
  fr: {
    eyebrow: 'Comparatifs honnêtes',
    title: 'Leopardo face aux solutions du marché',
    subtitle:
      "Vous évaluez une solution pour la gestion de vos équipes, la paie, le pointage ou vos opérations ? Ces comparatifs vous disent clairement quand un concurrent est le bon choix — et quand Leopardo l'est.",
    cardCta: 'Lire le comparatif',
    disclaimer:
      "Les marques citées appartiennent à leurs propriétaires respectifs. Les informations concurrents sont vérifiées sur leurs sites officiels à la date indiquée sur chaque comparatif ; signalez-nous toute inexactitude.",
  },
  en: {
    eyebrow: 'Honest comparisons',
    title: 'Leopardo vs established solutions',
    subtitle:
      'Evaluating a solution for workforce management, payroll, attendance or operations? These comparisons tell you clearly when a competitor is the right choice — and when Leopardo is.',
    cardCta: 'Read the comparison',
    disclaimer:
      'Trademarks belong to their respective owners. Competitor information is checked against official websites on the date shown on each page; please report any inaccuracy.',
  },
  tr: {
    eyebrow: 'Dürüst karşılaştırmalar',
    title: 'Leopardo ve yerleşik çözümler',
    subtitle:
      'Ekip yönetimi, bordro, yoklama veya operasyonlar için bir çözüm mü değerlendiriyorsunuz? Bu karşılaştırmalar, ne zaman bir rakibin ne zaman Leopardo’nun doğru seçim olduğunu açıkça söyler.',
    cardCta: 'Karşılaştırmayı oku',
    disclaimer:
      'Markalar ilgili sahiplerine aittir. Rakip bilgileri her sayfada belirtilen tarihte resmi sitelerden doğrulanır; hata bildirin.',
  },
  ar: {
    eyebrow: 'مقارنات نزيهة',
    title: 'ليوباردو مقابل الحلول الراسخة',
    subtitle:
      'هل تقيّمون حلاً لإدارة الفرق أو الرواتب أو الحضور أو العمليات؟ تخبركم هذه المقارنات بوضوح متى يكون المنافس هو الخيار الصحيح — ومتى يكون ليوباردو كذلك.',
    cardCta: 'اقرأ المقارنة',
    disclaimer:
      'العلامات التجارية ملك لأصحابها. يتم التحقق من معلومات المنافسين من مواقعهم الرسمية في التاريخ المبيّن على كل صفحة؛ يرجى الإبلاغ عن أي خطأ.',
  },
};

export const alternativesAltLabel: Record<AppLocale, (competitor: string) => string> = {
  fr: (c) => `Alternative à ${c}`,
  en: (c) => `${c} alternative`,
  tr: (c) => `${c} alternatifi`,
  ar: (c) => `بديل ${c}`,
};

export const alternativesHubSeo: Record<AppLocale, { title: string; description: string }> = {
  fr: {
    title: 'Alternatives & comparatifs — Leopardo face aux solutions du marché',
    description:
      "Comparez Leopardo aux solutions établies (Odoo, Sage, PayFit, OrangeHRM, Connecteam, Talenteo) : open source, paie multi-pays, pointage terrain, mode hors ligne. Comparatifs honnêtes, essai 14 jours.",
  },
  en: {
    title: 'Alternatives & comparisons — Leopardo vs established solutions',
    description:
      'Compare Leopardo with established solutions (Odoo, Sage, PayFit, OrangeHRM, Connecteam, Talenteo): open source, multi-country payroll, field attendance, offline mode. Honest comparisons, 14-day trial.',
  },
  tr: {
    title: 'Alternatifler ve karşılaştırmalar — Leopardo ve yerleşik çözümler',
    description:
      'Leopardo ile yerleşik çözümleri karşılaştırın (Odoo, Sage, PayFit, OrangeHRM, Connecteam): açık kaynak, çok ülkeli bordro, saha yoklaması, çevrimdışı mod. 14 gün deneme.',
  },
  ar: {
    title: 'البدائل والمقارنات — ليوباردو مقابل الحلول الراسخة',
    description:
      'قارن ليوباردو بالحلول الراسخة (Odoo وSage وPayFit وOrangeHRM وConnecteam): مفتوح المصدر، رواتب متعددة البلدان، حضور ميداني، وضع دون اتصال. تجربة 14 يومًا.',
  },
};

export const alternativesHubLabel: Record<AppLocale, string> = {
  fr: 'Alternatives & comparatifs',
  en: 'Alternatives & comparisons',
  tr: 'Alternatifler ve karşılaştırmalar',
  ar: 'البدائل والمقارنات',
};

