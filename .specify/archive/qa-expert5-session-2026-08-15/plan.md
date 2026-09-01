# Plan: QA Expert 5 — Session 2026-08-15

## Contexte
- `main` très actif (swarm de 4+ agents QA le 2026-08-15, ~50 PRs en file CI saturée).
- Le swarm mergeait sur checks **pending** (bypass admin, `enforce_admins: false`) → un test rouge est passé sur main (#3324, TestRtspSsidGuardTest).
- Sandbox : PHP 8.4 + PostgreSQL + Redis installés → **validation locale possible** (unique parmi les agents).

## Stratégie
1. **Protéger main vert** : valider localement (tests + PHPStan + Pint) avant merge ; corriger immédiatement tout rouge latent (#3324 → PR #3344).
2. **Ne pas dupliquer** : vérifier branches/PRs/issues existantes avant chaque action (protocole anti-doublon #2400).
3. **Tester live** les surfaces déployées + **audit statique** 4 surfaces (rapports dans `docs/qa/audit-expert5-2026-08-15/`).
4. **Formaliser** : issues GitHub `[QA][P#]` pour les constats propres + feature spec kit `qa-expert5-session-2026-08-15/`.
5. **Implémenter** les constats propres (#3324, #3352) + contribuer au merge des PRs vertes.
6. **Merge campagne** : uniquement PRs aux 5 checks requis verts ; merge main dans les branches stale ; vérifier main vert en fin.

## Étapes
| # | Action | Statut |
|---|--------|--------|
| 1 | Clone + lecture constitution/spec kit + inventaire issues/PRs | ✅ |
| 2 | Install PHP/PG/Redis + composer + migrations | ✅ |
| 3 | Tests unitaires locaux (511 pass, 2 fail SSRF) | ✅ |
| 4 | Issue #3324 + fix test SSRF + PR #3344 | ✅ |
| 5 | Test live surfaces (API/vitrine/admin) | ✅ |
| 6 | Audits statiques 4 surfaces (scouts parallèles) | ✅ |
| 7 | Issue #3352 + fix /contact + PR #3357 | ✅ |
| 8 | Feature spec kit (ce dossier) + docs session → PR docs | 🔄 |
| 9 | Suite Feature locale complète | 🔄 |
| 10 | Merge campagne (PRs vertes + stale) | 🔄 |
| 11 | Vérif finale main vert + rapport | ⬜ |

## Risques
- File CI saturée → prioriser, ne pas flooder de re-runs.
- Conflits avec le swarm → protocole anti-doublon strict (vérifier branches avant push).
- Staging stale (v4.23.5) → les tests live API sont limités (déploiement = action ops F-E4-01).
