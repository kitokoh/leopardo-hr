# Feature Specification: Module Comptabilité — interface web du rôle comptable (Closes #5534)

**Feature Branch**: `fix/5534-accounting-web-ui`
**Created**: 2026-08-25 | **Status**: In progress
**Issue**: #5534 (P1, web, ux, backend)
**Spec**: `.specify/features/5534-accounting-web-ui/spec.md`
**Anti-collision**: backend #5422/#5525 livré (13 routes, 0 UI) ; complète #5539 (wizard) ; la page partages (#5522) sera intégrée dans la section document.

## Contexte

La profondeur comptable de production est 100 % backend : le rôle comptable n'a AUCUN écran (l'expert-comptable doit appeler l'API à la main). Cette spec livre la section « Comptabilité » du dashboard web.

## User Stories & Testing

### US-1 — Accueil module (P1)
Given rôle principal/comptable, When GET `/accounting`, Then hub de navigation vers les 7 écrans.

### US-2 — Plan comptable (P1)
1. Given GET /accounting/chart, Then liste (code, libellé, type, classe, état) + filtre par type.
2. When création (POST /accounting/chart), Then compte ajouté (code numérique, label, type, classe 1-8).
3. Given compte système, Then suppression masquée (désactivation seule) ; compte libre → DELETE.
4. When toggle, Then PUT /accounting/chart/{code} {is_active}.

### US-3 — Grand livre + Balance (P1)
1. Given période AAAA-MM, Then écritures avec running balance + solde d'ouverture (+ filtre compte).
2. Given GET /accounting/balance, Then totaux par compte + totaux généraux + indicateur d'équilibre.
3. When bouton FEC, Then téléchargement /accounting/journal/export-fec?period=.

### US-4 — États financiers (P1)
1. Given année, Then bilan par sections PCG + invariant actif = passif + capitaux affiché.
2. Given période, Then compte de résultat (produits, charges, résultat net).

### US-5 — Exercices (P1)
1. Given liste, Then statuts ouvert/clôturé.
2. When ouverture, Then POST /accounting/fiscal-years {year}.
3. When clôture, Then dialog de confirmation (irréversible) → POST /accounting/fiscal-years/{year}/close.

### US-6 — Lettrage (P1)
1. Given journal de période, Then sélection multi-écritures (checkbox) + lettre.
2. When ≥ 2 sélectionnées + lettre, Then POST /accounting/journal/lettering.
3. When < 2 sélectionnées, Then message d'erreur, aucun appel.
4. When période clôturée, Then lecture seule ; délettrage via DELETE /journal/lettering/{letter}.

## Requirements

- FR-1 : 8 pages Next.js `(dashboard)/accounting/*` (home, chart, ledger, balance, statements, fiscal-years, lettering, fec), RBAC côté backend.
- FR-2 : i18n ×4 namespace `accountingModule.*` (106 clés) + `accountingActivation.*` (23 clés, #5539), validateur vert.
- FR-3 : Jest : chart (4), fiscal-years (3), lettering (3), activation (5), partages (5).
- FR-4 : CHANGELOG + cette spec.

## DoD

- [ ] Le comptable fait le cycle mensuel (clôture exercice, lettrage, FEC) sans l'API
- [ ] i18n ×4
- [ ] Tests Jest
- [ ] CHANGELOG + spec
