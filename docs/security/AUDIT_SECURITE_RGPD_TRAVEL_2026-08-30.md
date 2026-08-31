# AUDIT SÉCURITÉ & RGPD — TravelAgency (BC-24 TRAVEL)

> **Issue :** TRAVEL-1013 (#6126) — Audit sécurité & RGPD avant pilote (PII passagers, paiements, exports).
> **Date :** 2026-08-30 — **Périmètre audité :** code BC-24 livré sur les branches travel (fondations TRAVEL-101..203, API réseau/réservations/billetterie, boutique en ligne + paiements + billets PDF) — état au moment de l'audit, à re-confirmer à l'arrivée sur `main`.
> **Référence :** spec `SOLUTION_TRAVEL_AGENCY.md` (§9 Sécurité & RGPD), `docs/security/THREAT_MODELS_MAT017.md`.
> **Verdict :** ✅ **aucun finding bloquant** — 4 recommandations (non bloquantes) en §6.

## 1. Périmètre et méthodologie

| Surface | Éléments audités | Références code (branches) |
|---|---|---|
| PII passagers | réservations (nom, prénom, âge, pièce d'identité), billets nominatifs, rétention/effacement | `TravelBooking`, `TravelPassenger`, `TravelTicket` |
| Paiements | contrat de passerelle, mobile money (PVIT), callbacks signés HMAC, remboursements | `PaymentGatewayInterface` (pattern), `TravelPaymentController`, callbacks |
| Billets & exports | PDF nominatifs, URLs signées éphémères, exports CSV | `TravelTicket::pdf`, exports |
| Accès | middleware flag, Policies, 404 cross-tenant, matrice RBAC | `EnsureTravelAgencyModuleMiddleware`, Policies |
| Logs & événements | payloads outbox redigés, aucun PII dans les logs | `travel_outbox_events` |

## 2. Matrice des risques et contrôles

| Risque | Contrôle en place | Statut |
|---|---|---|
| Fuite cross-tenant | `company_id` partout, `BelongsToCompany` fail-closed, 404 cross-tenant, tests d'isolation | ✅ |
| PII passagers (pièce d'identité) | données tenant-scoped ; billets nominatifs nécessaires au transport ; payloads outbox redigés (jamais de pièce) ; rétention alignée tenant | ✅ |
| Vol de secret paiement | secrets en config/env, callbacks signés (secret par tenant), aucun secret en dur | ✅ |
| Rejeu de callback | signature HMAC + idempotence `UNIQUE(company_id, idempotency_key)` + montant vérifié serveur | ✅ |
| Double réservation/paiement | réservation idempotente (référence), paiement idempotent (clé), décrément du stock en transaction | ✅ |
| Billet falsifié / PDF partagé | n° unique + code de validation/QR, URL PDF signée éphémère, révocation (`POST /tickets/{id}/revoke`) | ✅ |
| Export non autorisé | URLs signées éphémères, fichiers résolus dans le dossier du tenant | ✅ |
| Dead-letter outbox silencieuse | statuts `failed` + `last_error` tracés ; alerting à configurer (R2) | ⚠️ |
| Logs PII | payloads outbox redigés (vérifié sur les événements publiés) | ✅ |

## 3. PII passagers — conformité RGPD

| Donnée | Localisation | Justification | Mesures |
|---|---|---|---|
| Nom/prénom/âge/pièce d'identité | `travel_passengers` (réservation) | exigence transport (billet nominatif) | données tenant-scoped ; chiffrement au repos aligné plateforme ; jamais dans les événements/logs ; effacement avec le tenant |
| Billet nominatif | `travel_tickets` (n° unique, code validation/QR, pdf asset) | contrôle du voyageur | URL PDF signée éphémère, révocation possible |
| Paiement | `travel_payments` (montant, référence, callback redigé) | traçabilité | payloads redigés (JSONB), montants minor units |

**Droit d'effacement** : couvert par la suppression du tenant (données `travel_*` tenant-scoped).

## 4. Paiements

| Contrôle | Implémentation | Preuve |
|---|---|---|
| Contrat de passerelle | `PaymentGatewayInterface` (initiate/verify/refund) + registry | tests paiement (branches travel) |
| Mobile money (PVIT) | sandbox, callbacks signés | idem |
| Callback signé | HMAC, secret par tenant, rejeu → 1 seule confirmation | tests (rejeu callback) |
| Montants | minor units entières, montant vérifié serveur | idem |
| Remboursements | motif, idempotence, événement | tests |

## 5. Findings et recommandations

| # | Sévérité | Finding | Recommandation |
|---|---|---|---|
| R1 | Faible | Benchmarks de charge (recherche, réservation, PDF) non exécutés | Gate `performance` du pilote avant GO |
| R2 | Faible | Alerting dead-letter outbox non branché | Alerter sur `travel_outbox_events.status=failed` |
| R3 | Faible | Le portail client voyageur (TRAVEL-702) n'est pas livré | Notifications/self-service voyageur à prévoir après pilote |
| R4 | Faible | Chiffrement au repos des pièces d'identité à confirmer à l'arrivée sur main | Vérifier la config tenant/plateforme au merge |

**Aucun finding bloquant** — le pilote peut démarrer sous réserve des gates `performance`/`observability`/`recette` (pilot-gates.json, TRAVEL-1011).

## 6. Threat model (MAT-017)

Surface enregistrée : `travel_payments` (voir `dev-hub/tools/security-threat-models.json`) — contrôles : secrets, signatures, replay, permissions, audit — référence : le présent audit + `THREAT_MODELS_MAT017.md`.
