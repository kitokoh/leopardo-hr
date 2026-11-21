# KIT DE RECETTE TERRAIN — RestaurantManager (R2–R11)

> **Issue :** #9725 — kit de recette terrain du pilote RestaurantManager (gate `recette`, MAT-018).
> **Complète :** [`RECETTE_UAT_RESTAURANTMANAGER.md`](./RECETTE_UAT_RESTAURANTMANAGER.md) (cahier de recette).
> **Ce que ce kit apporte :** le cahier décrivait *quoi* tester ; ce kit fournit *avec quoi* et *comment relever*.
> **Ce que ce kit ne fait pas :** il n'exécute pas la recette et ne la signe pas. L'exécution et la signature restent humaines.

---

## 1. Préparation de l'environnement

### 1.1 Provisionner le jeu de données

```bash
cd api
php artisan pilot:seed resto          # crée le tenant resto-pilot-001
php artisan pilot:seed resto --clean  # remet le tenant à zéro (avant chaque campagne)
```

Le seed est **idempotent** : relancé sur un tenant existant, il ne fait rien. Pour repartir d'un état
propre entre deux campagnes, utiliser `--clean` puis relancer.

### 1.2 Comptes de recette

Mot de passe commun : `recette123` (mot de passe de démonstration, jamais un secret de production).

| Rôle | Identifiant | Ce qu'il peut faire | Ce qu'il ne peut pas |
|---|---|---|---|
| Directrice (principal) | `principal@resto.recette.leopardo.test` | tout, y compris métriques et rapports | — |
| Gérant de succursale | `gerant@resto.recette.leopardo.test` | modération des avis, opérations de la succursale publique | métriques techniques, succursale privée |
| Serveur | `serveur@resto.recette.leopardo.test` | caisse, commandes, tables | modération, rapports, métriques |
| Livreur | `livreur@resto.recette.leopardo.test` | ses livraisons | tout le reste |

### 1.3 Surfaces à ouvrir

| Surface | Adresse | Usage dans la recette |
|---|---|---|
| Back-office | `/restaurant` | R2, R5, R7, R8, R9, R10 |
| Caisse tablette (Flutter) | app `leopardo_pos_restaurant` | R1 (déjà validé), R2 |
| Menu public | `/restaurants/chez-leo-recette` | R3, R6, R11 |
| API | `/api/v1/restaurant/*` | tous (vérifications de contrat) |

### 1.4 État initial garanti par le seed

| Domaine | Contenu |
|---|---|
| Succursales | 2 — `Chez Leo — Centre` (publique, slug `chez-leo-recette`), `Chez Leo — Nord` (privée) |
| Salle | 3 zones, 8 tables (T1–T8, capacités 2 à 10, T5 non fusionnable) |
| Carte | 6 produits dont **1 en rupture** (`PLAT-RUPTURE`), 1 menu publié (3 plats) |
| Stock | 4 ingrédients ; **`ING-HUILE` sous le seuil d'alerte** (8 kg pour un seuil de 15 kg) |
| Commandes | 7 — `open`, `in_preparation` ×2 (dont **1 bloquée depuis 2 h**), `ready`, `served`, `paid`, `closed` |
| Avis | 3 — 1 publié, **1 en attente**, 1 rejeté |
| Réservations | 2 — 1 confirmée (J+3 h), 1 en attente (J+1) |
| Livraison | 2 zones (100 000 / 250 000 minor), 1 livreur actif |
| Fidélité | 1 programme, 2 clients (120 et 40 points) |
| Promotion | `RECETTE10` — 10 %, minimum 500 000 minor, valide 30 jours |
| Stock achats | 1 fournisseur, 1 bon d'achat reçu, 1 réception |
| Caisse | 1 session POS ouverte (fond 5 000 000 minor) |
| Observabilité | 1 événement outbox en échec (2 tentatives) |

---

## 2. Checklist opérateur R2–R11

Chaque scénario suit le même format : **prérequis → étapes → attendu → critère de succès observable**.
Cocher ✅ ou ❌ dans la grille (§3). Une ❌ bloque la recette.

### R2 — Commande à emporter

- **Prérequis :** session POS ouverte ; produit `PLAT-POULET` disponible.
- **Étapes :**
  1. Caisse → nouvelle commande → type `takeaway`.
  2. Ajouter `PLAT-POULET` ×1.
  3. Envoyer en cuisine, marquer `ready`, puis `served`.
  4. Encaisser en espèces, clôturer.
- **Attendu :** statuts `open → in_preparation → ready → served → paid → closed`, sans 409 ni 422.
- **Critère de succès :** la commande apparaît `closed` dans le back-office, et le ticket de caisse
  affiche un total TTC égal à `total_minor` de l'API.

### R3 — Livraison complète

- **Prérequis :** zone `Centre-ville` active ; livreur `Moussa Livreur` actif.
- **Étapes :**
  1. Commande type `delivery`, adresse dans `Centre-ville`.
  2. Vérifier les frais appliqués automatiquement.
  3. Assigner le livreur, passer `out_for_delivery`, puis `deliver`.
- **Attendu :** frais = 100 000 minor (tarif de la zone, **jamais** saisi à la main) ; commande
  `served` après livraison.
- **Critère de succès :** le statut final est `served` (et **non** `closed`) ; le livreur redevient
  disponible ; le rejeu du même appel `deliver` ne crée pas de doublon (idempotence).

### R4 — Livraison annulée

- **Prérequis :** une commande `delivery` avec livreur assigné.
- **Étapes :** annuler la livraison avant `deliver`.
- **Attendu :** la commande retourne `ready` ; le livreur est libéré.
- **Critère de succès :** le livreur peut être réassigné immédiatement à une autre commande.

### R5 — Réservation + conflit de créneau

- **Prérequis :** table T2 libre sur le créneau visé.
- **Étapes :**
  1. Réserver T2 pour 4 couverts à J+3 h → confirmer.
  2. Réserver **la même table au même créneau**.
  3. Faire un check-in sur la première réservation.
- **Attendu :** la seconde réservation est refusée en **409** ; le check-in passe.
- **Critère de succès :** aucune double réservation possible ; le message d'erreur est explicite
  (créneau indisponible, pas une erreur 500).

### R6 — No-show et rappel

- **Prérequis :** une réservation confirmée dont l'heure est dépassée ; une réservation à J+1.
- **Étapes :** lancer le job de no-show ; lancer le job de rappel.
- **Attendu :** la réservation dépassée passe `no_show` **une seule fois** ; le rappel part **une seule fois**.
- **Critère de succès :** relancer les jobs ne produit ni second `no_show` ni second rappel
  (vérifier le journal des notifications).

### R7 — Stock et COGS

- **Prérequis :** `ING-POULET` à 40 kg ; une commande contenant `PLAT-POULET`.
- **Étapes :**
  1. Confirmer la commande.
  2. Ouvrir le stock : vérifier le mouvement généré.
  3. Ouvrir le rapport COGS.
- **Attendu :** un mouvement de stock est créé, **rattaché à la commande** (`reference_type = order`,
  `reference_id` = identifiant de la commande) ; le COGS du rapport correspond au coût moyen × quantité.
- **Critère de succès :** le mouvement est retrouvable en filtrant par la commande ; le COGS du
  rapport est égal au calcul manuel à ±1 minor.
- **Point de vigilance :** c'est exactement le défaut corrigé en #9714 (le mouvement existait mais
  portait un FQCN, donc était invisible au filtrage). Si le filtre par commande ne renvoie rien,
  la régression est de retour.

### R8 — Fidélité

- **Prérequis :** client fidèle à 120 points ; programme actif.
- **Étapes :**
  1. Encaisser une commande pour ce client.
  2. Vérifier le crédit de points.
  3. Tenter un échange supérieur au solde.
- **Attendu :** points crédités **une seule fois** (rejouer le paiement ne recrédite pas) ; échange
  au-delà du solde refusé en **422**.
- **Critère de succès :** le solde ne devient jamais négatif.

### R9 — Promotions

- **Prérequis :** code `RECETTE10` actif.
- **Étapes :**
  1. Appliquer `RECETTE10` sur une commande au-dessus du minimum.
  2. Appliquer sur une commande **sous** le minimum.
  3. Appliquer un code expiré.
- **Attendu :** remise bornée à 10 % sur la première ; **422** sur les deux suivantes.
- **Critère de succès :** la remise ne dépasse jamais le plafond, même sur un panier très élevé.

### R10 — Rapports et export

- **Prérequis :** les commandes du seed (7 commandes, dont 1 `paid` et 1 `closed`).
- **Étapes :**
  1. Ouvrir le rapport des ventes.
  2. Comparer au total des commandes payées.
  3. Exporter en CSV, puis rejouer l'export.
- **Attendu :** chiffres identiques aux données ; export rejouable donnant le **même fichier** ;
  URL signée valide 15 minutes.
- **Critère de succès :** le total du rapport égale la somme des `total_minor` des commandes
  `paid` + `closed` ; l'URL signée expire bien après 15 minutes.

### R11 — Sécurité et isolation

- **Prérequis :** deux tenants (le tenant de recette + un second tenant de test).
- **Étapes :**
  1. Avec le token du tenant B, appeler une ressource du tenant A.
  2. Appeler une route Restaurant avec un compte dont le flag `restaurantmanager` est désactivé.
  3. Avec le compte **serveur**, appeler `GET /restaurant/health/metrics`.
- **Attendu :** **404** (existence non révélée) sur le cross-tenant ; **403** sans le flag ;
  **403** pour le serveur sur les métriques.
- **Critère de succès :** aucune fuite de données, aucun message révélant l'existence d'une ressource
  d'un autre tenant.

---

## 3. Grille de relevé

Fichier : [`RECETTE_UAT_RESTAURANTMANAGER_GRILLE.csv`](./RECETTE_UAT_RESTAURANTMANAGER_GRILLE.csv)

Colonnes : `scenario`, `libelle`, `operateur`, `debut`, `fin`, `resultat`, `ecart_observe`,
`preuve_fichier`, `commentaire`.

Règles de remplissage :

1. **Une ligne par scénario et par exécution.** Une reprise après correctif crée une nouvelle ligne
   (ne pas écraser la ligne en échec : elle documente le défaut).
2. `resultat` ∈ `OK` | `KO` | `BLOQUE`. `BLOQUE` = impossible d'exécuter (prérequis manquant) —
   à traiter comme un défaut de kit, pas comme un succès.
3. `preuve_fichier` : nom de la capture d'écran ou du fichier d'export, déposé dans le dossier de
   campagne. Une ligne `OK` sans preuve n'est pas recevable.
4. `ecart_observe` : décrire l'écart factuel (valeur attendue vs valeur constatée), pas une
   interprétation.

### Synthèse de campagne

| Champ | Valeur |
|---|---|
| Date de campagne | |
| Environnement (URL) | |
| Version déployée (commit) | |
| Scénarios exécutés | / 10 |
| Scénarios OK | |
| Scénarios KO | |
| Scénarios BLOQUÉS | |
| Décision proposée | GO / NO-GO |

---

## 4. Ce que le kit ne couvre pas

- **Les p95 sur volumes réels.** Le gate `performance` est adossé à des budgets d'endpoints et à la
  présence des index, pas à une mesure de charge. Une mesure p95 réelle demande un environnement
  dimensionné et un générateur de charge — hors périmètre de ce kit.
- **L'application Flutter sur tablette physique.** Le seed fournit les données, mais la compilation
  et l'installation de l'app POS ne sont pas automatisées ici.
- **La signature.** Voir §5.

---

## 5. Signature

La recette n'est **signée** que lorsque les 10 scénarios sont `OK` avec preuve.

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Chef de projet (décision GO) | | | |
| Responsable technique | | | |
| Représentant métier (restaurateur pilote) | | | |

> Tant que cette table est vide, le gate `recette` reste `pending` et `go_decision` reste `pending`
> dans `dev-hub/tools/pilot-gates.json`. Un agent ne peut ni exécuter la recette terrain ni signer
> à la place des personnes ci-dessus.
