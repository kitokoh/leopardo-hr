# FEUILLE DE CAMPAGNE & SIGNATURE — Recette UAT RestaurantManager

> **Issue :** #9725 — kit de recette terrain (gate `recette`, MAT-018).
> **À imprimer et remplir en séance.** Une page, une campagne, une signature.
> **Documents liés :** [cahier de recette](./RECETTE_UAT_RESTAURANTMANAGER.md) (le *quoi*) ·
> [kit d'exécution](./RECETTE_UAT_RESTAURANTMANAGER_KIT.md) (le *avec quoi* et le *comment*) ·
> [grille de relevé](./RECETTE_UAT_RESTAURANTMANAGER_GRILLE.csv) (le *relevé ligne à ligne*).

---

## 1. Identification de la campagne

| Champ | Valeur |
|---|---|
| Tenant de recette | `resto-pilot-001` |
| Environnement (URL) | |
| Version déployée (commit) | |
| Date de campagne | |
| Opérateur(s) présent(s) | |
| Restaurateur pilote présent | ☐ oui ☐ non |

**Préparation (à cocher avant la séance) :**

- ☐ `php artisan pilot:seed resto --clean` exécuté, puis `php artisan pilot:seed resto`
- ☐ Connexion vérifiée avec `principal@example.com` / `recette123`
- ☐ Back-office `/restaurant` ouvert
- ☐ Menu public `/restaurants/chez-leo-recette` ouvert
- ☐ Caisse tablette `leopardo_pos_restaurant` installée et connectée
- ☐ Grille CSV ouverte pour le relevé

---

## 2. Relevé de campagne (R2–R11)

Cocher au fur et à mesure. **Une ligne `OK` sans preuve n'est pas recevable.**

| # | Scénario | Résultat | Preuve (fichier) | Écart observé |
|---|---|---|---|---|
| R2 | Commande à emporter | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R3 | Livraison complète | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R4 | Livraison annulée | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R5 | Réservation + conflit de créneau | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R6 | No-show et rappel | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R7 | Stock et COGS | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R8 | Fidélité | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R9 | Promotions | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R10 | Rapports et export | ☐ OK ☐ KO ☐ BLOQUÉ | | |
| R11 | Sécurité et isolation | ☐ OK ☐ KO ☐ BLOQUÉ | | |

**Rappel R1 :** déjà validé par la CI (`RestaurantGoldenJourneyTest`, 1 test / 23 assertions).
Il n'est pas à rejouer en séance, mais peut être montré au restaurateur comme démonstration.

---

## 3. Points de vigilance à vérifier explicitement

Ces trois points correspondent à des défauts **réellement corrigés** : les vérifier en séance
protège contre une régression silencieuse.

| Point | Ce qu'on vérifie | Résultat |
|---|---|---|
| **R7 — traçabilité du stock** | Le mouvement de stock est retrouvable en **filtrant par la commande** (`reference_type = order`). S'il ne remonte pas, la régression #9714 est de retour. | ☐ OK ☐ KO |
| **R3 — statut final de livraison** | Après `deliver`, la commande est **`served`** et non `closed` (sinon elle sort des rapports de CA). | ☐ OK ☐ KO |
| **R3 — idempotence** | Rejouer le même appel `deliver` ne crée **pas** de doublon. | ☐ OK ☐ KO |

---

## 4. Synthèse

| Champ | Valeur |
|---|---|
| Scénarios exécutés | / 10 |
| Scénarios OK | |
| Scénarios KO | |
| Scénarios BLOQUÉS | |
| Défauts ouverts (n° d'issue) | |
| **Décision proposée** | ☐ GO ☐ GO partiel ☐ NO-GO |

**Règle :** la recette n'est signée que si les 10 scénarios sont `OK` avec preuve. Un `BLOQUÉ`
est un défaut de kit, pas un succès. Un `KO` bloque la recette et ouvre une issue de correctif
(label `BC-25 RESTAURANT`).

---

## 5. Signature

En signant, les signataires attestent avoir exécuté les scénarios ci-dessus sur l'environnement
de recette, et que les preuves déposées correspondent aux résultats relevés.

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Chef de projet (décision GO) | | | |
| Responsable technique | | | |
| **Représentant métier (restaurateur pilote)** | | | |

---

## 6. Après signature

1. Reporter la décision dans `dev-hub/tools/pilot-gates.json` : gate `recette` → `validated`,
   avec la preuve (`campagne <date>, 10/10 OK, feuille signée déposée`).
2. Passer `go_decision` du pilote `restaurantmanager` à `go` **si et seulement si** les 9 gates
   sont `validated` (`bash dev-hub/tools/check-pilot-gates.sh` doit sortir en 0).
3. Archiver la feuille signée et les preuves dans le dossier de campagne.

> Tant que la table §5 est vide, le gate `recette` reste `pending` et `go_decision` reste
> `pending`. Un agent ne peut ni exécuter la recette terrain ni signer à la place des
> personnes ci-dessus.
