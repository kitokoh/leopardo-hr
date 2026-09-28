# POC jetable — ADR-0027 « Multi-org : un compte, plusieurs entreprises » (BOS-036, #8225)

> ⚠️ **Jetable — ne sera JAMAIS fusionné dans `main`.** Branche support du POC exigé par l'issue #8225
> (« POC jetable de la stratégie retenue pour l'analyse, hors prod »). Aucun code de production ici :
> une modélisation en mémoire des tables existantes pour vérifier les invariants de la décision proposée.

## Ce que le POC démontre

`switch.mjs` (Node ≥ 18, zéro dépendance) modélise les tables réelles du repo — `users` (public),
`employees` (email unique global), `user_lookups` (email PK → company principale),
`user_employee_links` (`unique(user_id, company_id)`), tokens Sanctum scopés — et vérifie
automatiquement les invariants de la stratégie **go-limité** d'ADR-0027 :

| # | Invariant ADR-0027 | Vérifié |
|---|---|---|
| 1 | Unicité email conservée : un 2ᵉ **compte** au même email est refusé (`GLOBAL_COLLISION`) ; la présence multi-tenant passe par des **liens**, pas par des comptes | ✅ |
| 2 | Login inchangé : `user_lookups` reste mono-ligne, dispatch sur la company principale | ✅ |
| 3 | Switch fail-closed : sans lien actif → refus uniforme (pas de fuite d'existence), refus audité | ✅ |
| 4 | Switch légitime : révocation de l'ancien token, réémission scopée au tenant cible ; une requête = un tenant ; aucun droit transporté (le rôle vient de la ligne `employees` du tenant courant) | ✅ |
| 5 | Kiosk inaffecté : le tenant reste porté par le device | ✅ |
| H | Cas cabinet comptable : un compte lié à N clientes, accès **séquentiel** uniquement | ✅ |

## Exécution et résultat

```bash
node poc/bos-036-company-switch/switch.mjs
```

Résultat mesuré (2026-09-28, Node v24.18.0) : **18 vérifications vertes, 0 rouge** — sortie complète
reproduite dans la PR du lot Z13.

## Ce que le POC n'est PAS

- Pas du code prod, pas un design d'implémentation Laravel (noms, signatures indicatifs).
- Pas une démo de consolidation holding (G-multi-entités — explicitement reportée, F2).
- Pas une preuve de charge ou de sécurité : une preuve de **cohérence des invariants** de la décision.
