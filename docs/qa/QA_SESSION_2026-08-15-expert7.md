# QA Leopardo HR — Session Expert 7 du 2026-08-15

Mission : auditer la plateforme (vitrine, web, admin, mobiles, workflows, API,
logiques, onboarding, cohérence), formaliser les constats selon la méthode
Spec Kit, implémenter le max d'issues ouvertes, merger le max de branches,
`main` VERT.

## Contexte

- Swarm QA actif : 276 issues ouvertes, 67 PRs, CI saturée, plusieurs agents
  sur les mêmes fichiers (leçons #2400 anti-dup, #3324 checks pending).
- Sandbox de cette session : **Node 24 uniquement** (pas de PHP/Dart) →
  validation locale web/admin (tsc, eslint, jest, mojibake) ; changements
  PHP/Dart chirurgicaux vérifiés par la CI.

## Bilan de la session

| Domaine | Issues traitées | PRs | Statut |
|---------|----------------|-----|--------|
| Web vitrine | #3328 #2984 #2985 #2987 #3266 #3264 #3435 #3254 | #3472 #3473 #3474 #3475 #3476 #3477 #3483 #3484 | 1 MERGED, 7 ⏳ CI |
| Admin dashboard | #3280 #3275 | #3501 #3502 | ⏳ CI |
| API Laravel | #3320 #3002 #3244 #3309 | #3506 #3507 #3508 #3509 | 3 MERGED |
| Mobile Flutter | #3005 #3432 #3433 | #3511 #3512 #3513 | 3 MERGED |
| Tooling/docs (constats neufs) | #3540 #3541 | #3550 #3551 | ⏳ CI |

- **Clôture sans code** : #3330 (déjà corrigé par #3212 sur main, preuve code).
- **Duplicat fermé** : #3329 (déjà couvert par #3440) → PR #3467 fermée avec
  renvoi vers la PR canonique (protocole #2400).
- **Abandon** : #3437 (PR #3465 canonique déjà alignée sur les clés réelles).

## Constats neufs d'audit (non couverts par les 210 issues existantes)

1. **#3540** — `dev-hub/tools/release-readiness.ps1:33` : variable morte
   `$mobileTests` comptant `front/mobile/test` (monolithe supprimé #754),
   jamais utilisée par un Add-Check → ligne retirée.
2. **#3541** — `CHANGELOG.md [Unreleased]` : entrées dupliquées par les merges
   parallèles (« vague hygiène » ×4, « routeur Manager réparé » ×2, entrées
   collées sur une même ligne) → seams recollés + déduplication (1/1).

## Méthode

- **Anti-doublon durci** : avant chaque claim, vérification des branches ET des
  **corps de PR** (le nom de branche ne contient pas toujours le numéro
  d'issue — cas #3328/#3329 couverts par `fix/3326-3333-web-coherence`).
- **Validation locale** web/admin : `npx tsc --noEmit`, `npm run lint`,
  `jest` ciblé, `check-mojibake` — 0 erreur avant push.
- **CHANGELOG** : entrée `[Unreleased]` ajoutée sur les 17 PRs de la session.
- **Rebase** : 11 branches en conflit mergées sur `origin/main` puis re-poussées.

## Artefacts spec-kit

- `.specify/features/qa-expert7-session-2026-08-15/` — spec/plan/tasks.

## Notes

- Leçon réutilisable : le filtre « issues libres » doit inclure les corps de
  PR (une PR `fix/3326-3333-web-coherence` couvre #3328/#3329 sans les nommer
  dans la branche).
- Leçon réutilisable : les merges parallèles concatènent les CHANGELOG.md
  divergents (entrées collées `.**- **`) — un passage de dédup est nécessaire
  après chaque vague.
