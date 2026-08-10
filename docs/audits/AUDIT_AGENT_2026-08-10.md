# Audit Agent — 2026-08-10 (session : implémentation S-1→S-7 + vérification audits antérieurs)

> Agent autonome, session du 2026-08-10. Méthode : chaque point vérifié contre le
> code réel et/ou les tests exécutés localement (PHP 8.4 + PostgreSQL 16 + pcov),
> jamais contre les dires des rapports précédents. Contexte : le dépôt est public,
> poussé par `kitokoh` et par un bot d'automatisation (`leopardo-hr bot`).

## 1. Issues ouvertes au début de session — état

| Issue | Spec | État en fin de session |
|---|---|---|
| #1661 | S-1 Biométrie rétention/purge | Implémentée (PR #1668) — commande + politique v2 + tests 7/7 verts localement |
| #1662 | S-2 Journalisation accès sensibles | Implémentée (PR #1674) — DataAccessAuditLogger étendu + commande rapport + tests 10/10 verts localement |
| #1663 | S-3 Durcissement paie | Implémentée (PR #1669) — migrations additives + 500 explicite + validation |
| #1664 | S-4 Coverage Payroll ≥ 80 % | PR #1676 (bot) + PR S-4 (agent) — gate bloquante + tests ; mesure en cours |
| #1665 | S-5 i18n | PR #1677 (bot) — emails/littéraux/dates |
| #1666 | S-6 A11y admin + widgets | PR #1675 (bot) |
| #1667 | S-7 Backlog reconciliation | PR #1678 (bot) |
| #1472 | P0 Action humaine | Toujours ouverte — rotation Redis Upstash + purge historique git (runbook prêt) |

## 2. Vérification des audits antérieurs — points non implémentés avant cette session

Vérifié sur main (bdc53395) + re-vérifié après correctifs.

| Point | Origine | Statut réel avant | Statut après session |
|---|---|---|---|
| `employees.position` utilisé par un test (colonne inexistante) | latent main | ❌ `EndOfContractApiTest` rouge si la suite tourne | ✅ corrigé (position → position_id, PR S-3/S-4) |
| `SET search_path TO public` dans public/0001 | R-2 audit expert 08-09 | ❌ | ✅ retiré (S-3) |
| Clé dupliquée `/payroll-runs/{payrollRun}/journal` dans openapi.yaml | pré-existant main | ❌ redocly rouge sur toute PR `api/routes/**` | ✅ retirée + SDK régénérés (S-1) |
| `biometric:purge-expired` closure `DB::transaction` sans `$months/$cutoff` | S-1 (nouveau) | — | ✅ corrigé (bug runtime réel) |
| Composer audit (CVE dompdf) | AUDIT_GLOBAL 07-26 | ✅ déjà résolu | ✅ confirmé (0 advisory) |
| Steps morts `mobile_*` dans coverage-gate | AUDIT_CICD 07-19 | ✅ déjà résolu | ✅ confirmé |
| Rotation Redis + purge git | AUDIT 07-01 / #1472 | ❌ action humaine | ❌ toujours action humaine |
| Secret Neon dans l'historique | #1601 | ❌ action humaine | ❌ toujours action humaine |
| Couverture Payroll ≥ 80 % bloquante | #1602 | ❌ gate advisory ~45-51 % | 🔄 S-4 (gate bloquante, tests ajoutés) |
| `safeEmployeeBalance` avale les exceptions | N9P4 | ❌ | ✅ propagée (S-3) |
| `effective_from` nullable | N9P4 | ❌ | ✅ additive NOT NULL (S-3) |

## 3. Constats propres de cette session

### 3.1 Défauts corrigés pendant la session
1. **Test latent rouge sur main** : `EndOfContractApiTest` créait un employé avec
   `position` (colonne absente du vrai schéma) — la suite complète n'a pas tourné
   sur les derniers commits main (tests.yml filtré par chemins + runs annulés lors
   de la frénésie de merges) : un rouge réel mais invisible. Corrigé via
   `position_id` + modèle `Position`.
2. **Bug runtime réel** dans `BiometricPurgeExpiredCommand` : le closure de
   `DB::transaction` n'importait pas `$months`/`$cutoff` (utilisés dans le
   metadata d'audit) → variables indéfinies à l'exécution. Corrigé (test 7/7).
3. **OpenAPI invalide sur main** : clé `/payroll-runs/{payrollRun}/journal`
   dupliquée (redocly échoue sur toute PR touchant `api/routes/**`) + SDK
   JS/Python/MANIFEST non régénérés (drift). Corrigé sur la branche S-1.
4. **Pattern de test piégeux** : `PendingCommand` est lazy (`__destruct`) — les
   tests qui chaînent `$this->artisan(...)->assertExitCode(0)` puis vérifient
   l'état DB constatent un état AVANT exécution. Corrigé dans les tests S-1/S-2
   par `run()` explicite. À auditer sur l'ensemble du repo (risque de faux
   positifs verts).
5. **Gate coverage advisory** : `continue-on-error: true` masquait le vrai
   chiffre (51 % mesuré localement avec pcov) — S-4 la rend bloquante.

### 3.2 Risques résiduels
- **R-A1** : l'historique git public contient des secrets réels (Redis, Neon) —
  #1472/#1601, action humaine requise, priorité absolue (le token exposé reste
  récupérable par quiconque clone le dépôt public).
- **R-A2** : double implémentation en parallèle (agent + bot « leopardo-hr bot »)
  sur les mêmes specs → nécessité de fermer les PR redondantes à la fusion
  (#1670/#1671/#1672/#1673 vs #1668/#1669/#1674) pour garder main propre.
- **R-A3** : le pattern PendingCommand lazy (3.1.4) peut masquer d'autres tests
  « verts » qui ne testent rien côté DB — à traiter par un audit ciblé.
- **R-A4** : `coverage-gate.yml` backend ≥ 60 % reste un plancher global ; la
  montée à 65 % (ratchet) n'est pas programmée dans une issue.

## 4. Spécifications proposées (cf. SPECS_AUDIT_AGENT_2026-08-10.md)
