# Impression des billets TravelAgency — prérequis, appareils, secours

> BC-24 TRAVEL OS — issue **#8547**. Ce document décrit ce qui est **réellement
> disponible** dans le dépôt, et ce qui dépend d'un **matériel non testé**.
> Aucun appareil n'est déclaré compatible sans recette réelle documentée.

## 1. Ce qui est livré et vérifié

| Élément | État | Preuve |
|---|---|---|
| Modèle de billet lisible (A4 portrait) | **Livré** | `api/resources/views/travel/ticket.blade.php` |
| Génération PDF locale (aucun service externe) | **Livré** | `TravelTicketPdfGenerator` (laravel-dompdf) |
| QR code scannable sur le billet | **Livré** (#8538) | `TravelTicketQrCode` (BaconQrCode + GD) |
| Mise à disposition par URL signée 30 min | **Livré** | `GET /v1/travel/tickets/{ticket}/pdf` |
| Trace des impressions / réimpressions | **Livré** (#8544) | `RecordTicketPrintAction`, `travel_ticket_print_events` |
| Décodage du QR **depuis le PDF** (test) | **Livré** | `TravelTicketQrDecoder` + `TravelTicketPrintingTest` |

Le billet contient : numéro de billet, référence de réservation, passager,
siège, itinéraire (origine → destination), date/heure de départ et d'arrivée,
validité, tarif, statut, **code de contrôle** et **QR code**.

## 2. Méthodes d'impression réellement disponibles

### 2.1 Navigateur (disponible, aucune installation)

L'agent ouvre l'URL signée du PDF et imprime via le navigateur. C'est le
**chemin de référence** : il ne dépend d'aucun pilote propriétaire.

- Prérequis : un navigateur et une imprimante installée sur le poste.
- Limite : l'URL signée expire au bout de **30 minutes** ; au-delà, il faut
  rappeler l'endpoint (l'opération est alors tracée comme une réimpression).

### 2.2 Partage système depuis mobile (disponible)

L'app agent peut ouvrir le PDF dans la feuille de partage de l'OS, qui propose
les imprimantes configurées sur l'appareil (AirPrint / Mopria / service
d'impression Android). Aucun code spécifique n'est requis côté serveur.

### 2.3 ESC/POS (impression thermique) — **NON implémenté**

`grep -rin "escpos\|esc_pos\|thermal\|receipt_printer" api/app front/mobile_apps`
→ **0 résultat**. Aucun chemin ESC/POS n'existe dans le dépôt.

**Décision : ne pas implémenter à l'aveugle.** Un flux ESC/POS dépend
étroitement du modèle d'imprimante (jeu de commandes, largeur du papier 58/80 mm,
encodage des caractères, mode de connexion Bluetooth/USB/réseau). L'écrire sans
appareil de recette produirait du code non vérifiable — exactement ce que la
mission interdit.

**Si un chemin ESC/POS est retenu plus tard**, les critères à respecter sont
fixés par l'issue #8547 §4 : bibliothèque maintenue, **aucun secret** dans le
flux imprimante, aucune donnée personnelle au-delà du strict nécessaire, et
**repli PDF** si l'imprimante est absente ou en erreur.

## 3. Appareils supportés

**Aucun modèle d'imprimante n'est déclaré compatible.** La compatibilité
dépend d'un test réel (modèle, firmware, capture) qui n'a pas été exécuté dans
cette mission. Le tableau ci-dessous est le **gabarit de recette** à remplir :

| Modèle | Firmware | Connexion | Méthode | Résultat | Date | Opérateur |
|---|---|---|---|---|---|---|
| _(à remplir)_ | | | | | | |

## 4. Solutions de secours

1. **Imprimante indisponible** → le PDF reste téléchargeable et imprimable
   ailleurs ; le billet n'est jamais perdu (il vit côté serveur).
2. **URL signée expirée** → rappeler `GET /v1/travel/tickets/{ticket}/pdf` ;
   l'opération est enregistrée comme réimpression (#8544).
3. **QR illisible à l'embarquement** → l'agent utilise la **recherche manuelle
   par référence** (`POST /v1/travel/tickets/lookup`, #8546) puis la
   vérification par référence + code (`POST /v1/travel/tickets/verify`).
4. **Aucun réseau** → aucune impression n'est possible : le billet est généré
   côté serveur, il n'existe pas de copie hors ligne.

## 5. Ce qui reste à faire (matériel requis)

- Recette d'impression sur **imprimante thermique réelle** (modèle + firmware).
- Recette d'impression depuis **l'app agent sur appareil réel**.
- Décision produit sur l'opportunité d'un chemin ESC/POS natif.

Tant que ces recettes n'ont pas eu lieu, l'impression thermique reste
« **nécessite un appareil** », jamais « opérationnelle ».
