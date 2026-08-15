# Feature Specification: Aligner les CTA de conversion et les E2E signup avec l'i18n (issue #3834)

**Feature Branch**: `fix/3834-cta-e2e-i18n`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: QA E2E campagne front Web — la suite `e2e/conversion-funnel.spec.ts` et
`e2e/client-feature-gates.spec.ts` échouent : les sélecteurs texte mono-locale
(`Essai gratuit`, `Get Started`) ne correspondent plus aux libellés réels
(`Start now`, `Commencer`, `Hemen baslayin`, `ابدأ الآن`), et les messages de
feature gate attendus divergent des catalogues localisés.

## Problème

Deux classes de désynchronisation entre le contrat de test E2E, l'i18n et le
rendu applicatif :

1. **CTA de conversion** : les tests cherchent des libellés FR rigides
   (`button:has-text("Essai gratuit")`) alors que le rendu utilise des
   libellés localisés par section/locale. Résultat : tests flaky/rouges sur
   les parcours signup, sans qu'une régression produit soit détectable.
2. **Feature gates** : `FeatureLockedPanel` mélange des chaînes localisées
   (via `labels.dashboard.featureLocked*`) et du FR en dur
   (« Demandez l activation au super administrateur de la plateforme… »,
   « Plan & role », « Les modules visibles dans cet espace… »). Les tests
   attendent un texte sans apostrophe (`n est pas inclus`) alors que le
   catalogue rend `n'est pas inclus` — mismatch exact.

## User Scenarios & Testing

### User Story 1 — Les CTA de conversion sont testés par un contrat stable (Priority: P1)

Un testeur (ou la CI) doit pouvoir exercer les parcours signup/demo/contact
quelle que soit la locale rendue.

**Independent Test**: `npx playwright test e2e/conversion-funnel.spec.ts --project=chromium` → 100 % vert.

**Acceptance Scenarios**:

1. **Given** la landing page (toutes locales), **When** le test cherche le CTA
   signup, **Then** il le trouve via un sélecteur stable
   (`data-testid="cta-signup"` ou `a[href="/signup"]`), pas un texte rigide.
2. **Given** la page `/signup`, **When** le formulaire guidé soumet un email
   invalide, **Then** une erreur de validation lisible s'affiche (testé).
3. **Given** la page `/signup`, **When** le test vérifie l'absence de mot de
   passe, **Then** `input[type="password"]` reste absent (parcours guidé).

### User Story 2 — Les messages de feature gate sont localisés et testés (Priority: P1)

Un manager dont un module est verrouillé voit un message explicite, localisé
dans les 4 locales, sans FR en dur.

**Independent Test**: `npx playwright test e2e/client-feature-gates.spec.ts --project=chromium` → 100 % vert ; grep du panel sans chaîne FR en dur.

**Acceptance Scenarios**:

1. **Given** un utilisateur sans module payroll, **When** il ouvre `/payroll`,
   **Then** le badge « Module non inclus » et le message de plan s'affichent
   depuis les catalogues (aucune chaîne FR codée en dur dans le composant).
2. **Given** un employé (rôle non autorisé), **When** il ouvre `/payroll`,
   **Then** le message « rôle » localisé s'affiche et l'événement
   `feature_blocked` (reason `role_locked`) est émis.
3. **Given** les 4 locales, **When** on rend le panneau verrouillé, **Then**
   chaque libellé provient de `labels.dashboard.featureLocked*`.

## Edge Cases

- Les apostrophes françaises : le catalogue utilise `n'est` ; les tests ne
  doivent pas coder un texte sans apostrophe (mismatch historique).
- Le `getByText` ne matche pas les `aria-label` : le badge de verrouillage
  doit être un vrai texte visible ou être sélectionné via un sélecteur stable.
- Les CTA localisés (`Start now`, `Hemen baslayin`, `ابدأ الآن`) doivent
  rester libres de changer sans casser la CI : le contrat de test porte sur
  la cible (`href`) ou un `data-testid`, jamais sur le libellé.

## Requirements

### Functional Requirements

- **FR-001**: Les tests E2E de conversion sélectionnent les CTA via `href`,
  `data-testid` ou `getByRole` — jamais un texte mono-locale.
- **FR-002**: `FeatureLockedPanel` ne contient plus de chaîne FR en dur :
  toutes les chaînes visibles passent par `labels.dashboard.featureLocked*`
  (4 locales).
- **FR-003**: Les tests feature-gates matchent les textes réellement rendus
  par les catalogues (apostrophes incluses) ou utilisent des sélecteurs
  stables.
- **FR-004**: `CI=1 pnpm exec playwright test`, `pnpm jest --runInBand`,
  `pnpm lint` et `pnpm build` restent verts (aucun timeout artificiel, aucun
  scénario ignoré).

### Key Entities

- `data-testid` sur les CTA signup/demo/contact de la vitrine (contrat de
  test stable).
- `labels.dashboard.featureLocked*` dans `front/web/src/lib/i18n.ts`
  (4 locales).

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100 % des tests Playwright vitrine passent sur chromium.
- **SC-002**: 0 chaîne FR en dur dans `FeatureLockedPanel`.
- **SC-003**: `pnpm lint` (ESLint, max-warnings 0) et `pnpm build` verts.

## Assumptions

- Le parcours signup reste un essai guidé sans mot de passe (v4.16.250).
- La suite E2E complète s'exécute sur chromium en CI (projet unique).
