# Leopardo POS Retail — tablette de caisse

Application **Flutter** de caisse pour le commerce de detail (BC-17 RETAIL),
compagnon natif du POS web `front/web/src/app/commerce/pos`.

## Pourquoi une application native

Le POS web fonctionne, mais il ne peut pas tenir une caisse sans reseau :
un navigateur n'a pas de base locale durable. Le natif apporte exactement ce
qui manquait :

- **base locale Hive** — catalogue, panier et file d'operations survivent a la
  fermeture de l'application et a une coupure reseau ;
- **file d'encaissement idempotente** — un encaissement hors ligne est rejoue
  a la reconnexion sans jamais debiter deux fois (cle stable, meme contrat que
  le backend `idempotency_key`) ;
- **ergonomie tablette** — cibles tactiles >= 48 dp, grille produit large,
  pavé de quantite, mode sombre pour les boutiques peu eclairees.

## Profils servis

| Profil | Ce qu'il fait dans l'application |
|---|---|
| **Vendeur / caissier** | ouvre la caisse, scanne ou cherche un produit, encaisse (especes, carte, mobile money), rembourse, gere le tiroir-caisse |
| **Gerant de commerce** | tout ce qui precede + cloture de caisse avec ecart, rapports de vente, valorisation du stock |

Le RBAC est celui du backend (#9207) : le vendeur tient la caisse via une
assignation `retail_location` au niveau `operate` ; les rapports exigent
`manage`. L'application ne decide jamais des droits — elle affiche ce que
l'API autorise.

## Structure

```
lib/
  core/
    api/       client HTTP offline-first
    models/    modeles purs (testables sans moteur graphique)
    offline/   file d'encaissement idempotente (Hive)
    storage/   cache local (catalogue, panier, session)
    theme/     theme tablette tactile
  features/pos/
    open_session_screen.dart   ouverture de caisse
    retail_pos_screen.dart     caisse 3 colonnes (categories | produits | panier)
    cash_movement_sheet.dart   mouvements de tiroir-caisse
    refund_sheet.dart          remboursement
```

## Tests

```bash
flutter test
```

Les tests couvrent les regles qui font perdre de l'argent si elles cassent :
totaux du panier, remise bornee, attendu de caisse, validite d'un
remboursement, stabilite de la cle d'idempotence, recherche produit et
correspondance code-barres.
