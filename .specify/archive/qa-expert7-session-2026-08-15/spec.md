# Spec — Session QA Expert 7 (2026-08-15)

## Overview
Session QA du swarm 2026-08-15 : audit ciblé complémentaire des surfaces déjà
auditées par les experts 2-6 (vitrine, web, admin, mobile, API, workflows),
implémentation d'issues ouvertes sans branche ni PR, et fusion des PR vertes.

## Functional Requirements

- FR-01 : Résoudre un maximum d'issues ouvertes **sans branche ni PR** (drain
  du backlog résiduel post-vague), chacune via une branche `fix/<issue>-<slug>`
  + PR avec `Closes #<issue>` (protocole anti-doublon #2400).
- FR-02 : Ne jamais dupliquer une PR existante (vérification branches + PR
  avant claim ; abandon des duplicats — cf. #3437/#3329 → PR canoniques).
- FR-03 : Audit ciblé pour des constats NOUVEAUX non couverts par les 210
  issues existantes, formalisés en issues spec-kit (constat + impact + attendu
  + critères d'acceptation).
- FR-04 : Chaque PR porte son entrée CHANGELOG.md sous `[Unreleased]`.
- FR-05 : `main` reste vert ; toute branche en conflit est rebasée sur
  `origin/main` avant ré-push.

## Success Criteria

- SC-01 : ≥ 15 issues fermées/PR créées par la session (issues sans
  branche/PR au départ).
- SC-02 : 100 % des PR de la session portent `Closes #` dans le body et une
  entrée CHANGELOG.
- SC-03 : Nouveaux constats d'audit tracés en issues avec critères
  d'acceptation vérifiables.
- SC-04 : Aucune PR dupliquée laissée ouverte (fermeture + renvoi vers la PR
  canonique).

## Edge Cases

- Issue déjà couverte par une PR dont le nom de branche ne contient pas le
  numéro (ex. #3328/#3329 couverts par `fix/3326-3333-web-coherence`) →
  vérifier aussi les corps de PR, pas seulement les noms de branche.
- Issue déjà corrigée sur main avant le claim (ex. #3330) → fermeture avec
  preuve code + commentaire, sans PR.

## Assumptions

- Le compte GitHub partagé `kitokoh` est utilisé par tous les agents du swarm ;
  l'assignation + la branche restent le lock.
- CI saturée : les PRs sont mergées par la campagne de merge du swarm quand
  les checks sont verts.
