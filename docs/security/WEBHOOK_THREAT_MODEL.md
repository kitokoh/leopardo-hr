# Threat Model — Webhooks et intégrations Leopardo

- **Statut :** ratifié — implémentation (issue #5740, CRM PRE)
- **Date :** 2026-08-28
- **Périmètre :** webhooks entrants (Stripe, Chargily, email-bounce,
  marketing-lead, futurs providers WhatsApp/SMS/CRM client) et sortants
  (livraisons partenaires)
- **Complète :** `docs/security/WEBHOOKS.md` (matrice des webhooks existants),
  `docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md` (contrat partenaire),
  `docs/api/VERSIONING.md` § 5bis (versioning des événements)
- **Issue de référence :** #5740 — « Finaliser le threat model des webhooks et intégrations »

---

## 1. Principe directeur

**Tout webhook entrant est une frontière hostile.** Le fournisseur (WhatsApp,
email, SMS, Stripe, Chargily…) est un tiers, pas un tenant authentifié : rien
dans le payload ne peut faire autorité — ni le tenant, ni l'identité, ni
l'événement. L'authenticité est établie par **signature (HMAC) ou secret
partagé** AVANT tout traitement, puis l'événement est **persisté avant
l'accusé de réception**, et seulement ensuite les effets métier sont
appliqués.

Règles non négociables :

1. **Aucun tenant n'est déduit d'un champ non authentifié.** La résolution du
   tenant (`TenantManager::withinTenant`, `SET search_path`) n'a lieu qu'après
   vérification de la signature (pattern `OnlinePaymentService`, issue #5272).
2. **Fail-closed** : secret non configuré → `503` ; signature invalide → `400`
   (pas de retry) ; erreur de traitement → `5xx` (le provider retente).
3. **Idempotence persistée** (#5444) : l'événement est enregistré dans
   `public.webhook_events` (unique `source + event_id`) avant tout effet
   métier ; un rejeu renvoie la réponse mémorisée sans double effet.
4. **Bornes d'entrée** (#5740) : taille ≤ 1 MiB (`413`), JSON valide (`400`),
   fenêtre de rejeu 300 s quand un horodatage est présent (`400`).
5. **Les secrets ne sont ni en base (côté entrant), ni dans les logs, ni dans
   les fixtures** : ils vivent dans la configuration (`config/services.php`,
   variables d'environnement). Les fixtures de test n'utilisent que des
   valeurs factices.

## 2. Matrice des attaques et contrôles

| # | Attaque | Vecteur | Contrôle | Où |
|---|---------|---------|----------|-----|
| A1 | Signature invalide / spoofing | Payload forgé | HMAC + `hash_equals` (Stripe/Chargily) ou secret partagé (`hash_equals`) ; secret absent → fail-closed 503 | `StripeService`, `ChargilyService`, `StripePaymentGateway`, `ChargilyPaymentGateway`, `EmailBounceWebhookController`, `MarketingLeadController` |
| A2 | Rejeu (replay) | Même payload re-soumis | Registre d'idempotence persisté `public.webhook_events`, unique `(source, event_id)` ; réponse mémorisée | `WebhookEventRegistry` (#5444) + `WebhookIdempotenceTest` |
| A3 | Timestamp expiré | Message ancien rejoué | Fenêtre de rejeu 300 s (Stripe) ; optionnelle mais vérifiée si l'en-tête présent (email-bounce, marketing-lead) | `InboundWebhookVerifier::timestampIsFresh()` |
| A4 | Payload trop grand (DoS mémoire) | Corps > 1 MiB | Rejet `413` avant parse | `InboundWebhookVerifier::payloadWithinLimit()` |
| A5 | JSON invalide / malformé | Corps illisible | Rejet `400` avant validation Laravel | `InboundWebhookVerifier::isJsonPayload()` |
| A6 | Provider inconnu (spoofing de source) | Source/connection falsifiée | Allowlist serveur des providers (la source est un identifiant de connexion configuré, jamais un champ payload) | `InboundWebhookVerifier::isKnownProvider()` |
| A7 | Déni de service (rafale) | Flood d'appels | Throttle `webhooks-inbound` 60/min/IP + timeout HTTP 10 s sortant | `AppServiceProvider` (§192), `config/security.php` |
| A8 | SSRF sortant | URL d'endpoint privée | `NotPrivateUrl` à la création/update + re-vérification DNS à l'envoi | `StoreWebhookEndpointRequest`, `DispatchWebhook::handle()` |
| A9 | Exfiltration de secret | Logs/fixtures/base | Secrets en config uniquement ; `WebhookEndpointResource` masque le secret ; aucune entrée en clair dans les logs | `WebhookEndpointResource`, conventions PR |
| A10 | Rotation de secret ratée | Ancien secret toujours actif | Rotation = nouvelle valeur en config + test négatif de l'ancien ; signatures HMAC vérifiées contre le secret courant | `InboundWebhookVerifierTest` |
| A11 | Tenant cross-tenant via payload | `company_id` forgé | Aucun tenant lu dans le payload ; résolution post-verification seulement ; endpoints sortants 404 cross-tenant | `OnlinePaymentService`, `WebhookController` (#2654/#3949) |
| A12 | Événement inconnu | Nom d'événement arbitraire | Allowlist des événements (validation `in:`) ; catalogue sortant gardé par CI | `EmailBounceWebhookController` (validation), `check-webhook-event-catalog.sh` (#5744) |

## 3. Cycle de vie d'un webhook entrant (contrat)

```
Provider ──POST──▶ Frontière (throttle) ──▶ 1. Signature (HMAC/secret)
                                             2. Bornes : taille, JSON, fenêtre
                                             3. Persistance idempotence (begin)
                                             4. Validation + résolution tenant (post-auth)
                                             5. Effets métier (complete/release)
                                             6. Accusé de réception
```

- L'étape 3 **précède** l'étape 5 : un crash entre les deux ne produit ni
  perte (le rejeu rejoue) ni doublon (l'événement est déjà enregistré).
- L'étape 4 est la SEULE qui résout le tenant, et uniquement après
  authentification réussie.

## 4. Secret handling

| Secret | Emplacement | En base ? | Logs ? | Rotation |
|---|---|---|---|---|
| Stripe webhook secret | `STRIPE_WEBHOOK_SECRET` (config) | Non | Jamais | Env + test négatif |
| Chargily webhook secret | `CHARGILY_WEBHOOK_SECRET` (config) | Non | Jamais | Env + test négatif |
| Email-bounce shared secret | `MAIL_BOUNCE_WEBHOOK_SECRET` (config) | Non | Jamais | Env + test négatif |
| Marketing-lead token | `MARKETING_LEAD_WEBHOOK_TOKEN` (config) | Non | Jamais | Env + test négatif |
| Secret d'endpoint SORTANT | `webhook_endpoints.secret` (aléatoire `Str::random(40)`) | Oui (nécessaire pour signer à l'envoi) | Jamais (masqué par la ressource API) | Régénération via update endpoint |

> Le secret sortant vit en base par conception (signature HMAC à la livraison).
> Risque accepté et atténué : valeur aléatoire par endpoint, jamais exposée
> par l'API (`WebhookEndpointResource`), rotation possible à tout moment.
> Les secrets entrants, eux, ne sont JAMAIS en base.

## 5. Implémentation de référence

`InboundWebhookVerifier` (`api/app/Shared/Services/`)
fournit les primitives pures couvertes par `InboundWebhookVerifierTest` :

- `secretMatches()` / `verifyHmacSignature()` — authentification
- `timestampFromHeader()` / `timestampIsFresh()` — fenêtre de rejeu (300 s)
- `payloadWithinLimit()` — taille max (1 MiB)
- `isJsonPayload()` — forme JSON
- `isKnownProvider()` — allowlist provider/connection

Appliqué aux endpoints entrants à secret partagé (`EmailBounceWebhookController`,
`MarketingLeadController`) ; couverture `WebhookThreatModelTest`.

## 6. Runbook — incident webhook

1. **Signature invalide en rafale** → vérifier la rotation de secret ; le
   provider utilise-t-il le nouveau secret ? (contrôle A10)
2. **Rejeu massif** → vérifier `public.webhook_events` (source, event_id) ;
   si absent, l'événement n'a jamais été persisté → investiguer le crash
   entre étapes 3 et 5.
3. **413 en rafale** → payload anormal (> 1 MiB) : le provider a changé de
   format ? (contrôle A4)
4. **4xx systématiques** → valider la fenêtre horaire (A3), le secret (A1),
   la forme JSON (A5).
5. **Tenant erroné suspecté** → vérifier qu'aucune résolution tenant n'a eu
   lieu avant l'étape 4 (contrôle A11) ; contacter l'ops avant toute
   correction de code.
