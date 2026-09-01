# Registre des constats — QA runtime expert 10 — 2026-08-15

Méthode : preuve par l'exécution (builds, gardes rejouées, sondes live, merge-tests locaux).
Convention : ✅ sain · 🔴 action requise · 🟠 suivi ailleurs.

| ID | Sévérité | Surface | Constat | Statut |
|----|----------|---------|---------|--------|
| F-01 | P1 | API/CI | PHPStan strict : 33 erreurs sur main (TrialWelcomeMail ×3 ternaires/comparaisons toujours vraies ; KioskController ×8 dont `$kiosk` indéfini l.157 ; 22 tests Feature `assign.propertyType` Model→Company/Employee + types itérables manquants) | 🟠 baseline régénérée poussée sur branche canonique #3515 ; dette de fond déjà tracée #3158 |
| F-02 | P1 | Ops | API prod Render périmée : `/health` version 4.23.5 (main 4.24.0), `queue.driver=sync`, `redis=skipped` — jobs « async » exécutés en cycle requête (constitution §IX non tenable) | 🟠 preuve commentée sur #2627 (déploiement Render à relancer, clés incluses) |
| F-03 | P1 | Ops | `leopardo-rh.com` NXDOMAIN (A/NS vides) — acquisition 0 sur le domaine canonique | 🟠 confirmé, action propriétaire — #3452 |
| F-04 | P2 | API | `APP_VERSION` `.env.example` (4.23.5) divergent du défaut `config/app.php` (4.24.0) — version rapportée fausse si l'env n'est pas surchargée | ✅ PR #3579 (Closes #3528) + garde CI `check-app-version-sync.sh` |
| F-05 | P2 | Backlog | Issues corrigées mais restées ouvertes faute de `Closes #` (règle #2512) | ✅ #3340, #3443 fermées avec preuve ; #3324/#3436 vérifiées closes à juste titre |
| F-06 | P2 | Swarm | PRs absorbées par des merges parallèles restées ouvertes (diffs vides après résolution) | ✅ #3426, #3448, #3408, #3462 fermées avec renvoi canonique |
| F-07 | P2 | Swarm | Doublon 1-issue-2-PRs sur #3435 (créées à 1 min d'intervalle) | ✅ #3482 fermée, #3483 canonique dé-conflictée |
| F-08 | P3 | Admin | ESLint admin : 2 warnings résiduels (`no-unused-vars`, ex. `formatDate` inutilisé) | 🟠 non bloquant (lint gate = errors only côté web) ; laissé au swarm |
| F-09 | — | Web | Vitrine : lint 0/0, build vert, 13 routes live 200, headers sécu sains (CSP report-only = décision #1607) | ✅ rien à ouvrir |
| F-10 | — | API | OpenAPI coverage 0 drift ; migrations 0 collision ; catalogue pays OK ; orphelins 0 nouveau | ✅ rien à ouvrir |
| F-11 | — | Mobile | Anti-patterns carte agent : 0 occurrence (withOpacity, dio direct, await-runApp, Firebase gardé via `_ensureFirebaseInitialized` + timeouts) | ✅ rien à ouvrir |
| F-12 | — | API | `GET /api/v1/plans` 404 public = comportement voulu (plans plateforme = super-admin `/platform/plans`) | ✅ pas une fuite — vérifié |
