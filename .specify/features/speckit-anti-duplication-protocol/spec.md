# Feature Specification: PROCESS — protocole anti-doublon Spec Kit durci (marker branch)

**Feature Branch**: `docs/2400-speckit-anti-duplication`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2400

## Problème

Malgré la règle « auto-assignation obligatoire » (constitution §I), de nombreuses issues reçoivent plusieurs PRs/branches concurrentes (constat 2026-08-15 : #2333 ×3 PRs, #2329 ×2 PRs, #2264 ×2, #2326 ×2 branches, #2335 ×2 branches…). Le claim par assignation seule ne protège pas la fenêtre implémentation ; les suffixes de branches différents (`fix/2333-a/b/c`) rendent la collision indétectable si on ne vérifie que les PRs.

## User Stories & Testing

### User Story 1 — Le claim est visible par tous (P2)
**Acceptance Scenarios**:
1. Given un agent se self-assigne une issue, When il pousse la branche `fix/<issue>-<slug>` avec un commit de claim, Then tout autre agent qui scanne les branches détecte la collision.
2. Given la règle documentée dans AGENTS.md + constitution, When lecture, Then le protocole en 4 étapes (vérif branches, marker branch, nommage unique, fermeture doublons) est explicite.

### User Story 2 — Les doublons sont nettoyés (P2)
**Acceptance Scenarios**:
1. Given #2333 (3 PRs CORS), When nettoyage, Then une seule PR ouverte reste (#2359).
2. Given #2329 (2 PRs), When nettoyage, Then une seule PR ouverte reste (#2362) — #2388 fermée avec renvoi.

## Plan technique
1. AGENTS.md : règle anti-doublon durcie (vérif branches + marker branch + nommage unique + fermeture doublons).
2. Constitution §I + §VII : marker branch et vérification élargie.
3. Nettoyage effectif des doublons #2333 (déjà clos #2351/#2352) et #2329 (#2388 fermée → #2362 canonique).
4. CHANGELOG + PR `docs/2400-...` `Closes #2400`.
