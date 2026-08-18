# Rapports de sessions QA — Leopardo RH

Index des rapports de sessions d'audit/implémentation. **Créé le 2026-08-17 (revue PM).**
Les rapports décrivent un **snapshot daté** d'un repo en activité concurrente permanente
(swarm d'agents) : pour l'état **actuel** d'une issue, toujours vérifier via GitHub
(`state`, PR mergée) avant de citer un statut.

## 2026-08-14

| Fichier | Agent | Objet |
|---|---|---|
| `QA_SESSION_2026-08-14.md` | swarm (4 agents) | Audit initial : ZKTeco, RBAC, paie, exports, dashboard |

## 2026-08-15 — vague experts 1-17 + audit externe

| Fichier | Agent | Objet |
|---|---|---|
| `QA_SESSION_2026-08-15.md` | audit externe (4 agents statiques + live) | Audit complet OPS/API/MOB/WEB/ADM — issues #2628→#2645 |
| `QA_SESSION_2026-08-15-expert.md` | expert 1 | ~180 issues pré-existantes, anti-dup #2400 |
| `QA_SESSION_2026-08-15-expert2.md` | expert 2 | 43 issues (#3021→#3065), web/admin/mobile/API |
| `QA_SESSION_2026-08-15-expert3.md` | expert 3 | 19 PRs, main rouge 4 checks, clôtures avec preuves |
| `QA_SESSION_2026-08-15-expert4.md` | expert 4 | Merge campaign + décision D-E4-01 (trial 14 jours) |
| `QA_SESSION_2026-08-15-expert5.md` | expert 5 | 41 findings → #3363→#3416, 5 PRs de vague |
| `QA_SESSION_2026-08-15-expert5-live-qa.md` | expert 5 (live) | Sondes prod : #2646, prod stale, /docs |
| `audit-expert5-2026-08-15/` | expert 5 (lecture seule) | 4 audits par surface (API/admin/mobile/web) |
| `QA_SESSION_2026-08-15-expert6.md` | expert 6 | 2e passe 360°, régression #3280, dettes i18n |
| `QA_SESSION_2026-08-15-expert7.md` | expert 7 | 276 issues, 67 PRs, scans tooling |
| `QA_SESSION_2026-08-15-expert8.md` | expert 8 | 16 issues (#3485→#3500), PR unique #3581 |
| `QA_SESSION_2026-08-15-expert10.md` | expert 10 | Kiosk/Edge/infra — 16 issues (#3586→#3602) |
| `QA_SESSION_2026-08-15-expert12.md` | expert 12 | 20 PRs mergées, 46 branches supprimées |
| `QA_SESSION_2026-08-15-expert13.md` | expert 13 | 19 PRs mergées, CI (#3802/#3803) |
| `QA_SESSION_2026-08-15-expert14.md` | expert 14 (rondes 1+2) | 22 issues #3888→#3909 + round 2 (16/08) |
| `QA_SESSION_2026-08-15-expert14b-session2.md` | expert 14b (session 2, 16/08) | SDK drift, goldens, LFS |
| `SESSION_EXPERT14B_2026-08-15.md` | expert 14b (coordination) | ~60 PRs mergées, hotfixes P0 |
| `QA_SESSION_2026-08-15-expert15.md` | expert 15 | 17 issues (#3857→#3873), paie P1 |
| `session-expert15-2026-08-15.md` | expert 15 (après-midi) | 23 branches rebasées, dups clos |
| `QA_SESSION_2026-08-15-expert16.md` | expert 16 | Phases 2/3, 14 PRs (#3821→#4055) |
| `QA_SESSION_2026-08-15-expert17.md` | expert 17 | PRs mergées, probes prod (#3259) |
| `QA_SESSION_2026-08-15-expert-tests.md` | expert tests | Merge campaign + E2E |
| `SESSION_MERGE_2026-08-15.md` | merge/QA | CI blockers #3778/#3782/#3791/#3802/#3815 |
| `qa-expert11-session-2026-08-15.md` | expert 11 | #3706/#3707/#3708 (domaines, env, gardes) |
| `qa-expert14-session-2026-08-15.md` | expert 14 (i18n) | 6 PRs mergées, catalogue pricing |
| `qa-expert-audit-360-2026-08-15.md` | audit 360 (kitokoh) | 52 issues #3918→#3972, 10 implémentations |

## 2026-08-16 — swarm intensif (merge drain + audits)

| Fichier | Agent | Objet |
|---|---|---|
| `QA_SESSION_2026-08-16-agent360.md` | agent-360 (run 2) | PRs mergées, #4388→#4389, sondes prod |
| `QA_SESSION_2026-08-16-agent360-swe-qa.md` | agent 360 SWE/QA | Consolidation, 17 branches orphelines |
| `QA_SESSION_2026-08-16-agent-swe-qa.md` | agent-swe-qa | Merge drain 68→0, #4411 réouverte |
| `QA_SESSION_2026-08-16-expert16-v2.md` | expert 16 (suite) | 7 issues mergées #3965→#4146 |
| `QA_SESSION_2026-08-16-expert16-v3.md` | expert 16 (v3) | CI backend débloquée, PHPStan |
| `QA_SESSION_2026-08-16-expert18-360-audit.md` | expert 18 | 15 issues (#4183→#4206), P0 #4151 |
| `QA_SESSION_2026-08-16-expert19.md` | expert 19 | 14 issues (#4169→#4182), 12 PRs |
| `QA_SESSION_2026-08-16-expert20.md` | expert 20 | 13 PRs mergées, pricing ADR-0014 |
| `QA_SESSION_2026-08-16-expert20-360-audit.md` | expert 20 (audit) | 34 issues (#4291→#4330), P0 #4291/#4307 |
| `QA_SESSION_2026-08-16-expert20-audit-implementation.md` | expert 20 (implémentation) | 14 issues, 12 PRs |
| `QA_SESSION_2026-08-16-expert21.md` | expert 21 | Incident prod errors.php, dups consolidés |
| `QA_SESSION_2026-08-16-expert360-verification.md` | expert 360 | Matrice 61 issues (snapshot 13:55) |
| `QA_SESSION_2026-08-16-expert-agent360.md` | agent-360 (PM) | 22 issues #4395→#4417, specs spec-kit |
| `QA_SESSION_2026-08-16-expert-main-green.md` | expert-main-green | #4382 PHPStan main, PRs ouvertes |
| `QA_SESSION_2026-08-16-expert-qa360.md` | expert-qa360 | P0 #4151 (PR #4203), registre pays #4217 |
| `QA_SESSION_2026-08-16-expert-swe-qa.md` | expert SWE/QA | Issues + probes prod |
| `QA_SESSION_2026-08-16-expert-swe-qa-v2.md` | expert SWE/QA (v2) | Drain, PRs #4559/#4561 |
| `QA_SESSION_2026-08-16-expert-swe-qa-v3.md` | SWEQA-3 | ~40 merges, 22 issues #4606→#4627 |
| `QA_SESSION_2026-08-16-expert-swe-qa-BILAN.md` | expert SWE/QA | 37 issues #4494→#4530, 17 PRs |
| `QA_SESSION_2026-08-16-expert-swe-qa-drain-audit.md` | expert SWE/QA (drain) | Drain 68→0, P0 fixes, probes |
| `QA_SESSION_2026-08-16-expert-swe-qa-VERIFICATION.md` | expert SWE/QA (vérif) | **Vérification audits antérieurs** — 73 PRs à drainer, 28 issues sans PR |
| `QA_SESSION_2026-08-16-moclaw-qa-audit.md` | expert moclaw | 13 PRs fusionnées, pricing |
| `QA_SESSION_2026-08-16-qa360.md` | QA-360 | 14 PRs rafraîchies, fillable scan |
| `QA_SESSION_2026-08-16-qa-360-v2.md` | qa-360-v2 | #4393/#4394/#4370, #4431 |
| `QA_SESSION_2026-08-16-session-qa-360.md` | session-qa-360 | 4 PRs, issues #4207/#4209 |
| `QA_SESSION_2026-08-16-swe-qa-2.md` | swe-qa (soir) | Drain ~80 runs annulés, #4551 |
| `QA_SESSION_2026-08-16-sweqa2.md` | SWEQA-2 | P0 errors.php, 27 issues #4687→#4724 |
| `QA_SESSION_2026-08-16-swe-qa-360.md` | swe-qa-360 | Drain 68→7, portail #4574 |
| `QA_SESSION_2026-08-16-swe-qa-b.md` | swe-qa-b (16/08) | Fin de journée, PHPStan round 3 |
| `QA_SESSION_2026-08-16-swe-qa-merge-drain.md` | merge-drain | ~55 PRs fusionnées, incident #4565 |

## 2026-08-17

| Fichier | Agent | Objet |
|---|---|---|
| `QA_SESSION_2026-08-17-swe-qa-1.md` | swe-qa-1 | Audit 360° : #4812→#4816 (ghost-close), i18n |
| `QA_SESSION_2026-08-17-swe-qa-360.md` | swe-qa-360 | Session 360° (cf. commits `docs(qa): session swe-qa-360`) |
| `QA_SESSION_2026-08-17-swe-qa-b.md` | swe-qa-b | Drain + audit 360° + implémentations |
| `QA_SESSION_2026-08-17-swe-qa-c.md` | swe-qa-c | Audit 360° (13 issues #4787→#4799), main vert |
| `QA_SESSION_2026-08-17-swe-qa-drain-audit-lot2.md` | SWE/QA (lot 2) | Drain, portail Lot 2, #4610 réouverte |
| `TRIAGE_2026-08-17.md` | triage | Classification des 30 issues ouvertes (A/B/C/D) |

## Documents connexes

- Audits historiques : [`../audits/`](../audits/) · Audit externe : [`../external-audits/`](../external-audits/)
- Rapports d'audit par surface (lecture seule, expert5) : [`audit-expert5-2026-08-15/`](audit-expert5-2026-08-15/)

## Notes de lecture

- **Doublons connus** (même agent, plusieurs fichiers) : expert5 (3 artefacts), expert14/14b (3),
  expert16 (v2/v3), expert20 (3), expert-swe-qa (6 fichiers de la même mission). À normaliser en
  un rapport par session lors d'une passe de nettoyage.
- **Artefacts** : `audit-expert5-2026-08-15/` = entrées intermédiaires de la session expert5.
- L'ancien `probe-merge.md` (artefact de test) a été supprimé le 2026-08-17.
