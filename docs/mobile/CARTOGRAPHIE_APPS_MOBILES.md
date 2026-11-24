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
| Visiteur / client final / candidat | `leopardo_public` | `com.leopardo.publicapp` | Android + iOS | `front/web/src/app/decouvrir`, `(public)`, `[companySlug]`, 8 verticales `public-surfaces` | 36 fichiers Dart, 3 écrans (découverte, fiche commerce, activité), 9 fichiers de test, 8 verticales au registre |

**Conformité au web — ce qui est aligné** : les 8 verticales (`restaurant`, `retail`,
`hospitality`, `trade`, `travel`, `pharmacy`, `vtc`, `careers`), leurs `basePath`
(`/restaurants`, `/boutiques`, `/stay`, `/entreprises`, `/billets`, `/pharmacies`,
`/operateurs`, `/emploi`) et leurs domaines canoniques sont **verrouillés par un test**
côté mobile et identiques au registre web. Une verticale inconnue rend `null`
(fail-closed), jamais une identité par défaut.

**Conformité au web — ce qui n'est PAS fait, et qui n'est pas masqué** :

| Parcours web | État mobile | Nature du travail |
|---|---|---|
| `/restaurants` : menu, commande, suivi | fiche + boutons d'action génériques | parcours de commande à écrire |
| `/boutiques` : catalogue, panier | fiche + boutons | panier/commande à écrire |
| `/stay` : disponibilité, réservation | fiche | réservation à écrire |
| `/billets` : recherche, achat | fiche | achat à écrire |
| `/entreprises` : demande de devis (B2B) | fiche | formulaire de devis à écrire |
| `/operateurs` : course VTC | fiche | course à écrire |
| `/pharmacies` : garde, produits | fiche | catalogue à écrire |
| `/emploi` : offres, candidature | fiche | liste d'offres + candidature à écrire |

Autrement dit : **le canal est défini et l'identité est conforme ; les parcours
transactionnels verticaux ne le sont pas encore.** C'est le chantier produit
principal du grand public, et il est listé ici pour ne pas être « oublié ».

### Professionnel — apps RH et gestion

| Persona | App | Identifiant | Plateformes | Contrepartie web | État mesuré |
|---|---|---|---|---|---|
| Salarié | `leopardo_employee` | `com.leopardo.employee` | Android + iOS | `(dashboard)` espace employé | 55 Dart, 22 écrans, 3 tests |
| Manager / responsable d'équipe | `leopardo_manager` | `com.leopardo.manager` | Android + iOS | `(dashboard)` équipe, validations | 40 Dart, 18 écrans, 16 tests |
| RH | `leopardo_hr` | `com.leopardo.rh` | Android + iOS | `(dashboard)` RH | 10 Dart, 3 écrans, 11 tests |
| Comptabilité | `leopardo_accounting` | `com.leopardo.accounting` | Android + iOS | `(dashboard)` facturation | 14 Dart, 5 écrans, 3 tests — écran stats encore mock (déclaré) |
| Marketing / communication | `leopardo_marketing` | `com.leopardo.marketing` | Android + iOS | `modules/marketing` | 10 Dart, 4 écrans, **0 test** — écran stats encore mock (déclaré) |
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
| Parcours transactionnels des 8 verticales côté `leopardo_public` | non (canal utilisable en consultation) | dev mobile |
| Projets iOS de `leopardo_cameras` et `leopardo_travel_agent` | non (Android-only déclaré) | dev mobile, sur décision produit |
| Écrans stats de `leopardo_accounting` / `leopardo_marketing` encore mock | non (déclaré dans leurs README) | dev mobile |
| Suite de tests de `leopardo_marketing` (0 fichier) | non | dev mobile |

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
