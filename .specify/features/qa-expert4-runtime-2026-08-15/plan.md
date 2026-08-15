# Plan: Session QA Expert 4 — Runtime & Merge Campaign (2026-08-15)

## Contexte technique

- Monorepo : `api/` Laravel 12 (modules DDD), `front/web` Next.js 15 (vitrine + portail),
  `front/admin-dashboard` Vue 3, `front/mobile_apps/` Flutter ×6 (core, employee, manager,
  hr, platform_admin, marketing), `front/zkteco-kiosk`.
- CI : ~40 workflows GitHub Actions ; branch protection main = 5 checks requis
  (Backend Coverage, PHPStan Strict L8, Module Structure, ESLint+TS, actionlint).
- Spec Kit : `.specify/` (constitution, features, skills) — workflow specify→plan→tasks→implement.
- Contrainte sandbox : pas de PHP/Flutter/Postgres local (Node ✅, PHP CLI installé pour
  syntax-check) → GitHub Actions = source de vérité (AGENTS.md), builds Next/Vue locaux.

## Stratégie

1. **Merge campaign** (priorité haute, effet immédiat) :
   - Boucle automatique : pour chaque PR ouverte, si les 5 checks requis sont verts et
     mergeable_state ∈ {clean, behind, unstable} → merge + delete branch (API).
   - Branches orphelines → analyse du diff, CHANGELOG si absent, PR créée.
   - Doublons (même issue, 2 PRs) → fermeture avec commentaire de renvoi (protocole #2400).
   - Conflits → merge origin/main dans la branche + résolution manuelle vérifiée.
   - File CI → `cancel-orphan-runs.sh --superseded` (outil officiel #2413).
2. **Tests** : checkers statiques du repo (OpenAPI, migrations, i18n, manifest routes,
   pays, env parity, orphelins, mojibake) + builds locaux Next/Vue + black-box staging.
3. **Consignation** : findings-registry + spec/plan/tasks + doc session + issues dédupliquées.
4. **Implémentation** : issues non assignées avec critères clairs (anti-doublon vérifié),
   une PR = un cluster d'issues cohérent, `Closes #N` dans le body, CHANGELOG sous
   `## [Unreleased]`.

## Risques & mitigations

| Risque | Mitigation |
|--------|-----------|
| Swarm merge en parallèle (branches bougent vite) | Fetch fréquent, merge origin/main, `--force-with-lease` jamais sur branches partagées, résolutions conservatrices |
| Régressions par écrasement (leçon 2026-08-15) | Vérif `git diff origin/main...HEAD` sur fichiers partagés avant push |
| Checks CI saturés (queued/cancelled) | Nettoyage file + merge uniquement quand checks verts |
| Conflits Flutter non compilables localement | Import-check + CI flutter analyze comme garde |
| Token révoqué en fin de session | Jamais persisté hors env ; rapport final complet |
