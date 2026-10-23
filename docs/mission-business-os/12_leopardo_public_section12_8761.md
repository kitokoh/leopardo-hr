## 12. Correction #8761 — une action n'est exposée que si le client peut l'atteindre

**Contexte de session** : `main` @ `94e2fdf44`. Branche `fix/8761-action-targets`.
PR **#8831**. Aucune migration, aucune variable d'environnement, aucune dépendance.

### 12.1 Le défaut, tel qu'il était réellement

Le parcours public livré dans §3–§11 fonctionnait, mais **#8761 n'était pas satisfaite**
dans son critère n°1 (« la demande arrive au bon module »).

`PublicDiscoverableProvider::supportedActions()` prouvait qu'un **service métier existe**.
Il ne prouvait pas que le **client peut l'atteindre**. La passerelle liait le CTA métier à
`item.url` — la fiche publique — en supposant qu'elle contenait le parcours.

Vérification sur les six verticales :

| Verticale | Action déclarée | `item.url` | Parcours présent sur la fiche ? |
|---|---|---|---|
| Restaurant | `order` | `/restaurants/{slug}` | ✅ `RestaurantOrderPanel` (menu → panier → `POST /restaurant/public/orders` → suivi) |
| Hospitality | `booking` | `/stay/{slug}` | ✅ `StayBookingPanel` (→ `POST /public/hospitality/.../reservations`) |
| **Retail** | **`order`** | `/boutiques/{slug}` | ❌ **fiche profil seule : nom, ville, note, catégories — aucun panier** |
| VTC | — | `/operateurs/{slug}` | — |
| Showcase | — | `/vitrine/{slug}` | — |
| Trade | — | `/entreprises/{slug}` | — |

L'endpoint `POST /api/v1/public/market/orders` existe bien (checkout invité, idempotent,
`RetailMarketOrderPublicController`). Mais le parcours d'achat vit dans
**`front/marketplace`** — une application Next.js séparée (`/boutiques/{slug}`, `/panier`,
`/commande`), déployée sur une **autre origine** (`leopardo-marche`, cf.
`docs/ops/DEPLOYMENT_URLS.md`). Elle n'est pas atteignable depuis `front/web`.

Le bouton « Commander » de `/decouvrir` menait donc à une fiche sans moyen d'acheter :
**exactement le bouton inopérant interdit par #8761**, déguisé en lien valide.

La garde n°4 existante (`test_declared_actions_are_backed_by_a_public_route`) ne pouvait
pas le voir : elle vérifie qu'une **route API** existe, pas qu'une **route navigateur**
est atteignable.

### 12.2 Choix technique

Un second axe au contrat de découverte — et non une liste de cas particuliers côté front,
qui aurait recréé la seconde source de vérité déjà éliminée par #8761.

```php
// PublicDiscoverableProvider
public function supportedActionUrls(): array; // action => URL relative | null
```

- URL **relative** à l'origine publique, gabarit `{slug}` (une origine en dur casserait
  les environnements).
- **clé absente** ⇒ action non atteignable depuis la passerelle ;
- **valeur `null`** ⇒ action servie par la passerelle elle-même (`contact`, formulaire local).

`PublicDiscoveryService::actionUrlsByType()` compose `meta.action_urls`, avec un filtre
**strict** : une destination pour une action non déclarée est ignorée — jamais promue en
capacité. Fail-closed : pas d'entrée ⇒ le frontend affiche l'état explicite.

Déclarations retenues : Restaurant `order → /restaurants/{slug}#commander`, Hospitality
`booking → /stay/{slug}#reserver`, **Retail : aucune destination**, VTC/Showcase/Trade :
contact seul. Les ancres `#commander` et `#reserver` ont été ajoutées sur les panneaux
cibles pour que le deep-link atterrisse sur le formulaire.

Frontend : `actionHref()` substitue `{slug}` (encodé) ; les CTA `order`/`booking` ne
lisent plus `item.url` ; sans destination ⇒ « commande non disponible » /
« réservation non disponible ». Le libellé Retail est donc désormais **exact**.

### 12.3 Fichiers modifiés

| Fichier | Nature |
|---|---|
| `api/app/Shared/Contracts/Discovery/PublicDiscoverableProvider.php` | + `supportedActionUrls()` |
| `api/app/Shared/Discovery/PublicDiscoveryService.php` | + `actionUrlsByType()`, `meta.action_urls` |
| `api/app/Modules/RestaurantManager/.../RestaurantDiscoverableProvider.php` | destination `order` |
| `api/app/Modules/HospitalityManager/.../HospitalityDiscoverableProvider.php` | destination `booking` |
| `api/app/Modules/Retail/.../RetailDiscoverableProvider.php` | **aucune destination `order`** |
| `api/app/Modules/{Vtc,Showcase,Trade}/.../*DiscoverableProvider.php` | contact seul |
| `api/tests/Feature/LeopardoPublic/PublicDiscoverableProviderContractTest.php` | gardes n°6 et n°7 |
| `front/web/src/lib/public-discovery-api.ts` | type `action_urls` |
| `front/web/src/app/decouvrir/discovery-explorer.tsx` | `actionHref()`, CTA recâblés |
| `front/web/src/app/decouvrir/__tests__/discovery-explorer.test.tsx` | 3 tests (dont Retail indisponible) |
| `front/web/src/app/restaurants/[slug]/restaurant-order-panel.tsx` | ancre `#commander` |
| `front/web/src/app/stay/[slug]/stay-booking-panel.tsx` | ancre `#reserver` |

**Aucun fichier des surfaces employé / manager / administration n'est touché.**

### 12.4 Tests exécutés (sorties réelles, commandes lancées dans ce run)

| Commande | Résultat |
|---|---|
| `php artisan test --filter=PublicDiscoverableProviderContractTest` | **7 passed (105 assertions)** |
| `php artisan test --filter=PublicDiscoveryTest` | **32 passed (143 assertions)** |
| `php artisan test --filter=RetailPublicDiscoveryTest` | **11 passed (45 assertions)** |
| `php artisan test --filter=PublicContactRequestTest` | **10 passed (35 assertions)** |
| `php artisan test --filter='PublicParcoursEndToEndTest\|PublicConversationTest'` | **37 passed (124 assertions)** |
| `npx jest src/app/decouvrir` | **23 passed / 3 suites** |
| `npm run typecheck` | exit 0 |
| `npm run lint` (`--max-warnings 0`) | exit 0 |
| `npm run lint:e2e` | exit 0 |
| `check:mojibake` | `OK — no double-encoded UTF-8 found` |
| `check:assets` | `5 asset(s) OK` |
| `check:orphans` | `27 pages (landing) : toutes sont liées en interne` |
| `check:french-copy` | `OK — aucune forme interdite` |
| `shared/i18n/validators/validate.js` | `I18N_VALIDATION_OK (4 locales)` |
| `check-i18n-catalog-parity.sh` | parité OK (backend `api/lang`, `shared/i18n`, ARB mobile, 4 langues) |
| `check-i18n-locale-parity.py` | parité OK fr/en/ar/tr — 7032 clés, 0 manquante |

Note d'environnement : le sandbox frais ne résolvait pas l'hôte `pgsql` attendu par
`tests/TestCase.php` ; rétabli via `/etc/hosts` (`127.0.0.1 pgsql`). **Aucune
modification du dépôt pour cela.**

### 12.5 Matrice issue → statut (périmètre #8758–#8762)

| Issue | Portée | Implémenté | Testé | Intégré | Déployé | Disponible |
|---|---|---|---|---|---|---|
| **#8758** accueil `/decouvrir` | page SSR | ✅ | ✅ | ✅ fusionnée | ❌ | ⚠️ voir 12.6 |
| **#8759** résultats unifiés + liens | liste, `item.url`, pagination ≤ 50 | ✅ (déjà sur `main`) | ✅ | ✅ | ❌ | ⚠️ |
| **#8760** contact / messagerie | formulaire + conversation | ✅ | ✅ (10 + 37) | ✅ | ❌ | ⚠️ |
| **#8761** CTA service conditionnel | **corrigé ici** (§12.1–12.3) | ✅ | ✅ (7 gardes + 23 Jest) | 🔄 **PR #8831** | ❌ | ❌ |
| **#8762** tests de la passerelle | Jest + E2E | ✅ | ✅ | ✅ | ❌ | ⚠️ |

### 12.6 Ce qui n'est pas fait, et pourquoi

1. **Retail `order` reste sans destination.** C'est un constat, pas un oubli : aucun panier
   n'existe dans `/boutiques/{slug}`, et `front/marketplace` est une autre application sur
   une autre origine. Débloquer demande soit un panier dans la passerelle, soit une origine
   marketplace configurée/validée — dans les deux cas avec un test. Non fait ici : cela
   sort du périmètre de #8761 (un correctif de contrat, pas une nouvelle surface d'achat).
2. **Fusion dans `main` non effectuée.** La PR #8831 est ouverte ; la fusion dépend de la
   CI verte **et** de l'autorisation explicite du propriétaire (règle de la mission).
3. **`main` reste rouge sur la dette préexistante** — `Déploiement aligné (dev)/(prod)`,
   `Sonde prod + watchdog`, `Analyze leopardo_hr/*`, `Desktop verify`,
   `Tests module Accounting`, `Backend`. Non masqué, non corrigé ici : lot infra dédié.
4. **Les ~95 autres issues ouvertes du dépôt** sont hors périmètre de cette mission
   (Leopardo Public = #8758–#8762) et n'ont pas été touchées.

### 12.7 Risques restants

- `meta` gagne une clé `action_urls` : purement additif, aucun client existant ne la lit,
  aucune assertion stricte sur `meta` dans le dépôt.
- Changement de comportement assumé pour Retail : `order` n'est plus un lien. C'est la
  correction voulue ; le risque est produit (un client ne peut pas commander depuis
  `/decouvrir`) et non technique — il était déjà vrai, mais silencieux.
- Aucun déploiement, aucun secret touché, aucune migration.

### 12.8 Statuts globaux après ce run

| Statut | Éléments |
|---|---|
| **Implémenté** | Contrat `supportedActionUrls()` + `meta.action_urls` ; CTA recâblés ; ancres des panneaux |
| **Testé** | 60 tests backend (7+32+11+10) + 37 parcours/conversation + 23 Jest + 4 gardes + 3 contrôles i18n, **tous verts** |
| **Intégré** | Sur la branche `fix/8761-action-targets` ; **PR #8831 ouverte, non fusionnée** |
| **Déployé** | ❌ Non |
| **Disponible pour les utilisateurs** | ❌ Non (PR non fusionnée) |
