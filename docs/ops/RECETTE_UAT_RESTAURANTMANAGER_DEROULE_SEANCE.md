# Déroulé de séance — Recette UAT RestaurantManager (R2–R11)

> **Document opérationnel.** Il se lit seul, en séance, sans le kit complet.
> Le kit détaillé (`RECETTE_UAT_RESTAURANTMANAGER_KIT.md`) reste la référence
> pour les données de test ; la feuille de signature
> (`RECETTE_UAT_RESTAURANTMANAGER_FEUILLE_SIGNATURE.md`) reste le document
> signé. Ce fichier dit **dans quel ordre, combien de temps, et qui fait quoi**.

---

## 1. Avant d'entrer en séance (15 min, opérateur technique)

| # | Action | Commande / vérification | Attendu |
|---|---|---|---|
| P1 | Base de recette prête | `php artisan pilot:seed resto --clean` puis `php artisan pilot:seed resto` | idempotent, aucun doublon au 2ᵉ passage |
| P2 | Tenant de recette | `resto-pilot-001` | présent, statut actif |
| P3 | Comptes | `principal@example.com` + un serveur + un gérant | connexion OK sur les 3 |
| P4 | Surfaces | back-office web, caisse tablette, page publique | les 3 répondent |
| P5 | Métriques | `GET /api/v1/restaurant/health/metrics` | 200, `data.alerts` vide |

**Si P1–P5 ne sont pas tous verts, la séance ne démarre pas.** Un échec de
préparation n'est pas un échec de recette : on corrige et on replanifie.

---

## 2. Rôles

| Rôle | Qui | Ce qu'il fait en séance |
|---|---|---|
| **Restaurateur** | le pilote (client) | exécute les gestes métier, juge l'expérience, signe |
| **Opérateur** | chef de projet / Dev | prépare, chronomètre, note les preuves, ne guide pas |
| **Observateur** | optionnel | note les frictions d'UX sans intervenir |

> **Règle d'or : l'opérateur ne montre pas.** Il donne l'objectif (« encaisse
> cette commande »), pas le chemin. Une friction qu'on doit expliquer est un
> défaut d'UX à consigner, pas un point à cocher.

---

## 3. Déroulé — 2 h 15

L'ordre suit les **dépendances réelles** : on ne peut pas tester la livraison
sans commande, ni le rapport sans ventes. Chaque scénario produit la donnée
dont le suivant a besoin.

### Bloc A — Ouverture et carte (25 min)

| Scénario | Objectif | Durée | Responsable | Critère de succès observable |
|---|---|---|---|---|
| **R2** | Ouvrir le service, vérifier la carte | 10 min | Restaurateur | la carte s'affiche avec prix et disponibilités ; un produit épuisé est visiblement indisponible |
| **R3** | Créer/modifier un produit | 15 min | Restaurateur | le produit créé apparaît **immédiatement** sur la page publique, sans action technique |

### Bloc B — Service en salle (40 min)

| Scénario | Objectif | Durée | Responsable | Critère de succès observable |
|---|---|---|---|---|
| **R4** | Prendre une commande sur tablette | 15 min | Serveur | la commande part en cuisine en < 5 s ; elle apparaît sur l'écran cuisine |
| **R5** | Encaisser et clôturer | 10 min | Serveur | le ticket est payé, la commande passe à **servie** (pas « fermée ») |
| **R6** | Vérifier le stock après vente | 15 min | Restaurateur | le stock du produit vendu a **baissé du bon nombre**, et le mouvement est **traçable par commande** |

> **Point de vigilance R6** — c'est le défaut corrigé en #9714. Si le stock ne
> bouge pas, ou si le mouvement n'est pas rattachable à la commande, **le
> scénario échoue** : c'est un blocage de production (COGS non calculable).

### Bloc C — Livraison (30 min)

| Scénario | Objectif | Durée | Responsable | Critère de succès observable |
|---|---|---|---|---|
| **R7** | Créer une livraison | 10 min | Gérant | la livraison se crée **sans erreur 422** (défaut corrigé en #9726) |
| **R8** | Suivre et clôturer la livraison | 10 min | Gérant | le client suit l'état ; à la clôture la commande est **servie** |
| **R9** | Rejouer la création de livraison | 10 min | Opérateur | le rejeu **ne crée pas de doublon** (idempotence) |

> **Point de vigilance R9** — un rejeu qui duplique la livraison est un défaut
> bloquant : en cas de coupure réseau, le livreur se retrouve avec deux
> courses.

### Bloc D — Pilotage et réputation (30 min)

| Scénario | Objectif | Durée | Responsable | Critère de succès observable |
|---|---|---|---|---|
| **R10** | Lire le rapport des ventes | 15 min | Restaurateur | le CA de la journée correspond **au total des tickets encaissés** en séance |
| **R11** | Modérer un avis client | 15 min | Restaurateur | l'avis déposé apparaît en attente, peut être publié ou retiré, et une réponse n'est visible **que** sur un avis publié |

> **Point de vigilance R10** — le restaurateur doit pouvoir **recalculer à la
> main** le total de la séance et le retrouver à l'écran. Un écart, même de
> quelques dinars, est un échec : c'est le chiffre sur lequel il paie ses
> impôts.

### Clôture (10 min)

| Étape | Qui | Sortie |
|---|---|---|
| Synthèse des scénarios | Opérateur | tableau rempli, preuves jointes |
| Décision | Restaurateur | signe, ou liste les blocages |
| Suite | Chef de projet | gate `recette` → `validated` **seulement si** R2–R11 sont tous ✅ |

---

## 4. Grille de relevé (à remplir en séance)

| Scénario | ✅ / ❌ | Durée réelle | Preuve conservée | Observation |
|---|---|---|---|---|
| R2 | | | capture | |
| R3 | | | capture | |
| R4 | | | capture | |
| R5 | | | capture | |
| R6 | | | capture + mouvement de stock | |
| R7 | | | capture | |
| R8 | | | capture | |
| R9 | | | capture (2 appels) | |
| R10 | | | capture + calcul manuel | |
| R11 | | | capture | |

**Un scénario sans preuve conservée n'est pas validé**, même s'il a semblé
fonctionner.

---

## 5. Ce qui n'est PAS couvert par cette séance

À dire explicitement au restaurateur pour ne pas surpromettre :

- **Performance sur volumes réels** (p95) : nécessite un jeu de données de
  production, hors séance.
- **Application mobile Flutter** : non compilée dans cet environnement ; la
  caisse tablette testée est la version web.
- **Environnements déployés** (Render/Vercel) : la séance se tient sur
  l'environnement de recette, pas sur la production.
- **Observabilité** : les alertes sont exposées par l'API, mais leur
  acheminement (mail/Slack) n'est pas testé en séance.

---

## 6. Après la séance

1. Remplir la feuille de signature (3 rôles).
2. Si R2–R11 tous ✅ : passer le gate `recette` à `validated` avec la feuille
   comme preuve, puis statuer sur `go_decision`.
3. Si un scénario ❌ : ouvrir une issue dédiée, **ne pas** passer le gate, et
   replanifier une séance de reprise sur les seuls scénarios en échec.
