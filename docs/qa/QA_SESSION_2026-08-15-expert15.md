# QA Leopardo RH — Session expert #15 du 2026-08-15 (audit 360° + backlog)

Mission : audit 360° + création d'issues spec-kit, nettoyage du backlog (PRs/branches ouvertes), implémentation des constats d'audit. Contexte : ~15 agents QA concurrents sur le même repo, main bouge ~1 merge/min, CI saturée.

## Phase 1 — Audit 360° & gap analysis

- **Méthode** : subagent scout = relecture croisée des 17 rapports `docs/qa/` (audit-expert5 ×4 surfaces + sessions) vs issues/PRs ouvertes vs code sur `main` → table de couverture + 21 GAPS vérifiés dans le code + 8 clusters de doublons.
- **Issues créées (13 gaps + 4 hygiène)** : #3857 BulkPay fail-open Redis (P1 money), #3858 Header search `useRouter()` hors setup (P1), #3859 Partner payout (P1→P3 après vérif), #3860 ATS unique index, #3861 coquille admin i18n, #3862 zone_enter perdu, #3863 case-studies fictifs, #3864 inbox notifs super-admin 401, #3865 cluster UX admin, #3866 code mort api, #3867 cluster qualité mobile, #3868 % annuel faux + checklist 2024, #3869 BelongsToCompany null-guard, #3870-#3873 consolidation doublons (OpenAPI ×3, prod-stale ×3, i18n ×4, sitemap /blog).

## Phase 2 — Nettoyage backlog

- **Deadlock main rouge levé** : les 4 correctifs complémentaires (#3791 PHPStan code, #3802 vitest, #3813 baselines, #3815 i18n+OpenAPI) ont été mergés par les agents concurrents pendant la session (vérifié) → issue #3840 créée puis close.
- **PRs pré-existantes** : les ~27 PRs ouvertes en début de session ont toutes été mergées par l'équipe (validation + CI).
- **Main rouge récurrent (2e occurrence)** : merge #3992 (skip-link) a modifié le catalogue source `shared/i18n/locales/*` sans régénérer cibles + `versions.json` → checksum fr/en/tr KO → validate-and-sync rouge sur main et toutes PRs. Correctif : issue #4017 + PR #4020 (resync complet, `I18N_VALIDATION_OK`). **Leçon : toute PR touchant `shared/i18n/locales/*` DOIT régénérer cibles + versions.json (3e occurrence de la classe — voir leçons).**

## Phase 3 — Implémentations livrées par cet agent

| PR | Issue | Contenu | Statut |
|---|---|---|---|
| #3969 | #3863 | Case-studies : bandeau démo + badge + suppression « 500+ entreprises » + coquille localisée (vitrine-locale) | MERGED |
| #3990 | #3868 | Badge annuel 20%→« jusqu'à 17% » (vrai max 17,24%) via catalogue + Checklist Paie 2024 → édition courante (PDF régénéré) | MERGED |
| #3998 | #3865 | Admin UX : export groupé respecte la sélection ; exports tenant → état honnête « espace client » ; Ctrl+K single-binding | MERGED |
| #4011 | #3857 | BulkPay fail-closed : 503 si Redis down (plus de dispatch sans claim) ; job aborte le lot (RuntimeException) sans marquer payé ; tests | MERGED |
| #4020 | #4017 | Resync i18n cibles + versions.json (main vert) | MERGED |
| #4028 | #3859 | Payout : classe morte `RequestPayout` supprimée (le chemin live était déjà protégé #2999) + transitions gardées (allowlist) + test | open |
| #4029 | #3866 | `TrainingController::indexSessionsAll` mort supprimé (markPaid N+1 déjà corrigé #3429) | open |
| #4046 | #3861 | Coquille admin i18n : document.title via `navigation.*` + Header via `$t('shell.*')`, catalogue source enrichi + régénéré | open |

## Constats vérifiés (corrigent des audits antérieurs)

- **#3859** : le « sur-paiement partenaire » pointait une classe MORTE (`Growth/Application/Actions/RequestPayout.php`) — le chemin live est protégé (lockForUpdate + solde, #2999, testé). P1→P3 effectif, commentaire posté sur l'issue.
- **#3866** : `indexSessionsAll` mort confirmé (route → `indexAllSessions`) ; `markPaid` N+1 déjà corrigé (#3429).

## Leçons pour les prochains agents

1. **`shared/i18n/locales/*` = source de vérité** : toute modification de cibles générées (admin `src/i18n/locales`, mobile `app_*.arb`) SANS passer par le source + sync = main rouge (validate-and-sync). Toujours : modifier le source → `node shared/i18n/sync/{sync-mobile,sync-web,sync-backend}.js` → valider → committer les 3 (source, cibles, versions.json).
2. **check-i18n-diff.js** : les littéraux ajoutés dans `src/app/**`, `front/admin-dashboard/src/**` sont flaggés SAUF via `$t('clé', fallback)` / `translate()` sans fallback littéral (le fallback littéral dans `translate()` est flaggé — pattern non reconnu) / fichiers ignorés (`vitrine-locale.ts`, `vitrine/data`, `seo.ts`). Une clé `'navigation.x'` = token technique → OK.
3. **Pushes** : `git push <url-avec-token>` ne met PAS à jour les refs de suivi `origin/*` → un `git reset --hard origin/<branche>` échoue silencieusement. Toujours vérifier `gh pr view <n> --json merged` avant tout force-push. Voir `memory/wiki/git-force-push-clobber.md`.
4. **Vérifier les audits avant d'implémenter** : 2 findings P1/P3 des rapports expert5 pointaient du code mort/déjà corrigé — `grep` des appelants + routes AVANT d'écrire l'issue.
5. **CI saturée** : les checks restent « queued » longtemps ; les PRs vertes sont mergées par d'autres agents rapidement — re-vérifier l'état AVANT de retravailler une branche.
