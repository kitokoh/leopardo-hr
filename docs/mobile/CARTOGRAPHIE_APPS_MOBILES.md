# Cartographie des applications mobiles — qui utilise quoi, et ce qui n'est pas encore fait

> **Statut** : source de vérité du 2026-11-23 (#9883). Les chiffres sont **mesurés**
> sur `main` et non estimés : `find lib -name '*.dart'`, `find lib -name '*_screen.dart'`,
> `find test -name '*_test.dart'`. Toute ligne de ce document qui ne correspond plus
> au disque est un défaut de ce document, pas une nuance.

## 1. Deux canaux, une seule règle : chaque type d'utilisateur a UNE app

| | Grand public | Professionnel |
|---|---|---|
| Qui | visiteur, client final, candidat | salarié, manager, RH, comptable, marketing, agent, caissier, responsable caméras, super-admin |
| Thème | identité de **surface** par verticale (ADR-0035) | marque du **tenant** (`TenantTheme`) |
| Modèle d'accès | `user_api` (client), consultation sans compte | `sanctum` (employé) ou guard plateforme |
| Doctrine | ADR-0034 (web) + ADR-0035 (mobile) | ADR-0035, règle de couche uniquement |
| Nombre d'apps | **1** (`leopardo_public`) | **10** |

Une app par persona, pas une app fourre-tout : c'est ce qui permet des identités
store distinctes, des permissions distinctes et des cycles de release distincts.

## 2. Matrice persona → application (état mesuré)

### Grand public

| Persona | App | Identifiant | Plateformes | Contrepartie web | État mesuré |
|---|---|---|---|---|---|
| Visiteur / client final / candidat | `leopardo_public` | `com.leopardo.publicapp` | Android + iOS | `front/web/src/app/decouvrir`, `(public)`, `[companySlug]`, 8 verticales `public-surfaces` | 64 fichiers Dart, 17 fichiers de test, 8 verticales au **registre des parcours** (`lib/features/journeys/domain/journey_registry.dart`) |

**Conformité au web — ce qui est aligné** : les 8 verticales (`restaurant`, `retail`,
`hospitality`, `trade`, `travel`, `pharmacy`, `vtc`, `careers`), leurs `basePath`
(`/restaurants`, `/boutiques`, `/stay`, `/entreprises`, `/billets`, `/pharmacies`,
`/operateurs`, `/emploi`) et leurs domaines canoniques sont **verrouillés par un test**
côté mobile et identiques au registre web. Une verticale inconnue rend `null`
(fail-closed), jamais une identité par défaut.

**Parcours transactionnels — état MESURÉ depuis #9907** :

Le registre `journey_registry.dart` porte les 8 verticales, leur type de
découverte, le parcours disponible et **le motif exact de ce qui manque**.
`test/journey_registry_test.dart` refuse un registre incomplet, un type
dupliqué, un motif sans clé i18n (4 locales) ou un `contactTargetType` que la
passerelle de demande refuserait.

| Verticale (`/…`) | Type `public.discover` | Parcours mobile | Ce qui reste bloqué, et pourquoi |
|---|---|---|---|
| `/restaurants` | `restaurant` | **annuaire + fiche + demande** natifs | la **commande** exige `X-Restaurant-Shop-Token`, un jeton par tenant émis au back-office |
| `/boutiques` | `retail_shop` | **annuaire + fiche + commande + suivi** natifs (catalogue → panier → `POST /public/market/orders` → suivi par référence + jeton) | — |
| `/stay` | `hospitality_property` | **annuaire + fiche + réservation + suivi** natifs (dates → disponibilités → `POST …/reservations` → suivi par code) | — |
| `/entreprises` | `trade_company` | **annuaire + fiche + demande de devis** natifs | le devis **structuré** (lignes, quantités, incoterm) n'existe pas dans le contrat public |
| `/pharmacies` | `pharmacy` | **annuaire + fiche** natifs, **lecture seule** | la **prise de contact** : `PublicProfileLocator::types()` ne contient pas `pharmacy`, une demande serait un 422 |
| `/operateurs` | `vtc_operator` | **annuaire + fiche + devis + course + suivi** natifs | — (la course exige `pickup`/`dropoff` en lat/lng : l'écran les demande comme la vitrine web, il ne géocode pas) |
| `/billets` | `travel` | **recherche + fiche + passagers + réservation + paiement + suivi** natifs | — |
| `/emploi` | `careers` | **annuaire + offres + candidature + suivi** natifs | — |

Autrement dit : **les 8 verticales ont désormais un parcours natif**, et **6 sont
couvertes de bout en bout**. Les 2 blocages restants ne sont pas des oublis : ils
sont nommés à l'écran (clé i18n du registre) et listés ici pour la prochaine
tranche serveur. Le blocage `pharmacy` a été réduit de 2 motifs à 1 : le motif
`catalog` était **périmé** (le provider public sert bien les officines opt-in),
seul `contact` reste vrai.

**Historique des livraisons** : #9943 (travel), #9944 (careers), #9945 (pharmacy),
#9948 (restaurant, retail, hospitality, trade, vtc), #9958 (paiement Billets),
#9959 (commande restaurant par slug), #9961 (suivi de paiement Billets).

### Professionnel — apps RH et gestion

| Persona | App | Identifiant | Plateformes | Contrepartie web | État mesuré |
|---|---|---|---|---|---|
| Salarié | `leopardo_employee` | `com.leopardo.employee` | Android + iOS | `(dashboard)` espace employé | 55 Dart, 22 écrans, 3 tests |
| Manager / responsable d'équipe | `leopardo_manager` | `com.leopardo.manager` | Android + iOS | `(dashboard)` équipe, validations | 40 Dart, 18 écrans, 16 tests |
| RH | `leopardo_hr` | `com.leopardo.rh` | Android + iOS | `(dashboard)` RH | 10 Dart, 3 écrans, 11 tests |
| Comptabilité | `leopardo_accounting` | `com.leopardo.accounting` | Android + iOS | `(dashboard)` facturation | 14 Dart, 5 écrans, 3 tests — écran stats encore mock (déclaré) |
| Marketing / communication | `leopardo_marketing` | `com.leopardo.marketing` | Android + iOS | `modules/marketing` | 11 Dart, 5 écrans, **2 fichiers de test (11 cas)** (#9963) — écran stats encore mock (déclaré) |
| Super-admin plateforme | `leopardo_platform_admin` | `com.leopardo.platformadmin` | Android + iOS | `front/admin-dashboard` + `/platform/*` | 15 Dart, 9 écrans, 1 test |
| Agent / vendeur TravelAgency | `leopardo_travel_agent` | `com.leopardo.travel_agent` | **Android** | `(dashboard)` travel, `/billets` | 18 Dart, 8 écrans, 3 tests |
| Responsable caméras (BC-19) | `leopardo_cameras` | `com.leopardo.cameras` | **Android** | `(dashboard)` caméras | 16 Dart, 5 écrans, 3 tests |
| Caissier retail (BC-17) | `leopardo_pos_retail` | `com.leopardo.posretail` | Android + iOS | `commerce/pos` | 15 Dart, 3 écrans, 4 tests |
| Serveur / caissier restaurant (BC-25) | `leopardo_pos_restaurant` | `com.leopardo.posrestaurant` | Android + iOS | `(dashboard)/restaurant/pos` | 12 Dart, 2 écrans, 5 tests |

`leopardo_core` (180 fichiers Dart) n'est **pas** une app : c'est le package partagé
(API client, stockage, i18n, thème, widgets, modèles). Il est exclu de cette matrice
et de la garde d'identités.

## 3. Surfaces qui n'ont volontairement PAS d'app mobile

Ces surfaces sont **web-only par décision**, pas par oubli. Les ajouter au mobile
exigerait une décision produit, pas un correctif :

| Surface | Pourquoi pas de mobile |
|---|---|
| Console d'administration du tenant (`(dashboard)`) | densité de tableaux et de réglages ; le mobile porte les parcours, pas les consoles |
| Kiosque de pointage (`/kiosk`) | tourne sur un appareil partagé, en web, avec `front/zkteco-kiosk` |
| `guardian-portal` | accès ponctuel par lien, pas un usage quotidien |
| Bibliothèque de contenus (`modules/library`) | lecture longue, hors du périmètre mobile |
| Vitrine commerciale (`modules/vitrine`) | site public de vente, tenu par l'agent Vitrine (verrou d'exclusivité) |
| Cagnottes, conversations, documents | accessibles depuis l'app employé, sans app dédiée |

## 4. « Rien ne doit être oublié » — ce qui est désormais **opposable**

Quatre registres et une garde rendent l'oubli impossible à introduire en silence :

| Mécanisme | Fichier | Ce qu'il empêche |
|---|---|---|
| Registre de couche | `dev-hub/tools/mobile-layer-registry.txt` | une app sur disque non classée `flat`/`layered` |
| Registre de plateformes | `dev-hub/tools/mobile-platform-registry.txt` | un dossier `ios/` supprimé ou non déclaré ; une plateforme déclarée sans dossier |
| Matrice d'analyse CI | `.github/workflows/mobile-apps-ci.yml` | une app jamais analysée ni testée |
| Matrices de distribution | `mobile-distribute-main.yml`, `mobile-distribute.yml` | une app jamais construite en APK |
| **Garde d'identités** | `dev-hub/tools/check-mobile-app-identities.sh` | deux apps qui partagent un `applicationId` ; Android ≠ iOS ; app hors registre/matrice |

La garde porte un **auto-test** (`--self-test`) et tourne dans
`.github/workflows/mobile-layer-guard.yml`, sans SDK Flutter (bash pur, quelques secondes).

### Ce que la garde a trouvé le 2026-11-23 (avant correctif)

| Défaut | Effet réel |
|---|---|
| `leopardo_marketing` → `applicationId "com.leopardo.accounting"` | deux apps, un seul identifiant : installation côte à côte impossible, et le paquet publié s'appelait `com.leopardo.accounting` |
| marketing : Android `com.leopardo.accounting` vs iOS `com.leopardo.leopardoMarketing` | deux identités pour une app |
| marketing : `namespace` Kotlin = `com.leopardo.leopardo_accounting`, titre de fenêtre desktop = `leopardo_accounting` | build sur un paquet qui appartient à une autre app |
| POS retail/restaurant : Android `…_pos_retail` vs iOS `…PosRetail` | identités divergentes ; l'underscore est de toute façon interdit dans un bundle id iOS |
| 6 apps hors de toute matrice de distribution (marketing, travel_agent, cameras, public, pos_retail, pos_restaurant) | aucun APK jamais produit pour ces personas |
| `FIREBASE_APP_ID` retombait sur un secret **générique** | une app sans secret dédié pouvait hériter de l'identifiant Firebase d'une autre |
| ternaire d'app id de l'upload (`appId:`) et de la relecture resté sur l'ancienne chaîne | l'app id **validé** (`mobilesdk_app_id`) et l'app id **utilisé** pouvaient différer |

**Exception mesurée le 2026-11-24** : `FIREBASE_APP_ID_HR` est absent des
secrets du dépôt (`gh secret list`) et l'identité Firebase de `leopardo_hr`
**est** le secret historique `FIREBASE_APP_ID`. La branche `hr` du ternaire lit
donc `secrets.FIREBASE_APP_ID_HR || secrets.FIREBASE_APP_ID` : supprimer ce
repli arrêterait un canal qui fonctionne aujourd'hui. La garde exige une
branche par app de matrice et interdit le repli générique **en fin** de chaîne
(celui qui s'appliquerait à toutes les apps).

## 5. Ce qui reste à faire, et par qui

| Reste à faire | Bloquant ? | Qui |
|---|---|---|
| Enregistrer 6 apps dans Firebase + créer `FIREBASE_{MARKETING,TRAVEL_AGENT,CAMERAS,PUBLIC,POS_RETAIL,POS_RESTAURANT}_ANDROID_APP_ID` | oui pour l'installation testeur | propriétaire (console Firebase) |
| Créer `FIREBASE_APP_ID_HR` pour retirer le repli documenté de `hr` | non (le canal fonctionne sur `FIREBASE_APP_ID`) | propriétaire (secrets du dépôt) |
| Parcours transactionnels restants côté `leopardo_public` : commande restaurant (jeton tenant), devis B2B structuré, prise de contact pharmacie | non (les 8 verticales ont un parcours natif depuis #9943–#9961) | dev mobile **et** dev backend (ces 3 blocages exigent un contrat public) |
| Projets iOS de `leopardo_cameras` et `leopardo_travel_agent` | non (Android-only déclaré) | dev mobile, sur décision produit |
| Écrans stats de `leopardo_accounting` / `leopardo_marketing` encore mock | non (déclaré dans leurs README) | dev mobile |
| ~~Suite de tests de `leopardo_marketing` (0 fichier)~~ **fait en #9963** : 11 tests, dont l'invariant « toute cible poussée a une route » | — | livré |
| **Décision A/B** : une app mobile par verticale, ou une app + 8 parcours ? (voir §7) | oui pour toute nouvelle app | produit |

## 6. Comment ajouter une app (procédure complète)

1. `flutter create` dans `front/mobile_apps/leopardo_<persona>` avec un identifiant
   `com.leopardo.<persona>` **unique** (vérifier : `grep -r applicationId front/mobile_apps`).
2. Le même identifiant côté iOS (`PRODUCT_BUNDLE_IDENTIFIER`), sans underscore.
3. Déclarer l'app dans `mobile-layer-registry.txt` (`flat` par défaut) **et** dans
   `mobile-platform-registry.txt` (`android` ou `android,ios`).
4. L'ajouter à la matrice d'analyse de `mobile-apps-ci.yml` **et** aux matrices de
   distribution (`mobile-distribute-main.yml`, `mobile-distribute.yml`) avec un
   `firebase_secret` dédié.
5. Ajouter sa ligne dans la matrice §2 de ce document, avec l'état **mesuré**.
6. `bash dev-hub/tools/check-mobile-app-identities.sh` doit passer au vert.

Les étapes 3, 4 et 6 sont **vérifiées par la CI** : en oublier une fait échouer
`Mobile Layer Guard`, elle ne se perd pas dans une revue.

## 7. Décision A/B — une app par verticale, ou une app + 8 parcours ? (#9964)

Le web a **8 vitrines** (`SURFACE_VERTICALS` dans
`front/web/src/modules/public-surfaces/kernel/domain/surface-identity.ts`), le
mobile a **1 app** qui sert les 8 verticales. La parité **fonctionnelle** est
atteinte (§2) ; ce qui reste ouvert est la parité **produit**.

### Les deux cibles

| | A — 8 apps distinctes | B — 1 app + 8 parcours (état actuel) |
|---|---|---|
| Parité produit avec le web | exacte | non : un utilisateur « pharmacies » installe une app qui parle de restaurants |
| Coût de release | × 8 (identifiants, registres, matrices CI, secrets Firebase, fiches store) | × 1 |
| Maintenance du code | 8 apps pour un code aujourd'hui partagé | un seul binaire |
| Listing store | 8 fiches à publier et maintenir | 1 |

### Recommandation : **B aujourd'hui, A par exception**

Une app dédiée se justifie quand une verticale a **assez de trafic pour
supporter son propre listing** — c'est-à-dire quand un utilisateur qui cherche
« pharmacies » dans un store doit trouver une app qui ne parle que de ça. En
dessous de ce seuil, 8 apps multiplient par 8 le coût de release **sans
bénéfice utilisateur** : le même code, le même backend, la même équipe.

**La question à trancher est donc produit, pas technique** : *quelles verticales
ont un volume qui justifie un listing dédié ?* Tant qu'elle n'est pas tranchée,
B reste la cible, et `journey_registry.dart` reste la source de vérité des
parcours.

### Si A est retenu — procédure, et pourquoi elle n'est pas un copier-coller

1. **Extraire le socle commun** (découverte, fiches, contact) dans `leopardo_core`
   — il est déjà largement partagé.
2. **Créer UNE app pilote** (`leopardo_public_pharmacy`, la plus simple : lecture
   seule) pour valider la chaîne complète : identifiants, registres, matrices,
   distribution.
3. **Mesurer le coût réel** de la chaîne sur cette pilote avant de dupliquer 7 fois.
4. **Étendre verticale par verticale**, en gardant `leopardo_public` comme app
   « toutes verticales » tant que la migration n'est pas finie.

Les 6 étapes de §6 sont **obligatoires** pour chaque app, et `check-mobile-app-identity.sh`
refuse une app scaffoldée par copie qui garde l'identité de l'app d'origine —
c'est exactement ce qui est arrivé à `leopardo_public` (#9110).

**Ce qui n'est pas livré ici, et pourquoi** : aucune app pilote n'est créée dans
cette tranche. Créer une app exige un `applicationId` unique, 5 inscriptions
(2 registres, 2 matrices de distribution, 1 matrice d'analyse), un secret
Firebase dédié et un build Android complet pour valider — or la décision A/B
n'est pas prise. Livrer 8 apps sur une décision non prise serait le contraire
d'une livraison : ce serait 8 fois la dette, décidée par un agent.
