# 🚫 Freeze Scope 60 jours — Leopardo RH (issue #5147)

**Version** : 1.0 · **Date** : 2026-08-19 · **Validité** : J1-J60 du plan (19/08 → 17/10/2026) — à réviser au gate J60.
**Règle d'or** : toute feature hors liste est **refusée en revue**, avec renvoi vers ce document. Une PR qui sort du périmètre sans issue dédiée est fermée avec ce lien.

---

## ✅ AUTORISÉ (on fait — toute priorité confondue)

| Domaine | Exemples |
|---|---|
| **Funnel d'acquisition** | trial guided/OTP (#5161/#5162), checkout, DNS (#3452), pricing, signup, magic link |
| **CI & qualité** | campagne « 5 jours verts » (#5145), E2E funnel (#5146), flakiness, gates, inventaires |
| **Paie DZ (wedge)** | golden tests ≥ 40 (#5149), clôture 2 étapes (#5150), benchmark 10k, exports DZ, runbook |
| **Pilotes DZ** | onboarding < 30 min (#5151), carnets (#5152), kit prospection (#5154), SLA (#5155), suivi usage (#5156) |
| **Bugs P0/P1** | toute régression bloquante, sécurité, data-loss — priorité absolue |
| **Sécurité** | scans, rotations de secrets, purge forks, durcissement RGPD |
| **Traîne & gouvernance** | cleanup (#5153), freeze scope, budget agents (#5148), handoff (#5160), bilan (#5159) |
| **i18n P0** | chaînes qui bloquent un parcours pilote (pas de vague générale) |

## 🟡 MAINTENANCE (bugs + sécurité seulement — pas de nouvelle feature)

| Domaine | Politique |
|---|---|
| Kiosque ZKTeco (web + edge) | bugs/sécurité prioritaires ; l'épic #5119 (punch-methods) attend le gate J16 |
| Apps mobiles non-employee | builds verts garantis, fixes de régression ; convergence F-27 gelée |
| i18n mobile (hors P0) | seules les chaînes bloquantes ; épics #2755/#4194 en veille |
| Modules périphériques (Recrutement, Caméras, Fleet, Growth, Marketing, Cabinet, Expense, EdgeSync, Notification avancées, SmartAttendance) | bugs + sécurité, zéro feature |
| PWA web-offline | bugs bloquants uniquement |

## 🚫 GELÉ (nouvelles features — refusées sans issue dédiée)

- Toute feature des modules périphériques (cf. liste 🟡)
- Nouveau pays de paie (hors DZ) avant le gate J60 — y compris MA/SN (sauf décision contraire du fondateur)
- Nouvelle app mobile ou nouveau module DDD
- Caméras RTSP / vision IA, Leo AI conversationnel, marketplace, SDK public
- Refactor d'architecture non lié à un bug (ex. nouvelles migrations de structure sans issue)
- Outils d'analytics / tracking externe

## Comment demander une exception

1. Ouvrir une issue avec le label `peripheral` ou le préfixe `[FREEZE-EXCEPTION]`
2. Justifier : impact pilote/prospect, coût si reporté, effort
3. La décision appartient au fondateur (pas à l'agent) — les agents ne s'auto-autorisent pas une exception

## Liens
- Plan 60 jours : `PLAN_60_JOURS.md` (racine du repo)
- Issues : #5144 → #5160 (batches 1-3) ; épics FOCUS (fermés) pour la paie DZ
- Constitution : `.specify/constitution.md`

---
*Document de gouvernance — généré depuis l'issue #5147 (plan 60 jours).*
