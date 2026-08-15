# Rapport QA Expert — Leopardo HR — Session 2026-08-15

## Mission
Tester la plateforme dans tous les sens (vitrine, web app, admin, mobiles, workflows,
API, logiques, onboarding, cohérence) → chaque manquement devient un objet Spec Kit
(issue GitHub + `.specify/features/<name>/{spec,plan,tasks}.md`) → implémenter les
manquements → traiter le backlog d'issues ouvertes → merger le max de branches → main vert.

**Contexte** : repo public kitokoh/leopardo-hr. Un swarm de ~9 sessions QA parallèles
(agent + experts 1→9) travaillait en continu : main bougeait toutes les ~5 min, la CI
était saturée (#2488, file de 90+ runs, annulations en cascade). Aucun PHP/Flutter local
→ CI GitHub Actions = source de vérité backend/mobile ; Node dispo → builds/lints web/admin.

---

## 1. Constats NOUVEAUX (vagues expert 6) — 12 issues créées

Revue statique experte par 4 scouts parallèles (API, vitrine/web, admin, mobile) + gardes
repo, avec vérification anti-doublon sur les ~210 issues ouvertes au moment du scan.

| Issue | Surface | Sev | Constat | Résolution |
|---|---|---|---|---|
| **#3427** | api | **P1** | Bootstrapping Edge node par tout employé → edge_token + pull PII/biométrie (face_encoding, biometric_id) de toute l'entreprise | **PR #3444 MERGED** |
| **#3428** | api | P2 | CameraPermission : employee_id cible non scoped tenant (FK cross-tenant) | **PR #3447 MERGED** |
| **#3429** | api | P3 | SalaryAdvance markPaid TOCTOU → double ledger + double document | **PR #3450 MERGED** |
| **#3430** | api | P3 | Onboarding complete/skip par tout employé (état company-level falsifiable) | Arbitrage demandé (conflit avec test T118 codifié) — commentaire #3430 |
| **#3431** | mobile | P2 | Statut avance `disputed` sans libellé mobile (impasse métier) | Corrigé sur main (vagues 7/8) — fermée avec preuve |
| **#3432** | mobile | P2 | Contract.fromJson : start_date nullable → TypeError liste contrats | Corrigé sur main — fermée |
| **#3433** | mobile | P3 | DateTime.parse non gardés (attendance_log, payroll, project_task) | Corrigé sur main — fermée |
| **#3434** | web | P2 | FAQ TR « 30 tam gün » (résidu essai 14j) | Fix déjà sur main — PR fermée (anti-doublon) |
| **#3435** | web | P2 | 12 pages /case-studies/[slug] sans metadata ni sitemap | PR #3483 (swarm) — fermée |
| **#3436** | admin | P3 | PayrollView : 3 exports CSV sans anti-injection de formule | Fix déjà sur main — PR fermée (anti-doublon) |
| **#3437** | admin | P3 | Libellés de features bruts (BIOMETRIC, LEO_AI, MUHASEBE) | **PR #3465 MERGED** |
| **#3205** | mobile | **P1** | Régression #2212 : manifeste sert 11 routes absentes du routeur leopardo_manager (introduit par #3117) → CI rouge + GoError modules | **PR #3209 MERGED** |

## 2. Constats implémentés depuis le backlog (issues ouvertes existantes)

| Issue | Surface | Fix | PR |
|---|---|---|---|
| #3487 | web | Meta description /pricing localisée via ?lang= (la locale était forcée en FR) | #3526 (CI) |
| #3485 | web | SSO SAML/OIDC vendu comme inclus mais coming_soon → « bientôt disponible » ×4 locales | **#3547 MERGED** |
| #3497 | api | Throttles manquants sur callbacks SSO publics (XML non authentifié) | #3556 (CI) |
| #3488 | web | MiniCaseStudies : chiffres fictifs (-40%/-35%/+60%) → disclaimer 4 locales | **#3558 MERGED** |
| #3523 | web | Proxy /api/v1 sans try/catch → 502 JSON contrôlé + timeout 15 s | #3569 (CI) |
| #3520/#3521 | sec | Credentials démo en clair (Postman + smoke script password123) → variables + échec explicite | #3572 (CI) |
| #3565 | mobile | sync_models_example.dart (12 print debug) dans le package partagé → supprimé | #3575 (CI) |
| #3496 | api | trial_days Enterprise=30 vs 14 | Fermée avec preuve code (déjà fixé #3516) |

## 3. Tests/checks effectués sur main

| Check | Résultat |
|---|---|
| Garde manifeste mobile (check-mobile-manifest-routes) | ROUGE → **réparée par #3209** ✅ |
| OpenAPI route coverage | 552/720 couvertes, **0 drift nouveau** ✅ |
| Migrations collisions | ✅ (fix swarm #3200) |
| Catalogue pays | ✅ 19 codes |
| Parité .env.example (272 clés) | ✅ |
| Contrôleurs/interfaces orphelins | ✅ 0 nouveau |
| Build vitrine Next.js + lint | ✅ 0 erreur |
| Build admin Vue + lint | ✅ (4 warnings non bloquants, signalés) |
| Test schema drift | 44 tables hors CreatesMvpSchema — dette connue #1489/#1586 |
| i18n debt | 8 987 (P1 2 724 / P2 6 263) — dette mesurée |

## 4. Bilan merge campaign

- **9 PRs mergées** (issues : #3205, #3427, #3428, #3429, #3437, #3485, #3488 + swarm).
- **4 PRs fermées anti-doublon** (#3459, #3462, #3482, #3478) avec renvoi — contenu déjà sur main.
- **5 PRs en attente CI** au moment du rapport : #3526, #3556, #3569, #3572, #3575
  (file CI saturée par le swarm ; toutes sans conflit, checks requis en attente).
- Main : **5 checks requis verts** (Backend Coverage, PHPStan Strict, Module Structure,
  Frontend ESLint+TS, actionlint) ; seule anomalie = rate-limit Vercel (infra, non bloquant).

## 5. Spec Kit (méthode demandée)

- `.specify/features/3205-manifest-manager-routes/{spec,plan,tasks}.md`
- `.specify/features/qa-expert6-wave-2026-08-15/{spec,plan,tasks}.md`
- Issues créées avec le label `QA` + sévérité + surface, corps structuré
  (constat → preuve fichier:ligne → impact → attendu → tests d'acceptation).

## 6. Points nécessitant décision propriétaire

1. **#3430** — Onboarding : T118 (test codifié) autorise tout employé à écrire les steps
   company-level vs risque de falsification. Arbitrage : steps par employé ou par entreprise ?
2. **#3452** — Vitrine DOWN : leopardo-rh.com NXDOMAIN (issue swarm, P1 ops) — DNS à rétablir.
3. **Essai 14 vs 30 j** : l'arbitrage 14 j (PRs #2944/#3135/#3516) a été contesté par #3343
   (30 j) puis re-corrigé — main est à 14 j via `config('billing.trial_days')` ; verrouiller la décision.
