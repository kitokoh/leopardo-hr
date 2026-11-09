# Leopardo POS Restaurant — application tablette de caisse

Application **tablette native Flutter** du point de vente restaurant
(BC-25 RestaurantManager, issue #9214).

## Pourquoi une application native

Le POS restaurant existant est une page web Next.js
(`front/web/src/app/(dashboard)/restaurant/pos/page.tsx`) : elle n'est pas
tactile-first, ne verrouille pas l'écran, et son mode hors ligne est partiel
(cache catalogue + file d'encaissement, mais l'ouverture de caisse et la
création de commande exigent le réseau).

Une caisse réelle exige :

- un **plein écran verrouillé** (pas de barre d'adresse, pas de fuite vers
  un autre site) ;
- des **cibles tactiles** dimensionnées (≥ 48 dp) et un mode **paysage** ;
- une **base locale** (Hive) pour fonctionner sans réseau — c'est la raison
  principale du choix natif.

## Périmètre fonctionnel

| Écran | Rôle | État |
|---|---|---|
| Ouverture de caisse | Restaurateur / serveur | ✅ |
| Plan de salle (tables) | Serveur | ✅ |
| Prise de commande (catégories + produits) | Serveur | ✅ |
| Panier (quantités, note) | Serveur | ✅ |
| Encaissement (espèces, carte, mobile) | Serveur / caissier | ✅ |
| Ticket | Serveur | ✅ |
| File hors ligne | — | ✅ |

## Architecture

```
lib/
  core/            # thème tablette, client API, stockage local
  features/
    pos/           # ouverture de caisse, encaissement
    floor/         # plan de salle
    order/         # prise de commande, panier
    sync/          # file d'attente hors ligne
  app.dart         # routeur + coquille
  main.dart        # bootstrap
```

- **État** : Riverpod (`flutter_riverpod`).
- **Navigation** : `go_router`.
- **Persistance locale** : Hive (catalogue, tables, commandes en cours, file
  d'encaissement).
- **Réseau** : `dio` via `leopardo_core`.

## Mode hors ligne

Le catalogue, le plan de salle et les commandes en cours sont mis en cache
localement. Un encaissement hors ligne devient une **opération de file** dont
la clé d'idempotence est stable (commande + montant + moyen de paiement) : un
rejeu après reconnexion ne crée pas de double encaissement. C'est le même
contrat que le noyau web (`front/web/src/lib/offline/pos-offline.ts`).

**Portée honnête** : l'ouverture de caisse et la création de commande
exigent un identifiant serveur ; hors ligne, l'interface le dit
explicitement au lieu d'échouer silencieusement.

## Développement

```bash
flutter pub get
flutter analyze
flutter test
flutter run -d <tablet-device>
```

## Limites connues

- Pas encore de sélection de table depuis le plan de salle vers la commande
  (le plan est consultatif en v1).
- Pas d'impression ESC/POS embarquée (le pont `edge/print-bridge` reste la
  voie d'impression).
- Pas de gestion des pourboires ni des remises dans l'UI (le backend les
  accepte).
