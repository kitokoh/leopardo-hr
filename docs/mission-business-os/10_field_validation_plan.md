# 10 — Plan de validation terrain

**Objectif :** confronter les parcours à des opérateurs réels avant toute généralisation.
Un test automatisé prouve que le logiciel fait ce qui est écrit ; il ne prouve pas que
l'opérateur peut s'en servir un jour de forte activité.

**Règle :** aucun résultat n'est publié sans le nom du site, la date, le nombre de
participants et les observations brutes. Pas de témoignage reconstitué.

---

## 1. Protocole

| Paramètre | Valeur |
|---|---|
| Durée par session | 90 minutes |
| Participants | 3 à 5 par profil (jamais le concepteur du logiciel) |
| Matériel | le matériel réel du site (imprimante, scanner, tablette, réseau du lieu) |
| Observation | un facilitateur silencieux, un preneur de notes horodatées |
| Mesures | temps par tâche, nombre d'erreurs, sollicitations d'aide, arrêts |
| Consentement | écrit, données anonymisées dans le compte rendu |
| Données | **jamais** de données réelles de clients ou d'employés : jeu de démonstration |

**Interdits pendant la session :** expliquer avant que la personne ne bloque, corriger le
logiciel en direct, orienter vers la bonne réponse. Un facilitateur qui aide fausse la
mesure.

---

## 2. Session A — Restaurant mono-établissement

| # | Tâche | Critère de réussite | Mesure |
|---|---|---|---|
| A1 | Créer l'espace de son restaurant depuis la vitrine | espace créé, établissement créé | temps, erreurs |
| A2 | Ajouter 5 produits au menu | 5 produits avec prix | temps |
| A3 | Enregistrer une vente de 3 articles | ticket émis, total correct | temps, erreurs |
| A4 | Envoyer une commande en cuisine puis la clôturer | statut cuisine correct | temps |
| A5 | Couper le Wi-Fi, enregistrer 2 ventes, reconnecter | 2 ventes présentes, **0 doublon** | doublons observés |
| A6 | Inviter un serveur avec un rôle restreint | invitation reçue, accès limité vérifié | erreurs |

**Point de rupture attendu :** A5 (bandeau hors-ligne et confiance dans le rejeu).

## 3. Session B — Groupe de restaurants

| # | Tâche | Critère | Mesure |
|---|---|---|---|
| B1 | Créer 2 établissements | 2 branches actives | temps |
| B2 | Affecter un serveur à la branche 2 uniquement | accès limité à la branche 2 | erreurs de périmètre |
| B3 | Vendre sur la branche 2, consulter la branche 1 | aucune donnée croisée | fuites observées |
| B4 | Consulter un rapport consolidé | total = somme des branches | écart constaté |

**Point de rupture attendu :** B4 — la vue consolidée n'existe pas encore (gap T-10). La
session sert à mesurer le besoin réel, pas à valider une fonctionnalité absente.

## 4. Session C — Station-service

| # | Tâche | Critère | Mesure |
|---|---|---|---|
| C1 | Créer le site et 2 cuves | site + cuves | temps |
| C2 | Enregistrer une vente au comptant | vente + niveau de cuve décrémenté | temps |
| C3 | Enregistrer une vente par carte | `payment_method=card` | erreurs |
| C4 | Clôturer la caisse | session clôturée, ventilation par mode | temps |
| C5 | Annuler une vente erronée | annulation conservant l'historique | compréhension |
| C6 | Utiliser le terminal en coupure réseau | ventes rejouées sans doublon | doublons |

## 5. Session D — Agence de voyage

| # | Tâche | Critère | Mesure |
|---|---|---|---|
| D1 | Créer un trajet avec 20 sièges | trajet visible côté public | temps |
| D2 | Réserver 2 sièges | 2 réservations, sièges indisponibles | erreurs |
| D3 | Scanner le QR du billet au contrôle | billet reconnu, non consommé par la vérification | temps |
| D4 | Double contrôle du même billet | second refusé, opérateur du premier conservé | comportement |

## 6. Session E — Onboarding « Trade »

À conduire avec un importateur et un grossiste réels.

| # | Tâche | Critère | Mesure |
|---|---|---|---|
| E1 | Depuis la vitrine, trouver la solution correspondant à son métier | atteint la bonne page sans aide | temps, hésitations |
| E2 | Choisir International puis « importateur » | choix conservé jusqu'à l'inscription | perte de choix |
| E3 | Créer son espace | tenant + établissement | temps |
| E4 | Revenir après 10 minutes d'interruption | reprise à l'étape quittée | perte de saisie |
| E5 | Réaliser la première opération guidée | opération enregistrée | abandon |

**Point de rupture attendu :** E2 et E4 — ce sont les écarts O-02 et O-06.

## 7. Grille d'analyse

| Indicateur | Seuil d'alerte |
|---|---|
| Taux de réussite sans aide | < 80 % → refonte du parcours concerné |
| Temps de la première opération | > 15 min → simplification |
| Doublons constatés | > 0 → **bloquant**, aucun déploiement |
| Fuite inter-établissement | > 0 → **bloquant** |
| Sollicitations d'aide | > 2 par tâche → microcopie à revoir |

---

## 8. Déroulé et livrables

1. **Avant** : jeu de démonstration chargé, appareils enrôlés, réseau du site mesuré.
2. **Pendant** : notes horodatées, capture des écrans d'erreur, aucun commentaire du
   facilitateur.
3. **Après (48 h)** : compte rendu brut, liste d'écarts priorisés, mise à jour de la
   matrice (`08_gap_matrix.md`) et des issues.
4. **Décision** : go / no-go pour pilote élargi, motivée par les seuils du §7.

Aucune de ces sessions n'a été conduite dans le cadre de cette mission : le plan est
**prêt à exécuter**, il n'est pas un compte rendu.
