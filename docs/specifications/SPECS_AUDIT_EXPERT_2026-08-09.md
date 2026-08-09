# Spécifications — Audit Expert 2026-08-09

> Issues créées à partir de l'audit (voir `docs/audits/AUDIT_EXPERT_2026-08-09.md`). Chaque spec est autonome : contexte, périmètre, critères d'acceptation.

## S-1 — [Security][P1] Biométrie : politique de rétention + purge automatique (RGPD, #1548 suite)

**Contexte** : la politique de rétention documentaire existe (`docs/security/POLITIQUE_RETENTION_DOCUMENTS.md`) mais aucune durée ni purge automatique des **templates biométriques** (kiosk/mobile) n'est implémentée. Les chemins de référence (`biometric_*_reference_path`, `biometric_enrollment_requests.*_reference_path`) peuvent rester indéfiniment.

**Périmètre**
1. Étendre la politique : durée de conservation des templates (ex. 24 mois après fin de contrat, ou N mois après dernier usage), base légale (consentement Loi 18-07).
2. Commande `biometric:purge-expired` : nullifie/efface les références dont la date de consentement/dernier usage dépasse la durée, par tenant, tracée dans `audit_logs`.
3. Tests de la commande (purgé / non-purgé / idempotent).

**Critères d'acceptation** : politique mise à jour ; commande testée ; opérations tracées ; dry-run disponible.

## S-2 — [Security][P2] Journalisation des accès aux données sensibles (paie, exports, bulletins)

**Contexte** : `DataAccessAuditLogger` existe mais seule l'anonymisation RGPD est tracée. Les lectures sensibles (téléchargement de bulletins, exports, end-of-contract, journal) ne sont pas journalisées.

**Périmètre**
1. Tracer les accès en lecture aux ressources sensibles (pay-slips, exports, journal, certificat) via `DataAccessAuditLogger` (qui/quoi/quand), sans exploser le volume (échantillonnage configurable).
2. Rapport périodique optionnel (commande `audit:sensitive-report`).

**Critères d'acceptation** : un test par ressource sensible ; volume borné (config) ; pas de régression perf (benchmark F-12).

## S-3 — [Backend][P1] Durcissement paie : NOT NULL effectivity, erreurs visibles, migrations additives

**Contexte** : trois fragilités relevées en audit : (a) `social_contributions.effective_from` nullable → 500 potentiel ; (b) `safeEmployeeBalance()` avale les exceptions et renvoie des valeurs vides au client ; (c) les migrations `2026_06_29_000205` et `2026_05_18_000003` ont été modifiées rétroactivement (les env déjà migrés ne rejoueront pas le nouveau code) ; (d) `public/0001` pose `SET search_path TO public` (fragilité current_schema).

**Périmètre**
1. Migration additive : `effective_from` NOT NULL (avec backfill) + validation `after:effective_from` au store.
2. `safeEmployeeBalance` : propager l'erreur (500 explicite + Log) au lieu de valeurs vides ; le rapport d'anomalies reste lisible.
3. Migrations **additives** pour le fix geo_auto (000205) et company_id UUID (000003) au lieu de réécrire l'existant ; commentaire dans les fichiers d'origine.
4. Supprimer/scoper le `SET search_path TO public` de `public/0001` (harmoniser phpunit/CI).

**Critères d'acceptation** : CI verte (migrate:fresh + RefreshTenantDatabase) ; aucun 500 silencieux ; geo_auto OK sur env neuf ET déjà migré.

## S-4 — [Qualité][P1] Couverture Payroll ≥ 80 % + gate bloquante (F-14, #1602)

**Contexte** : gate advisory à ~45 % ; le noyau paie est le cœur produit (conformité DZ).

**Périmètre**
1. Plan par fichier (rapport F-13 comme base) : prioriser PayrollCalculator, PayrollClosingService, EndOfContractService, PayrollAnomalyService, PayrollJournalGenerator.
2. Ajouter les golden/feature manquants (scénarios F-07/F-08/F-09/F-10/F-11/F-20).
3. Passer la gate `payroll-ci.yml` en bloquante à ≥ 80 %.

**Critères d'acceptation** : coverage ≥ 80 % mesuré par la gate ; gate bloquante (continue-on-error retiré) ; 3 runs verts consécutifs.

## S-5 — [i18n][P2] Compléter l'internationalisation (PA2-I18N-006/008/009/010/012 + turc)

**Contexte** : emails transactionnels non localisés (0 `__()`), littéraux mobile employee/manager, web Next.js, admin-dashboard, dates/devises codées `fr-FR`, qualité turque vitrine.

**Périmètre**
1. Localiser les 6 emails restants (welcome, welcome-employee, welcome-onboarding, trial-welcome, subscription-confirmed, password-reset) sur les 4 locales.
2. Extraire les littéraux mobile employee (priorité) puis manager ; brancher le catalogue existant.
3. Dates/devises par locale active (intl) — 30 occurrences `fr-FR` à remplacer.
4. Passe qualité turque sur la vitrine (i18n-enterprise gate étendue).

**Critères d'acceptation** : `check-i18n-diff.js` vert ; 0 littéral bloquant restant sur employee ; emails 4 locales ; TR validé par revue humaine.

## S-6 — [Frontend][P2] Accessibilité admin (WCAG W6) + parité widgets platform_admin

**Contexte** : aucun `aria-invalid`/`aria-describedby` dans admin-dashboard ; platform_admin n'utilise pas les widgets partagés (LeopardoBadge/LeopardoQrCard).

**Périmètre**
1. Composant `FormField.vue` : label + erreur inline (`aria-invalid`, `aria-describedby`, `role=alert`) ; migration des formulaires critiques (login, onboarding, employés).
2. Adoption des widgets partagés par platform_admin (dashboard, kiosk).
3. Audit WCAG rapide rejoué (lighthouse CI).

**Critères d'acceptation** : 0 champ de formulaire critique sans feedback inline ; lighthouse a11y ≥ 95 ; widgets partagés adoptés.

## S-7 — [Process][P3] Reconciliation du backlog atomique + fermeture des tickets livrés

**Contexte** : 106 tickets affichés ouverts mais livrés ; `02_BACKLOG_ATOMIQUE.md` (~1000+ lignes) non réécrit.

**Périmètre**
1. Réécrire `docs/archive/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md` : statut réel (fait/à faire) par ticket, avec référence PR/commit.
2. Fermer les issues GitHub livrées (vérification code avant fermeture).

**Critères d'acceptation** : backlog à jour (1 ligne/ticket) ; issues fermées avec référence ; zéro ticket « livré mais ouvert » restant.
