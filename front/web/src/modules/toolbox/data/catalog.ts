import type { ToolCategory, ToolDefinition } from '../types';

/**
 * Catalogue Leopardo Toolbox — métadonnées pures.
 *
 * Ajouter un outil = 1 entrée ici + 1 dossier `tools/<slug>/` (logique + tests)
 * + 1 formulaire dans `components/tools/`. Aucune autre modification n'est
 * nécessaire : les routes `/toolbox` et `/toolbox/[slug]` sont générées depuis
 * ce catalogue (`generateStaticParams`).
 */

export const TOOL_CATEGORIES: ToolCategory[] = [
  { id: 'calcul', icon: 'calculator' },
  { id: 'conversion', icon: 'arrow-left-right' },
  { id: 'finance', icon: 'receipt' },
  { id: 'sante', icon: 'heart-pulse' },
  { id: 'travail', icon: 'briefcase' },
  { id: 'logistique', icon: 'truck' },
  { id: 'commerce', icon: 'trending-up' },
];

export const TOOLS: ToolDefinition[] = [
  {
    slug: 'imc',
    category: 'sante',
    icon: 'heart-pulse',
    version: '1.0.0',
    keywords: ['imc', 'bmi', 'poids', 'taille', 'sante', 'corpulence', 'obesite'],
    warnings: ['notAdvice'],
    references: [
      { labelKey: 'whoBmi', url: 'https://www.who.int/health-topics/obesity' },
    ],
  },
  {
    slug: 'tva',
    category: 'finance',
    icon: 'receipt',
    version: '1.0.0',
    keywords: ['tva', 'vat', 'ht', 'ttc', 'taxe', 'facture', 'france'],
    warnings: [],
    references: [
      { labelKey: 'frVatRates', url: 'https://www.impots.gouv.fr/professionnel/les-taux-de-tva' },
    ],
  },
  {
    slug: 'salaire-net',
    category: 'travail',
    icon: 'briefcase',
    version: '1.0.0',
    keywords: ['salaire', 'brut', 'net', 'paie', 'cotisations', 'remuneration'],
    warnings: ['estimate', 'notAdvice'],
    references: [],
  },
  {
    slug: 'convertisseur-unites',
    category: 'conversion',
    icon: 'arrow-left-right',
    version: '1.0.0',
    keywords: ['conversion', 'unites', 'longueur', 'masse', 'volume', 'surface', 'vitesse', 'temperature'],
    warnings: [],
    references: [
      { labelKey: 'siBrochure', url: 'https://www.bipm.org/en/publications/si-brochure' },
    ],
  },
  {
    slug: 'pourcentage',
    category: 'calcul',
    icon: 'percent',
    version: '1.0.0',
    keywords: ['pourcentage', 'pourcent', 'evolution', 'variation', 'remise', 'augmentation'],
    warnings: [],
    references: [],
  },
  {
    slug: 'regle-de-trois',
    category: 'calcul',
    icon: 'divide',
    version: '1.0.0',
    keywords: ['regle', 'trois', 'proportion', 'proportionnalite', 'ratio', 'produit en croix'],
    warnings: [],
    references: [],
  },
  {
    slug: 'surface-volume',
    category: 'calcul',
    icon: 'shapes',
    version: '1.0.0',
    keywords: ['surface', 'aire', 'volume', 'geometrie', 'cercle', 'carre', 'cylindre', 'sphere'],
    warnings: ['theoretical'],
    references: [],
  },
  {
    slug: 'cbm-poids-volumetrique',
    category: 'logistique',
    icon: 'truck',
    version: '1.0.0',
    keywords: ['cbm', 'volume', 'poids', 'volumetrique', 'transport', 'colis', 'logistique', 'fret'],
    warnings: ['estimate'],
    references: [],
  },
  {
    slug: 'marge-prix-vente',
    category: 'commerce',
    icon: 'trending-up',
    version: '1.0.0',
    keywords: ['marge', 'marque', 'prix', 'vente', 'coefficient', 'commerce', 'rentabilite'],
    warnings: [],
    references: [],
  },
  {
    slug: 'tva-multi-taux',
    category: 'finance',
    icon: 'layers',
    version: '1.0.0',
    keywords: ['tva', 'vat', 'multi', 'taux', 'facture', 'recapitulatif', 'ht', 'ttc'],
    warnings: ['estimate'],
    references: [
      { labelKey: 'frVatRates', url: 'https://www.impots.gouv.fr/professionnel/les-taux-de-tva' },
    ],
  },
  {
    slug: 'convertisseur-devises',
    category: 'conversion',
    icon: 'arrow-left-right',
    version: '1.0.0',
    keywords: ['devise', 'devises', 'currency', 'change', 'taux', 'conversion', 'eur', 'usd', 'dzd'],
    warnings: ['estimate'],
    references: [],
  },
  {
    slug: 'duree-entre-dates',
    category: 'calcul',
    icon: 'calculator',
    version: '1.0.0',
    keywords: ['duree', 'dates', 'jours', 'mois', 'annees', 'intervalle', 'calendaire'],
    warnings: [],
    references: [],
  },
  {
    slug: 'age',
    category: 'calcul',
    icon: 'calculator',
    version: '1.0.0',
    keywords: ['age', 'naissance', 'anniversaire', 'annees', 'mois', 'jours'],
    warnings: [],
    references: [],
  },
  {
    slug: 'taux-horaire',
    category: 'travail',
    icon: 'briefcase',
    version: '1.0.0',
    keywords: ['taux', 'horaire', 'salaire', 'brut', 'mensuel', 'heures', 'paie', 'travail'],
    warnings: ['estimate'],
    references: [],
  },
  {
    slug: 'calcul-date-echeance',
    category: 'calcul',
    icon: 'calculator',
    version: '1.0.0',
    keywords: [
      'date',
      'echeance',
      'delai',
      'jours',
      'ouvres',
      'ouvrables',
      'mois',
      'annees',
      'relance',
      'paiement',
    ],
    warnings: ['theoretical'],
    references: [],
  },
];
