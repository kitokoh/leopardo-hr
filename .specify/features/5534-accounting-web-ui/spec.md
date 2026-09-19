# Feature Specification: Module Comptabilité — interface web du rôle comptable (Closes #5534)

**Feature Branch**: `bc/bc10-accounting-web-ui` (reprise #7776 — branche initiale `fix/5534-accounting-web-ui`)
**Created**: 2026-08-25 | **Status**: Delivered (2026-09-19, #7776 — console admin Vue `front/admin-dashboard`)
**Issue**: #5534 (P1, web, ux, backend) — rouverte par #7776 (ghost close)
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

- [x] Le comptable fait le cycle mensuel (clôture exercice, lettrage, FEC) sans l'API — 7 écrans livrés dans `front/admin-dashboard/src/views/accounting/` (#7776) : documents/factures, plan comptable, grand livre/journal (+ balance + FEC), lettrage, exercices & clôture, banque/rapprochement, TVA & états financiers
- [x] i18n ×4 — clés `accountingModule.*` (106 existantes + ≈105 nouvelles : documents, journal, TVA, import bancaire) et `bankRecon.*`, propagées par les synchroniseurs
- [ ] Tests Jest — pas de harnais Jest/Vitest sur `front/admin-dashboard` (main) ; à livrer avec le harnais (`bc/bc01-admin-web-plateforme`)
- [x] CHANGELOG + spec
