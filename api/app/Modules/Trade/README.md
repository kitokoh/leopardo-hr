# Trade — International Trade OS (BC-36)

Verticale de commerce international (import/export) et de B2B domestique
(grossiste) : noyau commercial **partagé** entre un espace acheteur et un espace
vendeur, qui sont deux vues métier sur les **mêmes objets**.

- Spec : `docs/specifications/VERTICAL_TRADE_OS.md`
- Audit : `docs/audits/TRADE_OS_AUDIT_2026-10-14.md`
- Matrice RBAC : `docs/security/RBAC_ROUTE_MATRIX.md` (section « Trade Module Matrix »)

## Activation

La verticale est **fail-closed** : rien n'est accessible tant qu'elle n'est pas
activée pour l'entreprise.

```bash
php artisan leopardo:solution:activate trade --company=<id>
```

L'activation passe par le `SolutionActivator` existant (idempotent : un second
appel retourne `already_active`). Le code `trade` est déclaré aux **trois points
obligatoires** — `ModuleRegistry`, `config/feature-flags.php` et
`Company::KNOWN_MODULES` (leçon #7220/#7235 : un code déclaré à deux points sur
trois rend la verticale inactivable).

Sans activation, toute route `/api/v1/trade/*` répond **403
`FEATURE_NOT_ENABLED`**.

## Rôles

L'autorisation est **par opération**, portée par le middleware `trade.role` et le
`TradeRoleResolver` (deny-by-default, pattern VTC). Un manager `principal`
reçoit `trade.admin`.

| Rôle | Périmètre |
|---|---|
| `trade.purchase` | Côté acheteur : émettre une RFQ, la diffuser, accepter une offre |
| `trade.sales` | Côté vendeur : répondre à une RFQ, émettre une offre |
| `trade.logistics` | Transport, coûts rendus, préparation douanière |
| `trade.admin` | Administration de la verticale |

Ces rôles sont **fonctionnels** : ils ne remplacent jamais les rôles de sécurité
tenant/plateforme. Une même entreprise peut importer **et** exporter — aucun rôle
métier n'est irréversible.

## Endpoints

| Méthode | Chemin | Rôles |
|---|---|---|
| GET | `/trade/ping` | (gate seule) |
| GET/POST | `/trade/rfqs` | purchase, admin |
| GET | `/trade/rfqs/{rfq}` | purchase, sales, admin |
| POST | `/trade/rfqs/{rfq}/send` | purchase, admin |
| GET | `/trade/quotes` | purchase, sales, admin |
| POST | `/trade/quotes` | sales, admin |
| GET | `/trade/quotes/{quote}` | purchase, sales, admin |
| POST | `/trade/quotes/{quote}/accept` | purchase, admin |
| GET/POST | `/trade/cost-estimates` | purchase, logistics, admin |
| GET | `/trade/cost-estimates/{costEstimate}` | purchase, logistics, admin |
| GET | `/trade/customs/checklist` | purchase, logistics, admin |

`GET /trade/quotes` accepte `?direction=incoming|outgoing` : c'est le **même
contrôleur** qui sert les deux espaces.

## Isolation tenant

Le périmètre est appliqué par `BelongsToCompany`. Une ressource appartenant à une
autre entreprise est **introuvable (404), jamais 403** : l'API ne révèle pas son
existence. Preuve : `tests/Feature/Security/TradeTenantIsolationTest.php`.

## Honnêteté des chiffres

Deux règles non négociables, vérifiées par les tests :

1. **Aucun total fourni par le client n'est accepté.** Seuls les prix unitaires
   et les quantités le sont ; le serveur calcule `subtotal_minor` et
   `total_minor`.
2. **Aucun chiffre n'est inventé.** Chaque poste de coût porte sa `source`
   (`confirmed` / `estimated` / `user_input` / `unavailable`) et un poste
   indisponible n'est jamais chiffré. Le taux de change est tracé
   (`fx.rate` / `fx.date` / `fx.source`) ; sans lui, les postes en devise
   étrangère sont signalés comme **non convertis** plutôt que convertis avec un
   taux deviné.

## Configuration requise et limites v1

| Capacité | État |
|---|---|
| RFQ, offres, commandes, coût rendu | **Opérationnel** |
| Checklist documentaire douanière | **Opérationnel** (documents + informations manquantes) |
| Estimation des droits et taxes de douane | **INDISPONIBLE** — `duty_rate_available: false`, `duty_rate_percent: null` tant qu'aucune source de tarifs n'est configurée. La capacité est signalée comme indisponible, jamais estimée à 0. |
| Taux de change automatique | **Non configuré** — le taux est fourni par l'appelant et tracé ; aucun fournisseur de taux n'est branché. |
| Cotations transporteurs / transitaires | **Non implémenté** (modèles `TradeTransportRequest` / `TradeTransportQuote` présents, endpoints à venir) |
| Espaces prestataires (transporteur, transitaire, dédouanement, contrôle qualité) | **Non implémenté** |
| Apps mobiles terrain | **Non implémenté** — `mobile_field` est catalogué mais jamais suggéré tant que l'app n'existe pas |

> Leopardo facilite l'estimation, la préparation documentaire et la mise en
> relation avec un prestataire compétent. Il ne rend **aucune décision
> douanière officielle** et ne garantit aucune conformité réglementaire.

## Structure

```
Trade/
├── Application/     Actions, DTOs, Queries, Listeners
├── Domain/          Enums (machines à états), Models, ValueObjects, Survey, Manifests
├── Infrastructure/  Services (coût rendu, checklist douanière), Repositories, Exports
├── Interfaces/      Api/V1 (Controllers, Requests, Resources)
└── Providers/       TradeServiceProvider
```
