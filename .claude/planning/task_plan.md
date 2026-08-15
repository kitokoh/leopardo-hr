# Task Plan — Session drain backlog Leopardo HR (2026-08-15)

## Goal
Implementer les tâches ouvertes du backlog une par une, review les issues, créer les
tickets manquants, rebase + merge sur main si tout est vert. Token utilisateur révoqué
en fin de session — travailler efficacement.

## Contexte
- Repo: kitokoh/leopardo-hr (main protégé, strict status checks, 0 review requise)
- 79 issues ouvertes réelles, 73 déjà couvertes par PR/branches d'autres agents
- 6 non-claimées: #1912 (bloqué expert), #2413 (CI saturée), #2414 (protection), #2415 (coverage), #2422 (review), #2429 (mobile UI)
- File Actions saturée: 659 runs queued, 18 in_progress

## Phases
- [ ] P1. Débloquer la CI (#2413): annuler les runs orphelins (branches sans PR ouverte)
- [ ] P1. Vérifier protection main (#2414): mesurée à 0 review requise → documenter/fermer
- [ ] P1. Investiguer coverage Payroll (#2415): gate rouge sur main
- [ ] P2. Review des issues/PRs (#2422) + créer les tickets manquants détectés
- [ ] P2. Implémenter les tâches non-claimées restantes (ex: #2429 mobile si toolchain dispo)
- [ ] P3. Concurrency groups + script cleanup CI (PR pour #2413, durable)
- [ ] P4. Rebase + merge mon travail sur main si tout vert
- [ ] P5. Rapport final (issues traitées, tickets créés, état main)

## Décisions
- Ne pas merger les PRs des autres agents sans arbitrage (risque), sauf demande explicite
- Ne pas toucher aux runs des branches avec PR ouverte (travail d'autres agents)
- Garder les runs sur main (source de vérité)
