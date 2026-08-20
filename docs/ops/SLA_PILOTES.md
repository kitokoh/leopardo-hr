# 🚨 SLA_PILOTES — Bugs pilotes : canal, triage et hotfix < 24 h (issue #5155)

**Version** : 1.0 · **Date** : 2026-08-20 · **Promesse** : un bug **bloquant**
signalé par un pilote se règle en **moins de 24 h** (déploiement prod inclus).

## 1. Canal

- **Issue GitHub** avec le template `PILOT_BLOCKER`
  (`.github/ISSUE_TEMPLATE/pilot_blocker.yml`) + label **`pilot-blocker`**.
- Le pilote (ou l'agent QA qui l'accompagne) ouvre l'issue ; l'agent ops la
  prend en charge immédiatement (self-assign + branche, protocole #2400).
- Pas d'outil de ticketing externe ; pas de SLA contractuel écrit en phase
  pilote (les engagements restent dans le carnet pilote, issue #5152).

## 2. Triage

| Niveau | Définition | Action |
|---|---|---|
| **Bloquant** | Paie, pointage ou login **impossible** en prod pour le pilote | Hotfix < 24 h (process §3) |
| **Dégradé** | Fonctionnalité accessible mais cassée partiellement | Fix normal (P1/P2), pas de promesse 24 h |
| **Amélioration** | Demande/UX | Issue P2 normale, backlog |

Règle de triage : **« la paie est-elle bloquée ? le pointage ? le login ? »**
— si oui à l'un des trois en prod → bloquant. Tout le reste est P2.

## 3. Workflow hotfix (< 24 h)

1. **Réception** : issue `pilot-blocker` ouverte avec repro + preuve (template).
2. **Triage immédiat** : agent ops — si le bug est confirmé en prod, priorité
   absolue (un seul hotfix à la fois).
3. **Branche** : `hotfix/<issue>-<slug>` depuis `main` (protocole #2400 :
   self-assign + branche = lock).
4. **CI minimale obligatoire** : tests paie (`payroll-ci.yml` paths) + E2E
   funnel prospect (`e2e-staging.yml`) — rien d'autre ne bloque le hotfix.
5. **Revue** : PR avec `Closes #<issue>` dans le body (garde #2512), revue
   humaine ou agent senior, pas de merge auto.
6. **Déploiement prod** : via `deploy-main.yml` (Render) — objectif < 24 h
   entre l'ouverture de l'issue et le déploiement.
7. **Vérification** : re-test du parcours pilote (ou smoke e2e) après deploy.
8. **Post-mortem court** (uniquement en cas de récidive du même bug) :
   5 lignes max dans l'issue : cause racine, pourquoi le premier fix n'a pas
   suffi, garde anti-régression ajoutée.

## 4. Métrique

Délai de résolution des bloquants = (déploiement prod OK − ouverture issue).
Suivi **hebdomadaire** dans le bilan du vendredi (tableau) :

| Semaine | Issue | Pilote | Ouvert | Résolu (deploy) | Délai | Cause racine |
|---|---|---|---|---|---|---|
| … | | | | | | |

Objectif : moyenne glissante < 24 h. Tout dépassement → post-mortem court.

## 5. Définition de done

- [ ] Issue fermée par `Closes #` (commit mergé, garde #4816)
- [ ] Hotfix déployé en prod + parcours pilote re-testé
- [ ] Délai consigné dans le tableau hebdo
- [ ] Récidive → post-mortem + garde de test ajoutée

---
*Issue #5155 (plan 60 jours, Batch 3, Phase 3) — à activer après le gate J16
et la signature du 1er pilote. Référence : AGENTS.md.*
