# Conventions des surfaces publiques (commerce invité)

> BOS-050 (#8208) — conventions obligatoires pour toute surface publique
> transactionnelle (consultée ou écrite **sans compte utilisateur**) :
> travel shop, retail/market, catalog B2B, showcase/vitrine, restaurant shop,
> hospitality /stay. Ces conventions remplacent la copie historique du même
> plumbing dans 6+ implémentations ; le code mutualisé vit dans
> `api/app/Shared/Services/PublicCommerce/`.

## 1. Résolution du tenant — `PublicTenantResolver`

Toute surface publique résout le tenant **fail-closed** puis exécute la
requête dans son contexte :

```php
// Par slug public (route) :
$company = $publicTenantResolver->companyBySlug($slug, 'feature_verticale', $optIn);
// Ou pour une société déjà résolue via une ressource bornée (branche, billet…) :
$publicTenantResolver->assertAccessible($company, 'feature_verticale');
// Puis exécution dans le contexte tenant :
return $publicTenantResolver->withinTenant($company, fn (Company $tenant) => /* … */);
```

Règles **non négociables** :

1. **Échec uniforme** : slug inconnu, société `suspended`/`expired`, feature
   absente, opt-in non satisfait → **404** (jamais 403 — anti-énumération ;
   401 uniquement pour les surfaces à jeton où l'absence de credential est
   l'information attendue).
2. **Feature flag** : toute surface rattachée à une verticale exige son flag
   (`b2b_catalog`, `retail`, `restaurantmanager`, `hospitality`…). Le flag
   n'est jamais divulgué publiquement. `feature = null` est réservé aux
   surfaces historiques sans flag (showcase).
3. **Contexte tenant** : `withinTenant()` pose le marqueur
   `tenant_scope_required` et délègue à `TenantManager::withinTenant()`
   (scope `BelongsToCompany` actif → aucune fuite cross-tenant), avec
   restauration de l'état antérieur en `finally` (imbrication sûre).
4. Le tenant vient **toujours** de la résolution serveur (slug, jeton hashé,
   ressource bornée) — jamais d'un paramètre libre du client.

## 2. Idempotence des écritures invitées — `IdempotentGuestWrite`

Toute écriture publique invitée (commande, réservation, booking…) porte une
**clé d'idempotence client, unique par tenant**
(`unique(company_id, idempotency_key)`) :

```php
/** @var array{result: Order, created: bool} $replay */
$replay = $idempotentGuestWrite->replay(
    findExisting: fn () => Order::where('idempotency_key', $key)->first(),
    create: fn () => DB::transaction(fn () => /* création complète */),
);
// created = false → répondre 200 avec le MÊME payload qu'à la création.
```

- Rejeu (même clé) → la ressource initiale, jamais de doublon.
- Course perdue sur la contrainte (SQLSTATE **23505**) → relecture du
  résultat du gagnant.
- ⚠️ `replay()` s'appelle **hors transaction ouverte** : PostgreSQL avorte
  la transaction courante sur 23505 (la relecture doit se faire après
  rollback, sinon 25P02).

## 3. Secret de suivi — `TrackingSecretService`

Le suivi public d'une écriture invitée exige une **référence non énumérable
+ un secret**, jamais la référence seule :

```php
$secret = $trackingSecrets->generate(); // ['plain' => 64 hex, 'hash' => sha256]
// Persister UNIQUEMENT $secret['hash'] ; retourner 'plain' UNE SEULE FOIS
// dans la réponse de création (jamais dans les logs, jamais en clair en base).
$trackingSecrets->matches($presented, $storedHash); // hash_equals, timing-safe
```

- Un hash stocké vide/nul ne matche jamais (fail-closed).
- Référence inconnue ET secret invalide produisent le **même 404**.

## 4. Throttling

- Le bucket **`shop-public`** (défaut 30/min par IP, clé
  `shop_public_per_minute`) est **unique et partagé** par toutes les
  surfaces « shop » publiques : il est enregistré **une seule fois** dans
  `AppServiceProvider` (garde : `PublicRateLimiterRegistrationTest` — un
  second enregistrement écraserait silencieusement le premier).
- Une surface peut ajouter un bucket **strict dédié** pour ses actions
  sensibles (`restaurant-reviews-public`, `market-account-public`…), en
  plus du bucket partagé — jamais un second enregistrement du même nom.

## 5. Contrats de réponse

- DTO strict : aucun champ interne (coûts, stock précis, emails staff…).
- Montants en `*_minor` entiers ; prix **relus serveur** (jamais ceux du client).
- Les endpoints publics sont documentés dans `api/openapi.yaml`.

## État de la migration (tenu à jour par les PR de BOS-050)

| Surface | Résolution | Idempotence | Suivi | Migrée le |
|---|---|---|---|---|
| Travel shop (`EnsurePublicShopAccess`) | historique | historique | hash ✅ | — |
| Retail market (`EnsureMarketPublicAccess`) | historique | historique | clair (à hacher) | — |
| Catalog (`EnsureCatalogPublicAccess`) | historique | n/a (lecture+inquiry) | n/a | — |
| Showcase (`ShowcasePublicController`) | historique | n/a | token aperçu | — |
| Restaurant shop (`EnsureRestaurantPublicShopAccess`) | socle ✅ (tranche 7) | socle ✅ (tranche 7) | hash ✅ (tranche 7) | 2026-09-29 |
| Hospitality (`HospitalityPublicPropertyResolver`) | historique | historique | hash ✅ | — |

> Constat documenté (hors périmètre BOS-050, suivi à part) : le bucket
> `restaurant-shop-public` est enregistré dans `AppServiceProvider` mais
> n'est utilisé par **aucune** route — les routes restaurant utilisent
> `shop-public`. Retrait à traiter dans une issue de suivi.

## 4. Dépréciation — ancien flux de suivi restaurant (référence seule)

Avant la tranche 7 (2026-09-29), le suivi public restaurant se faisait par
la référence seule (boutique/kiosque) ou référence + slug (RESTO-902),
sans secret — maillon faible identifié dès l'audit. Toute commande créée
désormais via une surface invitée porte `tracking_secret_hash` (SHA-256)
et son suivi exige le secret (`?secret=` ou en-tête `X-Tracking-Secret`) ;
référence inconnue, secret absent et secret invalide produisent le MÊME
404.

**Fenêtre de transition de 90 jours** : les commandes créées AVANT la
tranche 7 (hash NULL), ainsi que celles écrites hors surfaces invitées
(POS, webhooks marketplace — jamais suivies publiquement par le client),
restent suivies par l'ancien flux ; la réponse porte alors les en-têtes
`Deprecation: true` et `Sunset: Mon, 28 Dec 2026 00:00:00 GMT`
(RFC 8594). Après le 2026-12-28, le flux legacy sera fermé (suivi sous
secret obligatoire, quelle que soit l'ancienneté de la commande) — retrait
à planifier dans une issue de suivi.
