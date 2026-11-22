# Distribution — espace de définition et de pilotage

> **Qui utilise cet espace** : « Oui » et « Humain » — les deux personnes qui définissent et
> pilotent la mise sur le marché du produit.
>
> **Ce que cet espace est** : le poste de pilotage de la distribution. On y **décide**, on y
> **produit** (briefs vidéo, textes de référencement) et on y **suit** l'état de chaque action.
>
> **Ce que cet espace n'est pas** : une deuxième source de vérité business. Le positionnement,
> le pitch canonique, les canaux et les conventions UTM vivent dans `docs/GOTO_MARKET/` et
> `docs/REFERENTIEL_PRODUIT/`. Cet espace **y renvoie**, il ne recopie pas.

## Règle de vérité

Une action n'est `publié` que si elle porte une **URL** dans `SUIVI.md`. Sans URL, elle est au
mieux `fait` (produite, pas diffusée). Cette règle existe parce qu'un tableau d'avancement qui
accepte « publié » sans preuve devient une déclaration d'intention déguisée en fait.

## Tableau de bord

Les compteurs sont **mesurés** par `scripts/distribution-status.py`, pas saisis à la main :

```bash
python3 docs/DISTRIBUTION/scripts/distribution-status.py
```

Il lit `SUIVI.md` et affiche le nombre d'actions par statut et par catégorie, et signale les
incohérences (une action `publié` sans URL, une date sans statut, un brief référencé mais absent).

## Structure

| Chemin | Rôle |
|---|---|
| `PLAN_DISTRIBUTION.md` | objectifs, verticales cibles, séquencement des vagues |
| `SUIVI.md` | **registre d'état unique** — toutes catégories, colonnes fait / à faire / publié |
| `VIDEOS/` | briefs vidéo générés par catégorie × objet × cible de publication |
| `VIDEOS/INDEX.md` | index des briefs avec leur état |
| `REFERENCEMENT/` | textes prêts à coller + plateformes où inscrire la solution |
| `REFERENCEMENT/PLATEFORMES.md` | liste des plateformes avec fait / à faire / publié |
| `scripts/` | générateurs et contrôles (aucune dépendance externe) |

## Contraintes non négociables

Tout contenu produit ici respecte les référentiels du dépôt :

- **pitch et promesses interdites** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` ;
- **aucun chiffre public sans date de mesure** : `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md` ;
- **UTM conformes** : `docs/GOTO_MARKET/CONVENTION_UTM.md` ;
- **aucun contenu inventé** : pas de client, pas de témoignage, pas de métrique qui n'existe
  pas. Un brief est un **gabarit à remplir**, jamais une affirmation.

## Articulation avec `docs/GOTO_MARKET/`

| Question | Où aller |
|---|---|
| Quel est le pitch ? Quelles promesses sont interdites ? | `docs/REFERENTIEL_PRODUIT/MESSAGE.md` |
| Quels canaux existent, avec quels UTM ? | `docs/GOTO_MARKET/REGISTRE_CANAUX.md` |
| Quel kit pour soumettre sur une plateforme ? | `docs/GOTO_MARKET/platform-submissions/` |
| **Qu'est-ce qui est fait, à faire, publié ?** | **`SUIVI.md` (ici)** |
| **Quel brief vidéo pour telle cible ?** | **`VIDEOS/` (ici)** |
| **Où inscrire la solution pour le SEO ?** | **`REFERENCEMENT/PLATEFORMES.md` (ici)** |
