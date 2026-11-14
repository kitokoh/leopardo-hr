/**
 * Leopardo Toolbox — copie i18n du module (fr, en, tr, ar).
 *
 * Le module est AUTONOME : sa copie vit ici plutôt que dans le catalogue
 * partagé `shared/i18n` (qui est propagé vers le backend et le mobile, où la
 * Toolbox n'existe pas). Les 4 locales sont obligatoires — la vitrine est
 * servie en fr/en/tr/ar et l'arabe est RTL.
 *
 * ⚠️ Toute chaîne visible doit venir d'ici : aucune copie en dur dans les
 * composants (convention vitrine, gardes mojibake/accents).
 */

export const TOOLBOX_LOCALES = ['fr', 'en', 'tr', 'ar'] as const;
export type ToolboxLocale = (typeof TOOLBOX_LOCALES)[number];

export interface ToolCopy {
  name: string;
  description: string;
  /** Libellés des champs, indexés par identifiant de champ. */
  fields: Record<string, string>;
  /** Libellés des options de listes déroulantes, indexés par valeur. */
  options?: Record<string, string>;
  /** Libellés des lignes de résultat, indexés par identifiant. */
  results: Record<string, string>;
  /** Notes d'hypothèses affichées sous le formulaire. */
  notes?: string[];
}

export interface ToolboxCopy {
  meta: { title: string; description: string };
  nav: { label: string };
  catalog: {
    title: string;
    subtitle: string;
    searchPlaceholder: string;
    noResults: string;
    allCategories: string;
    toolsCount: string;
    openTool: string;
  };
  categories: Record<string, string>;
  warnings: Record<string, string>;
  refs: Record<string, string>;
  common: {
    result: string;
    copy: string;
    copied: string;
    reference: string;
    version: string;
    backToCatalog: string;
    disclaimer: string;
    invalidInput: string;
    notFound: string;
    notFoundHint: string;
    /** Partage d'un calcul par lien (les entrées voyagent dans l'URL). */
    share: string;
    shared: string;
    shareFailed: string;
    /** Export du résultat dans un fichier texte (100 % côté client). */
    export: string;
    exported: string;
    exportFailed: string;
    /** Titre de la section « outils similaires » en bas de page d'outil. */
    related: string;
    /** Frontière d'erreur du segment `/toolbox` (jamais la frontière racine). */
    errorTitle: string;
    errorBody: string;
    errorRetry: string;
    /** Exigence technique annoncée dans le JSON-LD (`browserRequirements`). */
    browserRequirements: string;
  };
  /**
   * Espace personnel « Mes outils » (favoris + historique).
   *
   * Persistance 100 % locale (`lib/storage.ts`, décision #9156) : ces libellés
   * ne décrivent jamais un compte ni une synchronisation serveur.
   */
  myTools: {
    title: string;
    subtitle: string;
    favorites: string;
    history: string;
    empty: string;
    emptyHint: string;
    clear: string;
    addFavorite: string;
    removeFavorite: string;
  };
  tools: Record<string, ToolCopy>;
}

const fr: ToolboxCopy = {
  meta: {
    title: 'Outils en ligne gratuits',
    description:
      'Calculateurs et convertisseurs gratuits, utilisables immédiatement sans compte : IMC, TVA, salaire brut en net, conversion d’unités et pourcentages.',
  },
  nav: { label: 'Outils' },
  catalog: {
    title: 'Leopardo Toolbox',
    subtitle:
      'Des outils numériques universels, gratuits et sans inscription. Choisissez un outil et utilisez-le immédiatement.',
    searchPlaceholder: 'Rechercher un outil…',
    noResults: 'Aucun outil ne correspond à votre recherche.',
    allCategories: 'Toutes les catégories',
    toolsCount: 'outils disponibles',
    openTool: 'Ouvrir l’outil',
  },
  categories: {
    calcul: 'Calcul',
    conversion: 'Conversion',
    finance: 'Finance',
    sante: 'Santé',
    travail: 'Travail',
    logistique: 'Logistique',
    commerce: 'Commerce',
  },
  warnings: {
    estimate:
      'Résultat indicatif : cette estimation ne remplace pas un document officiel (bulletin de paie, devis, facture).',
    theoretical:
      'Résultat théorique : il ne constitue en aucun cas une validation de sécurité.',
    notAdvice:
      'Information à titre éducatif uniquement : cela ne constitue pas un conseil professionnel, médical ou juridique.',
  },
  refs: {
    whoBmi: 'Organisation mondiale de la santé — obésité et IMC',
    frVatRates: 'impots.gouv.fr — les taux de TVA',
    siBrochure: 'BIPM — brochure sur le Système international d’unités (SI)',
  },
  common: {
    result: 'Résultat',
    copy: 'Copier',
    copied: 'Copié',
    reference: 'Référence',
    version: 'Version du calcul',
    backToCatalog: 'Retour à tous les outils',
    disclaimer:
      'Les outils Leopardo sont fournis à titre informatif. Vérifiez toujours les résultats avant un usage professionnel.',
    invalidInput: 'Saisissez une valeur valide.',
    notFound: 'Outil introuvable',
    notFoundHint: 'Cet outil n’existe pas ou a été renommé. Parcourez le catalogue pour trouver l’outil souhaité.',
    share: 'Partager ce calcul',
    shared: 'Lien copié',
    shareFailed: 'Copie impossible — sélectionnez le lien ci-dessous :',
    export: 'Exporter le résultat',
    exported: 'Fichier téléchargé',
    exportFailed: 'Export impossible dans ce navigateur',
    related: 'Outils similaires',
    errorTitle: 'Cet outil a rencontré un problème',
    errorBody:
      'Le calcul n’a pas pu s’afficher. Le reste du site continue de fonctionner — vous pouvez réessayer ou revenir au catalogue.',
    errorRetry: 'Réessayer',
    browserRequirements: 'Nécessite JavaScript',
  },
  myTools: {
    title: 'Mes outils',
    subtitle: 'Vos favoris et vos outils récemment utilisés, enregistrés dans ce navigateur.',
    favorites: 'Favoris',
    history: 'Récemment utilisés',
    empty: 'Aucun outil enregistré pour le moment.',
    emptyHint: 'Ouvrez un outil et ajoutez-le à vos favoris pour le retrouver ici.',
    clear: 'Effacer mes outils',
    addFavorite: 'Ajouter aux favoris',
    removeFavorite: 'Retirer des favoris',
  },
  tools: {
    imc: {
      name: 'IMC — Indice de masse corporelle',
      description:
        'Calculez votre indice de masse corporelle à partir de votre poids et de votre taille, avec la classification de l’OMS.',
      fields: { weight: 'Poids (kg)', height: 'Taille (cm)' },
      results: { imc: 'IMC', category: 'Interprétation' },
      notes: [
        'Formule : IMC = poids (kg) ÷ taille (m)².',
        'Classification OMS : < 18,5 insuffisance pondérale ; 18,5–24,9 corpulence normale ; 25–29,9 surpoids ; ≥ 30 obésité.',
        'L’IMC ne tient pas compte de la masse musculaire, de l’âge ni de la morphologie.',
      ],
    },
    tva: {
      name: 'TVA — calcul HT / TTC',
      description:
        'Calculez la TVA, le montant hors taxes ou le montant toutes taxes comprises à partir d’un taux.',
      fields: { amount: 'Montant', rate: 'Taux de TVA', direction: 'Sens du calcul' },
      options: {
        'ht-to-ttc': 'HT → TTC (ajouter la TVA)',
        'ttc-to-ht': 'TTC → HT (retirer la TVA)',
      },
      results: { ht: 'Montant HT', vat: 'TVA', ttc: 'Montant TTC' },
      notes: [
        'Formule : TTC = HT × (1 + taux) ; HT = TTC ÷ (1 + taux).',
        'Taux français courants : 20 % (normal), 10 %, 5,5 %, 2,1 %.',
      ],
    },
    'salaire-net': {
      name: 'Salaire brut → net (estimation)',
      description:
        'Estimez le salaire net mensuel à partir du brut, avec un taux de cotisations salariales paramétrable.',
      fields: { gross: 'Salaire brut mensuel', rate: 'Taux de cotisations salariales (%)' },
      results: { net: 'Salaire net estimé', deductions: 'Cotisations estimées' },
      notes: [
        'Formule : net ≈ brut × (1 − taux de cotisations salariales).',
        'Le taux réel dépend du statut, du pays et de l’entreprise : ajustez-le pour affiner l’estimation.',
        'Cette estimation ne remplace pas un bulletin de paie officiel.',
      ],
    },
    'convertisseur-unites': {
      name: 'Convertisseur d’unités',
      description:
        'Convertissez longueurs, masses, volumes, surfaces, vitesses et températures avec des facteurs exacts.',
      fields: { value: 'Valeur', category: 'Catégorie', from: 'De', to: 'Vers' },
      results: { converted: 'Valeur convertie' },
      notes: [
        'Les facteurs de conversion sont exacts (définitions SI) ; la température utilise des formules affines.',
        'Les résultats sont arrondis à l’affichage, le calcul reste en pleine précision.',
      ],
    },
    pourcentage: {
      name: 'Pourcentages et évolution',
      description:
        'Calculez un pourcentage d’une valeur, une évolution entre deux valeurs ou une remise.',
      fields: { mode: 'Calcul', a: 'Valeur A', b: 'Valeur B' },
      options: {
        'percent-of': 'Pourcentage de A (%)',
        evolution: 'Évolution de A vers B (%)',
        discount: 'Remise de B % sur A',
      },
      results: { value: 'Résultat' },
      notes: [
        'Pourcentage de A : A × B ÷ 100.',
        'Évolution : (B − A) ÷ A × 100.',
        'Remise : A × (1 − B ÷ 100).',
      ],
    },
    'regle-de-trois': {
      name: 'Règle de trois',
      description:
        'Trouvez la quatrième valeur d’une proportion (produit en croix), en proportionnalité directe ou inverse.',
      fields: { mode: 'Type de proportion', a: 'A', b: 'B (correspond à A)', c: 'C' },
      options: {
        direct: 'Directe : A → B, donc C → ?',
        inverse: 'Inverse : A → B, donc ? → C',
      },
      results: { value: 'Valeur recherchée' },
      notes: [
        'Directe : X = B × C ÷ A.',
        'Inverse : X = A × B ÷ C.',
        'A = 0 (directe) ou C = 0 (inverse) rend le calcul indéfini.',
      ],
    },
    'surface-volume': {
      name: 'Surfaces et volumes',
      description:
        'Calculez l’aire et le volume des formes usuelles : carré, rectangle, triangle, cercle, cube, pavé droit, cylindre, sphère.',
      fields: {
        shape: 'Forme',
        a: 'Côté',
        length: 'Longueur',
        width: 'Largeur',
        height: 'Hauteur',
        base: 'Base',
        radius: 'Rayon',
      },
      results: { area: 'Aire', volume: 'Volume' },
      notes: [
        'Toutes les dimensions doivent être dans la même unité : l’aire est en unité², le volume en unité³.',
        'Cercle : π × r². Cylindre : π × r² × h. Sphère : 4/3 × π × r³.',
        'Résultat théorique : il ne constitue pas un métré officiel ni une validation de sécurité.',
      ],
    },
    'cbm-poids-volumetrique': {
      name: 'CBM et poids volumétrique',
      description:
        'Calculez le volume (CBM) d’un colis, son poids volumétrique et le poids taxable selon le mode de transport.',
      fields: {
        length: 'Longueur (cm)',
        width: 'Largeur (cm)',
        height: 'Hauteur (cm)',
        quantity: 'Nombre de colis',
        weight: 'Poids réel total (kg)',
        mode: 'Mode de transport',
      },
      results: {
        unitCbm: 'CBM par colis',
        totalCbm: 'CBM total',
        volumetricWeight: 'Poids volumétrique',
        chargeableWeight: 'Poids taxable',
      },
      notes: [
        'CBM = longueur × largeur × hauteur (en mètres).',
        'Poids volumétrique = CBM × facteur du mode (aérien 167, routier 333, maritime 1000 kg/m³).',
        'Poids taxable = le plus élevé du poids réel et du poids volumétrique.',
        'Résultat indicatif : le poids taxable réel dépend du contrat transporteur.',
      ],
    },
    'marge-prix-vente': {
      name: 'Marge et prix de vente',
      description:
        'Calculez la marge, le taux de marge, le taux de marque et le coefficient, ou déduisez un prix de vente.',
      fields: {
        mode: 'Calcul',
        cost: 'Prix d’achat (PA)',
        price: 'Prix de vente (PV)',
        rate: 'Taux (%)',
      },
      options: {
        'from-cost-price': 'Marge depuis PA et PV',
        'from-margin': 'Prix de vente depuis un taux de marge',
        'from-markup': 'Prix de vente depuis un taux de marque',
      },
      results: {
        margin: 'Marge',
        marginRate: 'Taux de marge',
        markupRate: 'Taux de marque',
        coefficient: 'Coefficient',
        price: 'Prix de vente',
      },
      notes: [
        'Taux de marge = (PV − PA) ÷ PA × 100 (rapporté au coût).',
        'Taux de marque = (PV − PA) ÷ PV × 100 (rapporté au prix de vente).',
        'PV depuis un taux de marque : PA ÷ (1 − taux ÷ 100) — indéfini si le taux atteint 100 %.',
      ],
    },
    'tva-multi-taux': {
      name: 'TVA multi-taux',
      description:
        'Récapitulez la TVA d’une facture à plusieurs lignes et plusieurs taux, regroupée par taux.',
      fields: {
        amount: 'Montant HT',
        rate: 'Taux (%)',
        addLine: 'Ajouter une ligne',
        removeLine: 'Supprimer',
      },
      results: {
        totalHt: 'Total HT',
        totalVat: 'Total TVA',
        totalTtc: 'Total TTC',
        byRate: 'Détail par taux',
      },
      notes: [
        'Chaque ligne : TVA = montant HT × taux ÷ 100.',
        'Les lignes sont regroupées par taux, comme sur une facture.',
        'Résultat indicatif : il ne remplace pas une facture ni une déclaration de TVA.',
      ],
    },
    'convertisseur-devises': {
      name: 'Convertisseur de devises (estimation)',
      description:
        'Convertissez un montant entre devises avec des taux de référence statiques, hors ligne.',
      fields: { amount: 'Montant', from: 'Devise source', to: 'Devise cible' },
      results: { converted: 'Montant converti', rate: 'Taux appliqué' },
      notes: [
        'Les taux sont des constantes de référence documentées, pas un taux de marché en temps réel.',
        'Conversion via l’euro comme pivot : montant × taux source ÷ taux cible.',
        'Résultat indicatif : ne remplace pas un taux bancaire ni une facture.',
      ],
    },
    'duree-entre-dates': {
      name: 'Durée entre deux dates',
      description:
        'Calculez la durée calendaire exacte entre deux dates, en années, mois et jours.',
      fields: { from: 'Date de début', to: 'Date de fin' },
      results: { years: 'Années', months: 'Mois', days: 'Jours', totalDays: 'Total en jours' },
      notes: [
        'Décomposition calendaire exacte (années entières, puis mois, puis jours), pas une division par 365,25.',
        'Le jour est borné à la longueur du mois d’arrivée (31 janv. + 1 mois = 28/29 févr.).',
      ],
    },
    age: {
      name: 'Âge (années, mois, jours)',
      description: 'Calculez un âge exact en années, mois et jours à une date de référence.',
      fields: { birthDate: 'Date de naissance', referenceDate: 'Date de référence' },
      results: { years: 'Années', months: 'Mois', days: 'Jours', totalDays: 'Total en jours' },
      notes: [
        'Différence calendaire exacte, pas une division par 365,25.',
        'Une date de naissance dans le futur n’a pas d’âge : le résultat est vide.',
      ],
    },
    'taux-horaire': {
      name: 'Taux horaire / salaire mensuel',
      description: 'Convertissez un salaire mensuel brut en taux horaire, et inversement.',
      fields: { mode: 'Sens de la conversion', monthlyGross: 'Salaire brut mensuel', hoursPerWeek: 'Heures par semaine' },
      options: {
        'monthly-to-hourly': 'Brut mensuel → taux horaire',
        'hourly-to-monthly': 'Taux horaire → brut mensuel',
      },
      results: { hourly: 'Taux horaire', monthly: 'Salaire mensuel brut' },
      notes: [
        'Formule : taux horaire = brut mensuel ÷ (heures par semaine × 52 ÷ 12).',
        'Le diviseur est le nombre moyen d’heures par mois sur l’année (52 semaines).',
        'Résultat approximatif : il ne remplace pas un bulletin de paie.',
      ],
    },
    'calcul-date-echeance': {
      name: 'Calcul de date d’échéance',
      description:
        'Projetez une date dans le futur ou le passé : jours calendaires, jours ouvrés, mois ou années.',
      fields: { startDate: 'Date de départ', amount: 'Décalage', unit: 'Unité' },
      options: {
        jours: 'Jours calendaires',
        'jours-ouvres': 'Jours ouvrés (lun-ven)',
        mois: 'Mois',
        annees: 'Années',
      },
      results: {
        date: 'Date obtenue',
        weekday: 'Jour de la semaine',
        totalDays: 'Décalage en jours',
        businessDays: 'Jours ouvrés décomptés',
      },
      notes: [
        'Un décalage négatif remonte dans le passé (ex. -30 jours).',
        'Les mois et les années bornent le jour à la longueur du mois d’arrivée (31 janv. + 1 mois = 28/29 févr.).',
        'Les jours ouvrés excluent samedi et dimanche, mais AUCUN jour férié n’est connu de l’outil.',
        'Résultat théorique : il ne remplace pas un calcul de délai légal ou contractuel.',
      ],
    },
  },
};

const en: ToolboxCopy = {
  meta: {
    title: 'Free online tools',
    description:
      'Free calculators and converters you can use right away, no account needed: BMI, VAT, gross-to-net salary, unit conversion and percentages.',
  },
  nav: { label: 'Tools' },
  catalog: {
    title: 'Leopardo Toolbox',
    subtitle:
      'Universal digital tools, free and with no sign-up. Pick a tool and use it immediately.',
    searchPlaceholder: 'Search for a tool…',
    noResults: 'No tool matches your search.',
    allCategories: 'All categories',
    toolsCount: 'tools available',
    openTool: 'Open tool',
  },
  categories: {
    calcul: 'Calculation',
    conversion: 'Conversion',
    finance: 'Finance',
    sante: 'Health',
    travail: 'Work',
    logistique: 'Logistics',
    commerce: 'Commerce',
  },
  warnings: {
    estimate:
      'Indicative result: this estimate does not replace an official document (payslip, quote, invoice).',
    theoretical: 'Theoretical result: it is in no way a safety validation.',
    notAdvice:
      'Educational information only: this is not professional, medical or legal advice.',
  },
  refs: {
    whoBmi: 'World Health Organization — obesity and BMI',
    frVatRates: 'impots.gouv.fr — French VAT rates',
    siBrochure: 'BIPM — SI brochure (International System of Units)',
  },
  common: {
    result: 'Result',
    copy: 'Copy',
    copied: 'Copied',
    reference: 'Reference',
    version: 'Calculation version',
    backToCatalog: 'Back to all tools',
    disclaimer:
      'Leopardo tools are provided for information only. Always verify results before professional use.',
    invalidInput: 'Enter a valid value.',
    notFound: 'Tool not found',
    notFoundHint: 'This tool does not exist or has been renamed. Browse the catalogue to find the tool you need.',
    share: 'Share this calculation',
    shared: 'Link copied',
    shareFailed: 'Copy failed — select the link below:',
    export: 'Export the result',
    exported: 'File downloaded',
    exportFailed: 'Export not available in this browser',
    related: 'Similar tools',
    errorTitle: 'This tool ran into a problem',
    errorBody:
      'The calculation could not be displayed. The rest of the site keeps working — you can retry or go back to the catalogue.',
    errorRetry: 'Retry',
    browserRequirements: 'Requires JavaScript',
  },
  myTools: {
    title: 'My tools',
    subtitle: 'Your favourites and recently used tools, saved in this browser.',
    favorites: 'Favourites',
    history: 'Recently used',
    empty: 'No tool saved yet.',
    emptyHint: 'Open a tool and add it to your favourites to find it here.',
    clear: 'Clear my tools',
    addFavorite: 'Add to favourites',
    removeFavorite: 'Remove from favourites',
  },
  tools: {
    imc: {
      name: 'BMI — Body mass index',
      description:
        'Compute your body mass index from your weight and height, with the WHO classification.',
      fields: { weight: 'Weight (kg)', height: 'Height (cm)' },
      results: { imc: 'BMI', category: 'Interpretation' },
      notes: [
        'Formula: BMI = weight (kg) ÷ height (m)².',
        'WHO classification: < 18.5 underweight; 18.5–24.9 normal; 25–29.9 overweight; ≥ 30 obesity.',
        'BMI does not account for muscle mass, age or body shape.',
      ],
    },
    tva: {
      name: 'VAT — net / gross calculation',
      description: 'Compute VAT, the net amount or the gross amount from a rate.',
      fields: { amount: 'Amount', rate: 'VAT rate', direction: 'Calculation direction' },
      options: {
        'ht-to-ttc': 'Net → gross (add VAT)',
        'ttc-to-ht': 'Gross → net (remove VAT)',
      },
      results: { ht: 'Net amount', vat: 'VAT', ttc: 'Gross amount' },
      notes: [
        'Formula: gross = net × (1 + rate); net = gross ÷ (1 + rate).',
        'Common French rates: 20% (standard), 10%, 5.5%, 2.1%.',
      ],
    },
    'salaire-net': {
      name: 'Gross → net salary (estimate)',
      description:
        'Estimate the monthly net salary from the gross amount, with an adjustable employee contribution rate.',
      fields: { gross: 'Monthly gross salary', rate: 'Employee contribution rate (%)' },
      results: { net: 'Estimated net salary', deductions: 'Estimated contributions' },
      notes: [
        'Formula: net ≈ gross × (1 − employee contribution rate).',
        'The real rate depends on status, country and employer: adjust it to refine the estimate.',
        'This estimate does not replace an official payslip.',
      ],
    },
    'convertisseur-unites': {
      name: 'Unit converter',
      description:
        'Convert lengths, masses, volumes, areas, speeds and temperatures with exact factors.',
      fields: { value: 'Value', category: 'Category', from: 'From', to: 'To' },
      results: { converted: 'Converted value' },
      notes: [
        'Conversion factors are exact (SI definitions); temperature uses affine formulas.',
        'Results are rounded for display; the computation keeps full precision.',
      ],
    },
    pourcentage: {
      name: 'Percentages and change',
      description: 'Compute a percentage of a value, a change between two values, or a discount.',
      fields: { mode: 'Calculation', a: 'Value A', b: 'Value B' },
      options: {
        'percent-of': 'Percentage of A (%)',
        evolution: 'Change from A to B (%)',
        discount: 'Discount of B% on A',
      },
      results: { value: 'Result' },
      notes: [
        'Percentage of A: A × B ÷ 100.',
        'Change: (B − A) ÷ A × 100.',
        'Discount: A × (1 − B ÷ 100).',
      ],
    },
    'regle-de-trois': {
      name: 'Rule of three',
      description:
        'Find the fourth value of a proportion (cross-multiplication), in direct or inverse proportionality.',
      fields: { mode: 'Proportion type', a: 'A', b: 'B (matches A)', c: 'C' },
      options: {
        direct: 'Direct: A → B, so C → ?',
        inverse: 'Inverse: A → B, so ? → C',
      },
      results: { value: 'Value sought' },
      notes: [
        'Direct: X = B × C ÷ A.',
        'Inverse: X = A × B ÷ C.',
        'A = 0 (direct) or C = 0 (inverse) makes the calculation undefined.',
      ],
    },
    'surface-volume': {
      name: 'Areas and volumes',
      description:
        'Compute the area and volume of common shapes: square, rectangle, triangle, circle, cube, rectangular box, cylinder, sphere.',
      fields: {
        shape: 'Shape',
        a: 'Side',
        length: 'Length',
        width: 'Width',
        height: 'Height',
        base: 'Base',
        radius: 'Radius',
      },
      results: { area: 'Area', volume: 'Volume' },
      notes: [
        'All dimensions must use the same unit: the area is in unit², the volume in unit³.',
        'Circle: π × r². Cylinder: π × r² × h. Sphere: 4/3 × π × r³.',
        'Theoretical result: it is not an official quantity survey nor a safety validation.',
      ],
    },
    'cbm-poids-volumetrique': {
      name: 'CBM and volumetric weight',
      description:
        'Compute a parcel volume (CBM), its volumetric weight and the chargeable weight for the transport mode.',
      fields: {
        length: 'Length (cm)',
        width: 'Width (cm)',
        height: 'Height (cm)',
        quantity: 'Number of parcels',
        weight: 'Total actual weight (kg)',
        mode: 'Transport mode',
      },
      results: {
        unitCbm: 'CBM per parcel',
        totalCbm: 'Total CBM',
        volumetricWeight: 'Volumetric weight',
        chargeableWeight: 'Chargeable weight',
      },
      notes: [
        'CBM = length × width × height (in metres).',
        'Volumetric weight = CBM × mode factor (air 167, road 333, sea 1000 kg/m³).',
        'Chargeable weight = the higher of actual weight and volumetric weight.',
        'Indicative result: the real chargeable weight depends on the carrier contract.',
      ],
    },
    'marge-prix-vente': {
      name: 'Margin and selling price',
      description:
        'Compute the margin, margin rate, markup rate and coefficient, or derive a selling price.',
      fields: {
        mode: 'Calculation',
        cost: 'Purchase price (cost)',
        price: 'Selling price',
        rate: 'Rate (%)',
      },
      options: {
        'from-cost-price': 'Margin from cost and price',
        'from-margin': 'Selling price from a margin rate',
        'from-markup': 'Selling price from a markup rate',
      },
      results: {
        margin: 'Margin',
        marginRate: 'Margin rate',
        markupRate: 'Markup rate',
        coefficient: 'Coefficient',
        price: 'Selling price',
      },
      notes: [
        'Margin rate = (price − cost) ÷ cost × 100 (relative to cost).',
        'Markup rate = (price − cost) ÷ price × 100 (relative to selling price).',
        'Price from a markup rate: cost ÷ (1 − rate ÷ 100) — undefined when the rate reaches 100%.',
      ],
    },
    'tva-multi-taux': {
      name: 'Multi-rate VAT',
      description:
        'Summarise the VAT of an invoice with several lines and several rates, grouped by rate.',
      fields: {
        amount: 'Net amount',
        rate: 'Rate (%)',
        addLine: 'Add a line',
        removeLine: 'Remove',
      },
      results: {
        totalHt: 'Total net',
        totalVat: 'Total VAT',
        totalTtc: 'Total gross',
        byRate: 'Breakdown by rate',
      },
      notes: [
        'Each line: VAT = net amount × rate ÷ 100.',
        'Lines are grouped by rate, as on an invoice.',
        'Indicative result: it does not replace an invoice or a VAT return.',
      ],
    },
    'convertisseur-devises': {
      name: 'Currency converter (estimate)',
      description: 'Convert an amount between currencies using static, offline reference rates.',
      fields: { amount: 'Amount', from: 'Source currency', to: 'Target currency' },
      results: { converted: 'Converted amount', rate: 'Applied rate' },
      notes: [
        'Rates are documented reference constants, not a real-time market rate.',
        'Conversion via the euro as pivot: amount × source rate ÷ target rate.',
        'Indicative result: it does not replace a bank rate or an invoice.',
      ],
    },
    'duree-entre-dates': {
      name: 'Duration between two dates',
      description:
        'Compute the exact calendar duration between two dates, in years, months and days.',
      fields: { from: 'Start date', to: 'End date' },
      results: { years: 'Years', months: 'Months', days: 'Days', totalDays: 'Total days' },
      notes: [
        'Exact calendar breakdown (whole years, then months, then days), not a division by 365.25.',
        'The day is clamped to the length of the target month (31 Jan + 1 month = 28/29 Feb).',
      ],
    },
    age: {
      name: 'Age (years, months, days)',
      description: 'Compute an exact age in years, months and days at a reference date.',
      fields: { birthDate: 'Date of birth', referenceDate: 'Reference date' },
      results: { years: 'Years', months: 'Months', days: 'Days', totalDays: 'Total days' },
      notes: [
        'Exact calendar difference, not a division by 365.25.',
        'A birth date in the future has no age: the result is empty.',
      ],
    },
    'taux-horaire': {
      name: 'Hourly rate / monthly salary',
      description: 'Convert a monthly gross salary into an hourly rate, and back.',
      fields: { mode: 'Conversion direction', monthlyGross: 'Monthly gross salary', hoursPerWeek: 'Hours per week' },
      options: {
        'monthly-to-hourly': 'Monthly gross → hourly rate',
        'hourly-to-monthly': 'Hourly rate → monthly gross',
      },
      results: { hourly: 'Hourly rate', monthly: 'Monthly gross salary' },
      notes: [
        'Formula: hourly rate = monthly gross ÷ (hours per week × 52 ÷ 12).',
        'The divisor is the average number of hours per month over the year (52 weeks).',
        'Approximate result: it does not replace a payslip.',
      ],
    },
    'calcul-date-echeance': {
      name: 'Due date calculator',
      description:
        'Project a date into the future or the past: calendar days, business days, months or years.',
      fields: { startDate: 'Start date', amount: 'Offset', unit: 'Unit' },
      options: {
        jours: 'Calendar days',
        'jours-ouvres': 'Business days (Mon-Fri)',
        mois: 'Months',
        annees: 'Years',
      },
      results: {
        date: 'Resulting date',
        weekday: 'Day of the week',
        totalDays: 'Offset in days',
        businessDays: 'Business days counted',
      },
      notes: [
        'A negative offset moves into the past (e.g. -30 days).',
        'Months and years clamp the day to the length of the target month (31 Jan + 1 month = 28/29 Feb).',
        'Business days exclude Saturday and Sunday, but NO public holiday is known to this tool.',
        'Theoretical result: it does not replace a legal or contractual deadline calculation.',
      ],
    },
  },
};

const tr: ToolboxCopy = {
  meta: {
    title: 'Ücretsiz çevrimiçi araçlar',
    description:
      'Hemen kullanabileceğiniz ücretsiz hesaplayıcılar ve dönüştürücüler, hesap gerekmez: VKİ, KDV, brütten nete maaş, birim dönüştürme ve yüzdeler.',
  },
  nav: { label: 'Araçlar' },
  catalog: {
    title: 'Leopardo Toolbox',
    subtitle:
      'Evrensel dijital araçlar, ücretsiz ve kayıt gerektirmez. Bir araç seçin ve hemen kullanın.',
    searchPlaceholder: 'Araç ara…',
    noResults: 'Aramanızla eşleşen araç yok.',
    allCategories: 'Tüm kategoriler',
    toolsCount: 'araç mevcut',
    openTool: 'Aracı aç',
  },
  categories: {
    calcul: 'Hesaplama',
    conversion: 'Dönüştürme',
    finance: 'Finans',
    sante: 'Sağlık',
    travail: 'İş',
    logistique: 'Lojistik',
    commerce: 'Ticaret',
  },
  warnings: {
    estimate:
      'Gösterge niteliğinde sonuç: bu tahmin resmi bir belgenin (bordro, teklif, fatura) yerini tutmaz.',
    theoretical: 'Teorik sonuç: hiçbir şekilde bir güvenlik doğrulaması değildir.',
    notAdvice: 'Yalnızca bilgilendirme amaçlıdır: profesyonel, tıbbi veya hukuki tavsiye değildir.',
  },
  refs: {
    whoBmi: 'Dünya Sağlık Örgütü — obezite ve VKİ',
    frVatRates: 'impots.gouv.fr — Fransız KDV oranları',
    siBrochure: 'BIPM — Uluslararası Birimler Sistemi (SI) broşürü',
  },
  common: {
    result: 'Sonuç',
    copy: 'Kopyala',
    copied: 'Kopyalandı',
    reference: 'Kaynak',
    version: 'Hesaplama sürümü',
    backToCatalog: 'Tüm araçlara dön',
    disclaimer:
      'Leopardo araçları yalnızca bilgi amaçlıdır. Profesyonel kullanımdan önce sonuçları her zaman doğrulayın.',
    invalidInput: 'Geçerli bir değer girin.',
    notFound: 'Araç bulunamadı',
    notFoundHint: 'Bu araç yok veya yeniden adlandırıldı. İhtiyacınız olan aracı bulmak için kataloğa göz atın.',
    share: 'Bu hesaplamayı paylaş',
    shared: 'Bağlantı kopyalandı',
    shareFailed: 'Kopyalanamadı — aşağıdaki bağlantıyı seçin:',
    export: 'Sonucu dışa aktar',
    exported: 'Dosya indirildi',
    exportFailed: 'Bu tarayıcıda dışa aktarma kullanılamıyor',
    related: 'Benzer araçlar',
    errorTitle: 'Bu araçta bir sorun oluştu',
    errorBody:
      'Hesaplama görüntülenemedi. Sitenin geri kalanı çalışmaya devam ediyor — yeniden deneyebilir veya kataloğa dönebilirsiniz.',
    errorRetry: 'Yeniden dene',
    browserRequirements: 'JavaScript gerektirir',
  },
  myTools: {
    title: 'Araçlarım',
    subtitle: 'Favorileriniz ve son kullandığınız araçlar bu tarayıcıda saklanır.',
    favorites: 'Favoriler',
    history: 'Son kullanılanlar',
    empty: 'Henüz kayıtlı araç yok.',
    emptyHint: 'Bir araç açın ve burada bulmak için favorilere ekleyin.',
    clear: 'Araçlarımı temizle',
    addFavorite: 'Favorilere ekle',
    removeFavorite: 'Favorilerden çıkar',
  },
  tools: {
    imc: {
      name: 'VKİ — Vücut kitle indeksi',
      description:
        'Kilo ve boyunuzdan vücut kitle indeksinizi WHO sınıflandırmasıyla hesaplayın.',
      fields: { weight: 'Kilo (kg)', height: 'Boy (cm)' },
      results: { imc: 'VKİ', category: 'Yorum' },
      notes: [
        'Formül: VKİ = kilo (kg) ÷ boy (m)².',
        'WHO sınıflandırması: < 18,5 zayıf; 18,5–24,9 normal; 25–29,9 fazla kilolu; ≥ 30 obez.',
        'VKİ kas kütlesini, yaşı veya vücut yapısını dikkate almaz.',
      ],
    },
    tva: {
      name: 'KDV — net / brüt hesaplama',
      description: 'Bir orandan KDV, net tutar veya brüt tutarı hesaplayın.',
      fields: { amount: 'Tutar', rate: 'KDV oranı', direction: 'Hesaplama yönü' },
      options: {
        'ht-to-ttc': 'Net → brüt (KDV ekle)',
        'ttc-to-ht': 'Brüt → net (KDV çıkar)',
      },
      results: { ht: 'Net tutar', vat: 'KDV', ttc: 'Brüt tutar' },
      notes: [
        'Formül: brüt = net × (1 + oran); net = brüt ÷ (1 + oran).',
        'Yaygın Fransız oranları: %20 (normal), %10, %5,5, %2,1.',
      ],
    },
    'salaire-net': {
      name: 'Brüt → net maaş (tahmin)',
      description:
        'Brüt tutardan aylık net maaşı, ayarlanabilir çalışan prim oranıyla tahmin edin.',
      fields: { gross: 'Aylık brüt maaş', rate: 'Çalışan prim oranı (%)' },
      results: { net: 'Tahmini net maaş', deductions: 'Tahmini primler' },
      notes: [
        'Formül: net ≈ brüt × (1 − çalışan prim oranı).',
        'Gerçek oran statüye, ülkeye ve işverene bağlıdır: tahmini iyileştirmek için ayarlayın.',
        'Bu tahmin resmi bir bordronun yerini tutmaz.',
      ],
    },
    'convertisseur-unites': {
      name: 'Birim dönüştürücü',
      description:
        'Uzunluk, kütle, hacim, alan, hız ve sıcaklığı kesin faktörlerle dönüştürün.',
      fields: { value: 'Değer', category: 'Kategori', from: 'Kaynak', to: 'Hedef' },
      results: { converted: 'Dönüştürülen değer' },
      notes: [
        'Dönüştürme faktörleri kesindir (SI tanımları); sıcaklık afin formüller kullanır.',
        'Sonuçlar gösterim için yuvarlanır; hesaplama tam hassasiyeti korur.',
      ],
    },
    pourcentage: {
      name: 'Yüzdeler ve değişim',
      description: 'Bir değerin yüzdesini, iki değer arasındaki değişimi veya bir indirimi hesaplayın.',
      fields: { mode: 'Hesaplama', a: 'Değer A', b: 'Değer B' },
      options: {
        'percent-of': 'A’nın yüzdesi (%)',
        evolution: 'A’dan B’ye değişim (%)',
        discount: 'A üzerinde %B indirim',
      },
      results: { value: 'Sonuç' },
      notes: [
        'A’nın yüzdesi: A × B ÷ 100.',
        'Değişim: (B − A) ÷ A × 100.',
        'İndirim: A × (1 − B ÷ 100).',
      ],
    },
    'regle-de-trois': {
      name: 'Üç kuralı',
      description:
        'Bir orantının dördüncü değerini bulun (içler dışlar çarpımı), doğru veya ters orantıyla.',
      fields: { mode: 'Orantı türü', a: 'A', b: 'B (A’ya karşılık gelir)', c: 'C' },
      options: {
        direct: 'Doğru: A → B, yani C → ?',
        inverse: 'Ters: A → B, yani ? → C',
      },
      results: { value: 'Aranan değer' },
      notes: [
        'Doğru: X = B × C ÷ A.',
        'Ters: X = A × B ÷ C.',
        'A = 0 (doğru) veya C = 0 (ters) hesaplamayı tanımsız kılar.',
      ],
    },
    'surface-volume': {
      name: 'Alan ve hacim',
      description:
        'Yaygın şekillerin alanını ve hacmini hesaplayın: kare, dikdörtgen, üçgen, daire, küp, dikdörtgen prizma, silindir, küre.',
      fields: {
        shape: 'Şekil',
        a: 'Kenar',
        length: 'Uzunluk',
        width: 'Genişlik',
        height: 'Yükseklik',
        base: 'Taban',
        radius: 'Yarıçap',
      },
      results: { area: 'Alan', volume: 'Hacim' },
      notes: [
        'Tüm ölçüler aynı birimde olmalıdır: alan birim², hacim birim³ cinsindendir.',
        'Daire: π × r². Silindir: π × r² × h. Küre: 4/3 × π × r³.',
        'Teorik sonuç: resmi bir metraj veya güvenlik doğrulaması değildir.',
      ],
    },
    'cbm-poids-volumetrique': {
      name: 'CBM ve hacimsel ağırlık',
      description:
        'Bir kolinin hacmini (CBM), hacimsel ağırlığını ve taşıma moduna göre ücretlendirilebilir ağırlığını hesaplayın.',
      fields: {
        length: 'Uzunluk (cm)',
        width: 'Genişlik (cm)',
        height: 'Yükseklik (cm)',
        quantity: 'Koli sayısı',
        weight: 'Toplam gerçek ağırlık (kg)',
        mode: 'Taşıma modu',
      },
      results: {
        unitCbm: 'Koli başına CBM',
        totalCbm: 'Toplam CBM',
        volumetricWeight: 'Hacimsel ağırlık',
        chargeableWeight: 'Ücretlendirilebilir ağırlık',
      },
      notes: [
        'CBM = uzunluk × genişlik × yükseklik (metre cinsinden).',
        'Hacimsel ağırlık = CBM × mod faktörü (hava 167, kara 333, deniz 1000 kg/m³).',
        'Ücretlendirilebilir ağırlık = gerçek ağırlık ile hacimsel ağırlığın büyüğü.',
        'Gösterge niteliğinde sonuç: gerçek ağırlık taşıyıcı sözleşmesine bağlıdır.',
      ],
    },
    'marge-prix-vente': {
      name: 'Marj ve satış fiyatı',
      description:
        'Marjı, marj oranını, kâr oranını ve katsayıyı hesaplayın veya bir satış fiyatı türetin.',
      fields: {
        mode: 'Hesaplama',
        cost: 'Alış fiyatı (maliyet)',
        price: 'Satış fiyatı',
        rate: 'Oran (%)',
      },
      options: {
        'from-cost-price': 'Maliyet ve fiyattan marj',
        'from-margin': 'Marj oranından satış fiyatı',
        'from-markup': 'Kâr oranından satış fiyatı',
      },
      results: {
        margin: 'Marj',
        marginRate: 'Marj oranı',
        markupRate: 'Kâr oranı',
        coefficient: 'Katsayı',
        price: 'Satış fiyatı',
      },
      notes: [
        'Marj oranı = (fiyat − maliyet) ÷ maliyet × 100 (maliyete göre).',
        'Kâr oranı = (fiyat − maliyet) ÷ fiyat × 100 (satış fiyatına göre).',
        'Kâr oranından fiyat: maliyet ÷ (1 − oran ÷ 100) — oran %100’e ulaşırsa tanımsız.',
      ],
    },
    'tva-multi-taux': {
      name: 'Çok oranlı KDV',
      description:
        'Birden çok satır ve oran içeren bir faturanın KDV’sini orana göre gruplayarak özetleyin.',
      fields: {
        amount: 'Net tutar',
        rate: 'Oran (%)',
        addLine: 'Satır ekle',
        removeLine: 'Kaldır',
      },
      results: {
        totalHt: 'Toplam net',
        totalVat: 'Toplam KDV',
        totalTtc: 'Toplam brüt',
        byRate: 'Orana göre döküm',
      },
      notes: [
        'Her satır: KDV = net tutar × oran ÷ 100.',
        'Satırlar faturadaki gibi orana göre gruplanır.',
        'Gösterge niteliğinde sonuç: fatura veya KDV beyannamesi yerine geçmez.',
      ],
    },
    'convertisseur-devises': {
      name: 'Döviz çevirici (tahmini)',
      description: 'Statik, çevrimdışı referans kurlarıyla bir tutarı dövizler arasında çevirin.',
      fields: { amount: 'Tutar', from: 'Kaynak döviz', to: 'Hedef döviz' },
      results: { converted: 'Çevrilen tutar', rate: 'Uygulanan kur' },
      notes: [
        'Kurlar belgelenmiş referans sabitleridir, gerçek zamanlı piyasa kuru değildir.',
        'Euro pivot üzerinden çevrim: tutar × kaynak kur ÷ hedef kur.',
        'Gösterge niteliğinde sonuç: banka kuru veya fatura yerine geçmez.',
      ],
    },
    'duree-entre-dates': {
      name: 'İki tarih arasındaki süre',
      description: 'İki tarih arasındaki tam takvim süresini yıl, ay ve gün olarak hesaplayın.',
      fields: { from: 'Başlangıç tarihi', to: 'Bitiş tarihi' },
      results: { years: 'Yıl', months: 'Ay', days: 'Gün', totalDays: 'Toplam gün' },
      notes: [
        'Tam takvim ayrıştırması (önce tam yıl, sonra ay, sonra gün), 365,25’e bölme değil.',
        'Gün, varış ayının uzunluğuna sınırlanır (31 Oca + 1 ay = 28/29 Şub).',
      ],
    },
    age: {
      name: 'Yaş (yıl, ay, gün)',
      description: 'Bir referans tarihte tam yaşı yıl, ay ve gün olarak hesaplayın.',
      fields: { birthDate: 'Doğum tarihi', referenceDate: 'Referans tarihi' },
      results: { years: 'Yıl', months: 'Ay', days: 'Gün', totalDays: 'Toplam gün' },
      notes: [
        'Tam takvim farkı, 365,25’e bölme değil.',
        'Gelecekteki bir doğum tarihinin yaşı yoktur: sonuç boştur.',
      ],
    },
    'taux-horaire': {
      name: 'Saatlik ücret / aylık maaş',
      description: 'Aylık brüt maaşı saatlik ücrete çevirin ve tersini yapın.',
      fields: { mode: 'Dönüşüm yönü', monthlyGross: 'Aylık brüt maaş', hoursPerWeek: 'Haftalık saat' },
      options: {
        'monthly-to-hourly': 'Aylık brüt → saatlik ücret',
        'hourly-to-monthly': 'Saatlik ücret → aylık brüt',
      },
      results: { hourly: 'Saatlik ücret', monthly: 'Aylık brüt maaş' },
      notes: [
        'Formül: saatlik ücret = aylık brüt ÷ (haftalık saat × 52 ÷ 12).',
        'Bölen, yıl boyunca (52 hafta) aylık ortalama saat sayısıdır.',
        'Yaklaşık sonuç: bordro yerine geçmez.',
      ],
    },
    'calcul-date-echeance': {
      name: 'Vade tarihi hesaplama',
      description:
        'Bir tarihi geleceğe veya geçmişe taşıyın: takvim günü, iş günü, ay veya yıl.',
      fields: { startDate: 'Başlangıç tarihi', amount: 'Kaydırma', unit: 'Birim' },
      options: {
        jours: 'Takvim günü',
        'jours-ouvres': 'İş günü (Pzt-Cum)',
        mois: 'Ay',
        annees: 'Yıl',
      },
      results: {
        date: 'Elde edilen tarih',
        weekday: 'Haftanın günü',
        totalDays: 'Gün cinsinden kaydırma',
        businessDays: 'Sayılan iş günü',
      },
      notes: [
        'Negatif kaydırma geçmişe gider (ör. -30 gün).',
        'Ay ve yıl, günü varış ayının uzunluğuna sınırlar (31 Oca + 1 ay = 28/29 Şub).',
        'İş günleri cumartesi ve pazarı hariç tutar, ancak bu araç HİÇBİR resmî tatili bilmez.',
        'Teorik sonuç: yasal veya sözleşmesel süre hesabının yerine geçmez.',
      ],
    },
  },
};

const ar: ToolboxCopy = {
  meta: {
    title: 'أدوات مجانية عبر الإنترنت',
    description:
      'حاسبات ومحوّلات مجانية يمكنك استخدامها فورًا دون حساب: مؤشر كتلة الجسم، ضريبة القيمة المضافة، الراتب من الإجمالي إلى الصافي، تحويل الوحدات والنسب المئوية.',
  },
  nav: { label: 'أدوات' },
  catalog: {
    title: 'صندوق أدوات ليوباردو',
    subtitle: 'أدوات رقمية عالمية، مجانية وبدون تسجيل. اختر أداة واستخدمها فورًا.',
    searchPlaceholder: 'ابحث عن أداة…',
    noResults: 'لا توجد أداة تطابق بحثك.',
    allCategories: 'كل الفئات',
    toolsCount: 'أداة متاحة',
    openTool: 'افتح الأداة',
  },
  categories: {
    calcul: 'حساب',
    conversion: 'تحويل',
    finance: 'مالية',
    sante: 'صحة',
    travail: 'عمل',
    logistique: 'اللوجستيك',
    commerce: 'التجارة',
  },
  warnings: {
    estimate: 'نتيجة إرشادية: هذا التقدير لا يحل محل وثيقة رسمية (قسيمة راتب، عرض سعر، فاتورة).',
    theoretical: 'نتيجة نظرية: لا تشكّل بأي حال من الأحوال تحققًا من السلامة.',
    notAdvice: 'معلومات لأغراض تعليمية فقط: لا تُعد نصيحة مهنية أو طبية أو قانونية.',
  },
  refs: {
    whoBmi: 'منظمة الصحة العالمية — السمنة ومؤشر كتلة الجسم',
    frVatRates: 'impots.gouv.fr — معدلات ضريبة القيمة المضافة الفرنسية',
    siBrochure: 'BIPM — كتيّب النظام الدولي للوحدات (SI)',
  },
  common: {
    result: 'النتيجة',
    copy: 'نسخ',
    copied: 'تم النسخ',
    reference: 'المرجع',
    version: 'إصدار الحساب',
    backToCatalog: 'العودة إلى كل الأدوات',
    disclaimer: 'أدوات ليوباردو مقدَّمة لأغراض إعلامية فقط. تحقّق دائمًا من النتائج قبل الاستخدام المهني.',
    invalidInput: 'أدخل قيمة صحيحة.',
    notFound: 'الأداة غير موجودة',
    notFoundHint: 'هذه الأداة غير موجودة أو تم تغيير اسمها. تصفّح الكتالوج للعثور على الأداة التي تحتاجها.',
    share: 'مشاركة هذا الحساب',
    shared: 'تم نسخ الرابط',
    shareFailed: 'تعذّر النسخ — حدّد الرابط أدناه:',
    export: 'تصدير النتيجة',
    exported: 'تم تنزيل الملف',
    exportFailed: 'التصدير غير متاح في هذا المتصفح',
    related: 'أدوات مشابهة',
    errorTitle: 'واجهت هذه الأداة مشكلة',
    errorBody:
      'تعذّر عرض الحساب. بقية الموقع تعمل بشكل طبيعي — يمكنك إعادة المحاولة أو العودة إلى الكتالوج.',
    errorRetry: 'إعادة المحاولة',
    browserRequirements: 'يتطلب JavaScript',
  },
  myTools: {
    title: 'أدواتي',
    subtitle: 'مفضلاتك والأدوات التي استخدمتها مؤخرًا، محفوظة في هذا المتصفح.',
    favorites: 'المفضلة',
    history: 'المستخدمة مؤخرًا',
    empty: 'لا توجد أداة محفوظة بعد.',
    emptyHint: 'افتح أداة وأضفها إلى المفضلة لتجدها هنا.',
    clear: 'مسح أدواتي',
    addFavorite: 'أضف إلى المفضلة',
    removeFavorite: 'إزالة من المفضلة',
  },
  tools: {
    imc: {
      name: 'مؤشر كتلة الجسم',
      description: 'احسب مؤشر كتلة جسمك من وزنك وطولك، مع تصنيف منظمة الصحة العالمية.',
      fields: { weight: 'الوزن (كغ)', height: 'الطول (سم)' },
      results: { imc: 'مؤشر كتلة الجسم', category: 'التفسير' },
      notes: [
        'الصيغة: مؤشر كتلة الجسم = الوزن (كغ) ÷ الطول (م)².',
        'تصنيف منظمة الصحة العالمية: أقل من 18.5 نقص وزن؛ 18.5–24.9 طبيعي؛ 25–29.9 زيادة وزن؛ 30 فأكثر سمنة.',
        'لا يأخذ المؤشر في الحسبان الكتلة العضلية أو العمر أو شكل الجسم.',
      ],
    },
    tva: {
      name: 'ضريبة القيمة المضافة — حساب الصافي / الإجمالي',
      description: 'احسب ضريبة القيمة المضافة أو المبلغ الصافي أو الإجمالي انطلاقًا من معدل.',
      fields: { amount: 'المبلغ', rate: 'معدل الضريبة', direction: 'اتجاه الحساب' },
      options: {
        'ht-to-ttc': 'صافي ← إجمالي (إضافة الضريبة)',
        'ttc-to-ht': 'إجمالي ← صافي (إزالة الضريبة)',
      },
      results: { ht: 'المبلغ الصافي', vat: 'الضريبة', ttc: 'المبلغ الإجمالي' },
      notes: [
        'الصيغة: الإجمالي = الصافي × (1 + المعدل)؛ الصافي = الإجمالي ÷ (1 + المعدل).',
        'المعدلات الفرنسية الشائعة: 20٪ (العادي)، 10٪، 5.5٪، 2.1٪.',
      ],
    },
    'salaire-net': {
      name: 'الراتب من الإجمالي إلى الصافي (تقدير)',
      description: 'قدّر الراتب الصافي الشهري من الإجمالي، مع معدل اشتراكات قابل للتعديل.',
      fields: { gross: 'الراتب الإجمالي الشهري', rate: 'معدل اشتراكات الموظف (٪)' },
      results: { net: 'الراتب الصافي المقدَّر', deductions: 'الاشتراكات المقدَّرة' },
      notes: [
        'الصيغة: الصافي ≈ الإجمالي × (1 − معدل اشتراكات الموظف).',
        'يعتمد المعدل الحقيقي على الوضع والبلد وصاحب العمل: عدّله لتحسين التقدير.',
        'هذا التقدير لا يحل محل قسيمة راتب رسمية.',
      ],
    },
    'convertisseur-unites': {
      name: 'محوّل الوحدات',
      description: 'حوّل الأطوال والكتل والأحجام والمساحات والسرعات ودرجات الحرارة بمعاملات دقيقة.',
      fields: { value: 'القيمة', category: 'الفئة', from: 'من', to: 'إلى' },
      results: { converted: 'القيمة المحوَّلة' },
      notes: [
        'معاملات التحويل دقيقة (تعريفات النظام الدولي)؛ تستخدم الحرارة صيغًا خطية.',
        'تُقرَّب النتائج للعرض، ويحتفظ الحساب بالدقة الكاملة.',
      ],
    },
    pourcentage: {
      name: 'النسب المئوية والتغيّر',
      description: 'احسب نسبة من قيمة، أو تغيّرًا بين قيمتين، أو خصمًا.',
      fields: { mode: 'الحساب', a: 'القيمة أ', b: 'القيمة ب' },
      options: {
        'percent-of': 'نسبة من أ (٪)',
        evolution: 'التغيّر من أ إلى ب (٪)',
        discount: 'خصم ب٪ على أ',
      },
      results: { value: 'النتيجة' },
      notes: [
        'نسبة من أ: أ × ب ÷ 100.',
        'التغيّر: (ب − أ) ÷ أ × 100.',
        'الخصم: أ × (1 − ب ÷ 100).',
      ],
    },
    'regle-de-trois': {
      name: 'قاعدة الثلاثة',
      description:
        'أوجد القيمة الرابعة في تناسب (الضرب التبادلي)، بتناسب طردي أو عكسي.',
      fields: { mode: 'نوع التناسب', a: 'أ', b: 'ب (يقابل أ)', c: 'ج' },
      options: {
        direct: 'طردي: أ → ب، إذن ج → ؟',
        inverse: 'عكسي: أ → ب، إذن ؟ → ج',
      },
      results: { value: 'القيمة المطلوبة' },
      notes: [
        'طردي: س = ب × ج ÷ أ.',
        'عكسي: س = أ × ب ÷ ج.',
        'أ = 0 (طردي) أو ج = 0 (عكسي) يجعل الحساب غير معرّف.',
      ],
    },
    'surface-volume': {
      name: 'المساحات والأحجام',
      description:
        'احسب مساحة وحجم الأشكال الشائعة: المربع، المستطيل، المثلث، الدائرة، المكعب، متوازي المستطيلات، الأسطوانة، الكرة.',
      fields: {
        shape: 'الشكل',
        a: 'الضلع',
        length: 'الطول',
        width: 'العرض',
        height: 'الارتفاع',
        base: 'القاعدة',
        radius: 'نصف القطر',
      },
      results: { area: 'المساحة', volume: 'الحجم' },
      notes: [
        'يجب أن تكون جميع الأبعاد بالوحدة نفسها: المساحة بوحدة² والحجم بوحدة³.',
        'الدائرة: π × نق². الأسطوانة: π × نق² × ع. الكرة: 4/3 × π × نق³.',
        'نتيجة نظرية: لا تُعد قياسًا رسميًا ولا تحققًا من السلامة.',
      ],
    },
    'cbm-poids-volumetrique': {
      name: 'الحجم والوزن الحجمي',
      description:
        'احسب حجم الطرد (CBM) ووزنه الحجمي والوزن الخاضع للفوترة حسب وسيلة النقل.',
      fields: {
        length: 'الطول (سم)',
        width: 'العرض (سم)',
        height: 'الارتفاع (سم)',
        quantity: 'عدد الطرود',
        weight: 'الوزن الفعلي الإجمالي (كغ)',
        mode: 'وسيلة النقل',
      },
      results: {
        unitCbm: 'الحجم لكل طرد',
        totalCbm: 'الحجم الإجمالي',
        volumetricWeight: 'الوزن الحجمي',
        chargeableWeight: 'الوزن الخاضع للفوترة',
      },
      notes: [
        'الحجم = الطول × العرض × الارتفاع (بالمتر).',
        'الوزن الحجمي = الحجم × معامل الوسيلة (جوي 167، بري 333، بحري 1000 كغ/م³).',
        'الوزن الخاضع للفوترة = الأكبر بين الوزن الفعلي والوزن الحجمي.',
        'نتيجة إرشادية: الوزن الفعلي يعتمد على عقد الناقل.',
      ],
    },
    'marge-prix-vente': {
      name: 'الهامش وسعر البيع',
      description:
        'احسب الهامش ونسبة الهامش ونسبة الربح والمعامل، أو استنتج سعر بيع.',
      fields: {
        mode: 'الحساب',
        cost: 'سعر الشراء (التكلفة)',
        price: 'سعر البيع',
        rate: 'النسبة (%)',
      },
      options: {
        'from-cost-price': 'الهامش من التكلفة وسعر البيع',
        'from-margin': 'سعر البيع من نسبة الهامش',
        'from-markup': 'سعر البيع من نسبة الربح',
      },
      results: {
        margin: 'الهامش',
        marginRate: 'نسبة الهامش',
        markupRate: 'نسبة الربح',
        coefficient: 'المعامل',
        price: 'سعر البيع',
      },
      notes: [
        'نسبة الهامش = (سعر البيع − التكلفة) ÷ التكلفة × 100 (بالنسبة للتكلفة).',
        'نسبة الربح = (سعر البيع − التكلفة) ÷ سعر البيع × 100 (بالنسبة لسعر البيع).',
        'سعر البيع من نسبة الربح: التكلفة ÷ (1 − النسبة ÷ 100) — غير معرّف إذا بلغت النسبة 100٪.',
      ],
    },
    'tva-multi-taux': {
      name: 'ضريبة القيمة المضافة متعددة المعدلات',
      description:
        'لخّص ضريبة القيمة المضافة لفاتورة بعدة أسطر ومعدلات، مجمّعة حسب المعدل.',
      fields: {
        amount: 'المبلغ الصافي',
        rate: 'المعدل (%)',
        addLine: 'إضافة سطر',
        removeLine: 'حذف',
      },
      results: {
        totalHt: 'الإجمالي الصافي',
        totalVat: 'إجمالي الضريبة',
        totalTtc: 'الإجمالي مع الضريبة',
        byRate: 'التفصيل حسب المعدل',
      },
      notes: [
        'كل سطر: الضريبة = المبلغ الصافي × المعدل ÷ 100.',
        'تُجمَّع الأسطر حسب المعدل، كما في الفاتورة.',
        'نتيجة إرشادية: لا تحل محل فاتورة أو تصريح ضريبي.',
      ],
    },
    'convertisseur-devises': {
      name: 'محوّل العملات (تقديري)',
      description: 'حوّل مبلغًا بين العملات باستخدام أسعار مرجعية ثابتة دون اتصال.',
      fields: { amount: 'المبلغ', from: 'العملة المصدر', to: 'العملة الهدف' },
      results: { converted: 'المبلغ المحوّل', rate: 'السعر المطبّق' },
      notes: [
        'الأسعار ثوابت مرجعية موثّقة، وليست سعر سوق في الوقت الحقيقي.',
        'التحويل عبر اليورو كنقطة ارتكاز: المبلغ × سعر المصدر ÷ سعر الهدف.',
        'نتيجة إرشادية: لا تحل محل سعر بنكي أو فاتورة.',
      ],
    },
    'duree-entre-dates': {
      name: 'المدة بين تاريخين',
      description: 'احسب المدة التقويمية الدقيقة بين تاريخين بالسنوات والأشهر والأيام.',
      fields: { from: 'تاريخ البداية', to: 'تاريخ النهاية' },
      results: { years: 'سنوات', months: 'أشهر', days: 'أيام', totalDays: 'الإجمالي بالأيام' },
      notes: [
        'تفكيك تقويمي دقيق (سنوات كاملة ثم أشهر ثم أيام)، وليس قسمة على 365.25.',
        'يُقيَّد اليوم بطول شهر الوصول (31 يناير + شهر = 28/29 فبراير).',
      ],
    },
    age: {
      name: 'العمر (سنوات، أشهر، أيام)',
      description: 'احسب العمر الدقيق بالسنوات والأشهر والأيام عند تاريخ مرجعي.',
      fields: { birthDate: 'تاريخ الميلاد', referenceDate: 'التاريخ المرجعي' },
      results: { years: 'سنوات', months: 'أشهر', days: 'أيام', totalDays: 'الإجمالي بالأيام' },
      notes: [
        'فرق تقويمي دقيق، وليس قسمة على 365.25.',
        'تاريخ ميلاد في المستقبل ليس له عمر: النتيجة فارغة.',
      ],
    },
    'taux-horaire': {
      name: 'الأجر بالساعة / الراتب الشهري',
      description: 'حوّل راتبًا شهريًا إجماليًا إلى أجر بالساعة، والعكس.',
      fields: { mode: 'اتجاه التحويل', monthlyGross: 'الراتب الشهري الإجمالي', hoursPerWeek: 'الساعات أسبوعيًا' },
      options: {
        'monthly-to-hourly': 'الشهري الإجمالي ← الأجر بالساعة',
        'hourly-to-monthly': 'الأجر بالساعة ← الشهري الإجمالي',
      },
      results: { hourly: 'الأجر بالساعة', monthly: 'الراتب الشهري الإجمالي' },
      notes: [
        'الصيغة: الأجر بالساعة = الراتب الشهري ÷ (الساعات أسبوعيًا × 52 ÷ 12).',
        'المقسوم عليه هو متوسط عدد الساعات شهريًا على مدار السنة (52 أسبوعًا).',
        'نتيجة تقريبية: لا تحل محل كشف الراتب.',
      ],
    },
    'calcul-date-echeance': {
      name: 'حساب تاريخ الاستحقاق',
      description:
        'انقل تاريخًا إلى المستقبل أو الماضي: أيام تقويمية أو أيام عمل أو أشهر أو سنوات.',
      fields: { startDate: 'تاريخ البداية', amount: 'الإزاحة', unit: 'الوحدة' },
      options: {
        jours: 'أيام تقويمية',
        'jours-ouvres': 'أيام عمل (الاثنين-الجمعة)',
        mois: 'أشهر',
        annees: 'سنوات',
      },
      results: {
        date: 'التاريخ الناتج',
        weekday: 'يوم الأسبوع',
        totalDays: 'الإزاحة بالأيام',
        businessDays: 'أيام العمل المحسوبة',
      },
      notes: [
        'الإزاحة السالبة ترجع إلى الماضي (مثل -30 يومًا).',
        'تُقيَّد الأشهر والسنوات بطول شهر الوصول (31 يناير + شهر = 28/29 فبراير).',
        'تستثني أيام العمل السبت والأحد، لكن الأداة لا تعرف أي عطلة رسمية.',
        'نتيجة نظرية: لا تحل محل حساب مهلة قانونية أو تعاقدية.',
      ],
    },
  },
};

const CATALOGS: Record<ToolboxLocale, ToolboxCopy> = { fr, en, tr, ar };

/** Retourne la copie complète du module pour une locale (repli : fr). */
export function getToolboxCopy(locale: string): ToolboxCopy {
  return CATALOGS[locale as ToolboxLocale] ?? CATALOGS.fr;
}

/** Retourne la copie d'un outil, ou `null` si le slug est inconnu. */
export function getToolCopy(locale: string, slug: string): ToolCopy | null {
  return getToolboxCopy(locale).tools[slug] ?? null;
}
