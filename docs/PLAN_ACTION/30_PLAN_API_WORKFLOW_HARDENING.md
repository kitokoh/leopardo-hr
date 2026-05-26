# Plan 30 - API workflow hardening

Date : 2026-05-26

## Objectif

Renforcer le socle API pour que les trois apps mobiles, le web client, le kiosque et l'admin plateforme puissent executer leurs workflows reels sans divergence de contrat.

Le principe du plan 30 est simple : chaque bouton critique visible dans un frontend doit correspondre a une route Laravel stable, documentee, testee et compatible avec le role attendu.

## Lots

### Lot 30.1 - Contrats frontends/API

- Etendre `FrontendApiContractTest` aux routes mobiles employee/manager qui etaient utilisees mais pas encore gardees : equipe, invitations, avances, approvals et resume mensuel.
- Ajouter les routes Platform Admin mobile : auth, plans, entreprises, health, subscriptions, features, metriques et demandes clients.
- Garder `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md` comme matrice canonique.

### Lot 30.2 - Creation client depuis Platform Admin mobile

- Accepter un payload mobile minimal pour `POST /api/v1/platform/companies`.
- Resoudre automatiquement le premier plan actif si `plan_id` n'est pas fourni.
- Definir des defaults serveur coherents : secteur `Non precise`, langue `fr`, devise `DZD`, timezone `Africa/Algiers`, pays uppercase.
- Retourner une erreur JSON propre si aucun plan n'est disponible ou si l'email manager existe deja.

### Lot 30.3 - Listes plateforme robustes

- Standardiser `GET /api/v1/platform/companies` avec `data` + `meta`.
- Ajouter filtres allowlistes `status`, `search`, `per_page`.
- Standardiser `GET /api/v1/platform/company-requests` avec validation `status`, `search`, `per_page`.

## Critere de completion actuel

- Les routes critiques existent dans Laravel.
- La creation d'entreprise Platform Admin mobile fonctionne avec le payload reel de l'app.
- Les listes platform ne prennent pas de champ libre dangereux.
- Les tests contractuels couvrent les nouveaux workflows.

## Lots suivants proposes

- Lot 30.4 : tests fonctionnels bout-en-bout employee mobile, manager mobile et platform admin mobile avec tokens reels de test.
- Lot 30.5 : OpenAPI enrichi pour toutes les routes Platform Admin et workflows mobile.
- Lot 30.6 : payload examples versionnes pour Flutter, Next.js, kiosk et integrateurs.
- Lot 30.7 : limites de plan/rate-limit par workflow critique avec messages exploitables cote mobile.
