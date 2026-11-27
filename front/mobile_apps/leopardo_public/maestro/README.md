# Flows Maestro — parcours commande restaurant

Tests E2E du parcours réel de commande de la verticale restaurant, exécutés sur
un **appareil ou un émulateur Android** contre l'app `leopardo_public`.

## Pourquoi Maestro et pas seulement les tests widget

Les tests widget (`test/restaurant_order_test.dart`) vérifient la logique d'un
écran avec un serveur simulé. Ils ne voient **jamais** ce qui casse en vrai :
un clavier qui masque le bouton de validation, un `ListView` qui ne fait pas
défiler jusqu'au champ téléphone, une route profonde qui n'ouvre pas l'écran.
Maestro pilote l'app installée, donc il voit exactement ce que voit le client.

## Prérequis

```bash
# Maestro (une fois)
curl -Ls "https://get.maestro.mobile.dev" | bash
export PATH="$PATH:$HOME/.maestro/bin"

# Un émulateur ou un appareil branché
adb devices
```

## Lancer

```bash
# Depuis front/mobile_apps/leopardo_public
make e2e-restaurant          # tous les flows, dans l'ordre
make e2e-restaurant-smoke    # seulement le parcours nominal
```

Ou directement :

```bash
maestro test maestro/restaurant/
```

## Variables d'environnement

Les flows ne contiennent **aucun identifiant d'établissement en dur** : un slug
codé dans un flow casse dès que la base de démonstration change. Ils lisent :

| Variable | Défaut | Rôle |
|---|---|---|
| `RESTO_SLUG` | `le-bistrot` | Slug de l'établissement de test |
| `RESTO_PRODUCT_CODE` | *(premier produit)* | Code catalogue d'un produit à ajouter |

```bash
RESTO_SLUG=mon-resto maestro test maestro/restaurant/
```

## Ce que couvrent les flows

| Fichier | Parcours |
|---|---|
| `01_fiche_et_menu.yaml` | Ouverture de la fiche par lien profond, en-tête, menu, recherche, filtre catégorie |
| `02_panier_et_commande.yaml` | Ajout au panier, quantités, saisie client, validation |
| `03_paiement_et_suivi.yaml` | Choix du mode de paiement, paiement, suivi du statut |

## Conventions

- **Aucun sélecteur par texte traduit.** Les flows visent les `ValueKey`
  exposées par `lib/features/journeys/domain/restaurant_test_keys.dart`. Un
  libellé change de langue, une clé ne change pas.
- **Un flow = un parcours**, pas un écran : chaque fichier part de l'état
  laissé par le précédent (`launchApp` avec `clearState: false`).
- **`assertVisible` après chaque action qui change d'écran** : un flow qui
  clique sans vérifier ne prouve rien.
