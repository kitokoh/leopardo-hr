/**
 * Leopardo Library — types du catalogue.
 *
 * Le catalogue est DÉCLARATIF : il ne contient que des métadonnées (slug,
 * catégorie, langue, modèle d'accès, licence, source). Aucune logique de
 * calcul ni d'accès : celle-ci vit dans `lib/` (fonctions pures testées).
 *
 * Règle de contenu : seules des œuvres du DOMAINE PUBLIC ou explicitement
 * libres sont référencées dans le catalogue de démarrage. Aucune œuvre
 * protégée n'est intégrée sans autorisation (voir `license`).
 */

export type LibraryLocale = 'fr' | 'en' | 'tr' | 'ar';

/** Catégories de contenu — administrables et extensibles. */
export type LibraryCategoryId =
  | 'livres-ebooks'
  | 'education-formation'
  | 'sciences-technologie'
  | 'informatique-ia'
  | 'entrepreneuriat-gestion'
  | 'agriculture-elevage'
  | 'commerce-import-export'
  | 'voyage-tourisme'
  | 'guides-professionnels'
  | 'manuels-techniques'
  | 'documents-pratiques'
  | 'litterature';

/**
 * Modèle d'accès d'une ressource.
 *
 * - `free`        : lecture ET téléchargement libres ;
 * - `read_only`   : lecture en ligne gratuite, téléchargement désactivé ;
 * - `purchase`    : achat à l'unité (réutilise le paiement Leopardo) ;
 * - `subscription`: inclus dans un abonnement ;
 * - `private`     : réservé à un tenant/organisation (accès vérifié serveur).
 */
export type LibraryAccessModel = 'free' | 'read_only' | 'purchase' | 'subscription' | 'private';

/** Statut éditorial — workflow brouillon → validation → publié → archivé. */
export type LibraryResourceStatus = 'draft' | 'pending' | 'published' | 'archived';

/** Type de document (format principal). */
export type LibraryDocumentType = 'book' | 'ebook' | 'guide' | 'manual' | 'report' | 'article';

/** Détenteur des droits et conditions de distribution. */
export interface LibraryLicense {
  /** Identifiant de licence (ex. `public-domain`, `cc-by-4.0`, `proprietary`). */
  id: string;
  /** Détenteur des droits (auteur, éditeur, « domaine public »). */
  holder: string;
  /** Autorise le téléchargement du fichier complet. */
  allowDownload: boolean;
  /** Autorise la redistribution par des tiers. */
  allowRedistribution: boolean;
  /** Restrictions géographiques éventuelles (codes ISO 3166-1 alpha-2). */
  geoRestrictions?: string[];
}

/** Ressource de la bibliothèque (livre, guide, manuel, document…). */
export interface LibraryResource {
  /** Identifiant d'URL, unique, kebab-case. */
  slug: string;
  category: LibraryCategoryId;
  documentType: LibraryDocumentType;
  /** Langue principale du document (ISO 639-1). */
  language: string;
  /** Langues de métadonnées disponibles (au moins `language`). */
  metaLanguages: LibraryLocale[];
  accessModel: LibraryAccessModel;
  status: LibraryResourceStatus;
  /** Auteur (personne ou organisation). */
  author: string;
  /** Éditeur, si applicable. */
  publisher?: string;
  /** Année de publication (première édition connue). */
  publishedYear?: number;
  /** Nombre de pages, si connu. */
  pages?: number;
  /** Prix en minor units (entier) + devise ISO 4217 — jamais de flottant. */
  priceMinor?: number;
  currency?: string;
  /** Identifiant de tenant propriétaire (contenus `private` uniquement). */
  tenantId?: string;
  license: LibraryLicense;
  /** URL de la source publique (domaine public / licence ouverte). */
  sourceUrl?: string;
  /** Extrait de contenu (aperçu autorisé) — texte brut. */
  excerpt?: string;
  /** Contenu intégral lisible en ligne (œuvres courtes du domaine public). */
  content?: string;
  /** Mots-clés de recherche (non traduits, minuscules). */
  keywords: string[];
  /** Mise en avant éditoriale (sélection). */
  featured?: boolean;
  /** Nouveauté (ajout récent). */
  isNew?: boolean;
  /** Marqueur : entrée de démonstration (métadonnées illustratives). */
  demo?: boolean;
}

export interface LibraryCategory {
  id: LibraryCategoryId;
  icon: string;
}

/** Filtres de recherche de la bibliothèque. */
export interface LibraryFilters {
  query?: string;
  category?: LibraryCategoryId | 'all';
  language?: string | 'all';
  accessModel?: LibraryAccessModel | 'all';
  documentType?: LibraryDocumentType | 'all';
  freeOnly?: boolean;
}
