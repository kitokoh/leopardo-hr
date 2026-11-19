/**
 * Chiffres canoniques de la vitrine (#7307).
 *
 * Pourquoi ce fichier : le même chiffre produit était annoncé différemment
 * selon la page. Le nombre de pays de paie valait **21** sur l'accueil et
 * **6** sur la page témoignages ; un acheteur qui lisait deux pages ne savait
 * plus quoi croire, et la crédibilité de tous les autres chiffres en souffrait.
 *
 * Règle : aucun nombre « produit » ne doit être écrit en dur dans une page ou
 * un contenu localisé. Il est déclaré ici, et **dérivé du registre réel**
 * chaque fois qu'une source de vérité existe côté API.
 *
 * NB : ce module ne contient que des chiffres vérifiables. Les chiffres de
 * démonstration (note moyenne, effectifs d'une maquette) restent explicitement
 * étiquetés comme tels à l'endroit où ils sont affichés.
 */

import { SUPPORTED_COUNTRIES_FALLBACK } from './supported-countries'

/**
 * Nombre de pays de paie couverts.
 *
 * Dérivé du registre canonique `SUPPORTED_COUNTRIES_FALLBACK`, lui-même
 * miroir de `GET /api/v1/supported-countries`. Dériver plutôt que recopier
 * garantit qu'ajouter un pays au registre met la vitrine à jour partout, sans
 * qu'un contenu localisé puisse rester sur une valeur périmée.
 */
export const PAYROLL_COUNTRIES_COUNT = SUPPORTED_COUNTRIES_FALLBACK.length

/**
 * Nombre de moteurs de règles de paie implémentés côté API
 * (`app/Modules/Payroll/Infrastructure/Services/CountryRules`, tous enregistrés
 * dans `CountryRulesResolver::defaultRulesMap()` au 2026-09-13) :
 * Algérie, Canada, CEDEAO, CEMAC, France, Maroc, Sénégal, Tunisie, Turquie,
 * Royaume-Uni, États-Unis.
 *
 * La vitrine annonçait « 9 moteurs » : le chiffre était **faux** (il omettait
 * les packs EN Royaume-Uni / États-Unis). Corrigé en 11 — la vitrine
 * sous-vendait le produit en plus de se contredire.
 *
 * Distinct de `PAYROLL_COUNTRIES_COUNT` : un moteur régional (CEDEAO, CEMAC)
 * couvre plusieurs pays, et CEDEAO/CEMAC sont éclatés par État membre. Les deux
 * chiffres sont vrais — leur libellé doit donc toujours dire lequel des deux il
 * mesure.
 */
export const PAYROLL_RULE_ENGINES_COUNT = 11

/** Langues de l'interface : FR/EN/AR/TR (dont l'arabe en RTL). */
export const SUPPORTED_LANGUAGES_COUNT = 4

/** Durée de l'essai gratuit, en jours. */
export const FREE_TRIAL_DAYS = 14

/** Applications mobiles publiées (manager, employé, kiosque). */
export const MOBILE_APPS_COUNT = 3
