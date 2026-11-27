/**
 * Types de l'architecture des VERTICALES cote mobile.
 *
 * Miroir fidele de `front/web/src/lib/client-features.ts` : les memes cles de
 * module, les memes groupes visuels, les memes portees (`core` / `business`),
 * les memes verticales metier. Un module mobile et un module web designent
 * donc la MEME chose, et le gating se resout avec la meme logique.
 */

/**
 * Metiers (verticales) portes par la plateforme.
 * Chaque verticale est un ensemble de modules metier actives par un feature
 * flag tenant — identique a `BusinessVertical` du web.
 */
export type BusinessVertical =
  | 'restaurant'
  | 'travel'
  | 'education'
  | 'fuel'
  | 'health'
  | 'commerce'
  | 'mobility'
  | 'trade'
  | 'hospitality';

/**
 * Groupes VISUELS de la navigation (miroir de `ClientModuleGroup`).
 * - `general`    : liens directs (Tableau de bord, Rapports, verticales) ;
 * - `hr`         : sous-menu « RH » ;
 * - `finance`    : sous-menu « Finance » ;
 * - `growth`     : sous-menu « Clients & croissance » ;
 * - `operations` : sous-menu « Operations » ;
 * - `platform`   : hors barre (panneau « Modules & plan »).
 */
export type ClientModuleGroup = 'general' | 'hr' | 'finance' | 'growth' | 'operations' | 'platform';

/** Etat d'un module pour l'utilisateur courant. */
export type FeatureState = 'available' | 'trial' | 'locked';

/**
 * Portee d'un module :
 * - `core`     : transverse, utile a toute entreprise (RH, paie, rapports…) ;
 * - `business` : metier — n'a de sens que pour la verticale du tenant.
 */
export type ClientModuleScope = 'core' | 'business';

/** Cle de module — miroir strict de `ClientModuleKey` du web. */
export type ClientModuleKey =
  | 'dashboard'
  | 'mon_logiciel'
  | 'employees'
  | 'attendance'
  | 'attendance_geo'
  | 'absences'
  | 'contracts'
  | 'recruitment'
  | 'payroll'
  | 'training'
  | 'reports'
  | 'partner'
  | 'billing'
  | 'integrations'
  | 'marketing'
  | 'accounting'
  | 'crm'
  | 'restaurant'
  | 'restaurant_kitchen'
  | 'edu_manager'
  | 'health'
  | 'health_patients'
  | 'health_appointments'
  | 'health_admissions'
  | 'health_billing'
  | 'health_referential'
  | 'travel'
  | 'travel_portal'
  | 'fuel'
  | 'fleet'
  | 'cameras'
  | 'showcase'
  | 'library'
  | 'fundraising'
  | 'communication'
  | 'assistant'
  | 'commerce'
  | 'vtc'
  | 'trade'
  | 'hospitality'
  | 'pharmacy';

/** Entree du catalogue de modules (miroir de `ClientModule`). */
export type ClientModule = {
  key: ClientModuleKey;
  /** Route web equivalente — sert de reference de parite, pas de navigation. */
  href?: string;
  label: string;
  group: ClientModuleGroup;
  /** Nom d'icone (le web utilise Lucide ; le mobile resout un glyphe). */
  icon?: string;
  /** Hierarchie du rail « Mon metier » (Cuisine sous Restaurant, etc.). */
  parentKey?: ClientModuleKey;
  capabilityKeys: string[];
  featureKeys: string[];
  allowedRoles: string[];
  upgradeLabel: string;
  /** Defaut : `core` (transverse). */
  scope?: ClientModuleScope;
  /** Renseigne pour les modules `business` : verticale de rattachement. */
  vertical?: BusinessVertical;
};

/** Module + etat resolu pour l'utilisateur courant. */
export type ClientModuleAccess = ClientModule & {
  state: FeatureState;
  enabled: boolean;
  reason: 'available' | 'trial' | 'feature_locked' | 'role_locked';
};

/** Une carte du rail « Mon metier » et ses sous-ecrans rattaches. */
export type BusinessRailEntry = {
  module: ClientModuleAccess;
  children: ClientModuleAccess[];
};

/** Descripteur d'une verticale (regroupement de modules metier). */
export type VerticalDescriptor = {
  key: BusinessVertical;
  title: string;
  description: string;
  /** Glyphe affiche dans le rail. */
  icon: string;
  /** Couleur d'accent (charte Leopardo). */
  accent: string;
  /** Modules metier de premier niveau rattaches a cette verticale. */
  moduleKeys: ClientModuleKey[];
};
