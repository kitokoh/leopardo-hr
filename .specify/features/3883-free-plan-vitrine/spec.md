# Feature Specification: Plan Free visible sur la vitrine (Closes #3883)

**Feature Branch**: `fix/3883-free-plan-vitrine`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #3883 (P2, billing, web)

## Contexte

Le `PlanSeeder` (schéma canonique #2977) crée 4 plans actifs : **Free** (0 €/mois,
5 employés), **Pilot** (29 €/mois, 30 emp), **Operations** (99 €/mois, 250 emp),
**Enterprise** (sur devis). Le checkout commentait « free plan supprimé du backend »
(résidu #3247, depuis invalidé par #2977), et `pricing.ts` n'exportait que 3 plans :
le plan d'entrée des très petites équipes (< 5 personnes) était invisible.

Toute l'UI était déjà préparée pour 4 plans : grille `/pricing` en
`xl:grid-cols-4`, badge « gratuit » (`isFree`), styles `isFree` des cartes,
colonne `free` du tableau comparateur (`getFeatureValue`), et `getPlanHref`
avec un fallback défensif pour `price === '0'`. Il ne manquait que la donnée
et le câblage CTA — preuve que l'affichage du plan Free était prévu puis retiré
lors de la restructuration #3919.

## User Stories & Testing

### User Story 1 — Un prospect TPE découvre un point d'entrée gratuit (P1)

En tant que dirigeant d'une équipe de 3 personnes, je veux voir un plan à 0 €
sur la page tarifs pour m'inscrire sans risque.

**Acceptance Scenarios**:
1. Given la page `/pricing` (4 locales), When je consulte les plans, Then je vois
   4 cartes : Free (0 €), Pilot (29 €), Operations (99 €), Enterprise (sur devis).
2. Given la section tarifs de la home page, When je consulte les plans, Then Free
   est affiché en tête avec un style distinct (slate).
3. Given le CTA « Commencer gratuitement » du plan Free, When je clique, Then je
   suis redirigé vers l'essai guidé sans carte (`/signup?source=pricing_free`),
   jamais vers un paywall de checkout (leçon #2907).
4. Given le tableau comparateur, When la colonne Free est affichée, Then les
   valeurs correspondent à la colonne `free` existante (pointage web seulement,
   absences, app Employee, pas de paie/API/SSO).

### Edge Cases

- `plan.price === '0'` → CTA `/signup?source=pricing_free` (essai guidé), pas de
  checkout — le checkout n'a pas de configuration Free facturable (0 €).
- Toggle mensuel/annuel : Free affiche 0 €/0 € dans les deux cas (annualPrice 0).
- `planNameToCheckoutKey('Free')` → `'free'` (fallback déjà testé).
- JSON-LD FAQPage (`pricing/layout.tsx`) : la nouvelle entrée FAQ « Free » est
  incluse automatiquement via `getPricingFaq()`.

## Requirements

### Functional Requirements

- **FR-001**: `pricing.ts` DOIT exposer 4 plans (Free/Pilot/Operations/Enterprise)
  dans les 4 locales, Free en premier, `price`/`annualPrice` = `'0'`,
  `employeeLimit` ≤ 5 employés.
- **FR-002**: le CTA du plan Free DOIT pointer vers `/signup?source=pricing_free`
  (page `/pricing` ET section home `PricingSection`) — jamais vers `/checkout`.
- **FR-003**: les grilles de cartes DOIVENT accueillir 4 colonnes
  (home : `sm:grid-cols-2 lg:grid-cols-4`).
- **FR-004**: la FAQ tarifs DOIT mentionner le plan Free (entrée dédiée ×4 locales)
  et les plafonds 5/30/250/illimité dans la question facturation.
- **FR-005**: le test `pricing-checkout-alignment` DOIT refléter le jeu complet
  (4 plans) avec Free ↔ checkout 0/0.

## Success Criteria

- **SC-001**: les 4 cartes de plans s'affichent sur `/pricing` et la home, 4 locales.
- **SC-002**: aucun CTA Free ne mène au checkout (grep `plan=free` sur les CTA = 0).
- **SC-003**: `npm run lint`, `tsc`, et les tests vitrine passent.
- **SC-004**: `validate-and-sync` i18n vert (aucune clé ajoutée, FAQ seulement).

## Assumptions

- Le plan Free reste actif côté backend (PlanSeeder, `is_active: true`).
- Le parcours d'inscription réel reste l'essai guidé 14 jours sans carte
  (décision D-E4-01) ; le plan Free est le palier post-essai des petites équipes.
- Pas de changement côté checkout API : `free` reste un alias doux vers Pilot
  (aucune facturation 0 € n'existe côté paiement).
