# Tasks — #3834 CTA / E2E i18n

## T1 — Catalogues i18n
- [x] Ajouter les 4 clés `featureLockedAdminHint`, `featureLockedPlanRoleTitle`,
      `featureLockedPlanRoleBody`, `featureLockedCta` dans `lib/i18n.ts`
      (fr/en/tr/ar + type `dashboard`).
- [ ] Vérifier `check:mojibake` (aucune séquence mojibake introduite).

## T2 — Composant FeatureLockedPanel (`app/(dashboard)/layout.tsx`)
- [x] Remplacer les 4 chaînes FR en dur par `labels.dashboard.featureLocked*`.
- [x] `data-testid="feature-locked-panel"` sur la `<section>`.
- [x] `aria-label` du cadenas sidebar → `labels.dashboard.featureLockedBadge`.

## T3 — Tests E2E feature-gates
- [x] `'n est pas inclus'` → `"n'est pas inclus"` (catalogue réel).
- [x] `'d acceder'` → `"d'acceder"` (catalogue réel).
- [x] Assertions de visibilité via `getByTestId('feature-locked-panel')`.

## T4 — Tests E2E conversion-funnel
- [x] Helpers `signupCta`/`demoCta` (`a[href^=...]`, robustes `?lang=`/`?source=`).
- [x] Demo + contact : `Promise.all([waitForURL, click])`, timeout dev honnête.

## T5 — Validation
- [x] `npx tsc --noEmit` → 0 erreur.
- [x] `npm run lint` (max-warnings 0) → 0 problème.
- [ ] Playwright chromium : `client-feature-gates` vert.
- [ ] Playwright chromium : `conversion-funnel` vert (dépend du cold compile).

## T6 — Livraison
- [x] Entrée CHANGELOG (Closes #3834).
- [ ] PR `fix/3834-cta-e2e-i18n` → `main`, `Closes #3834` dans le body.
