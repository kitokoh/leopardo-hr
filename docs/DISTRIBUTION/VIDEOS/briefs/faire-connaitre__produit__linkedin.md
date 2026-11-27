# Brief video — installer le nom et la categorie · la suite metier Leopardo · LinkedIn

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`faire-connaitre` × `produit` × `linkedin`).
> **Gabarit a remplir** : les `[A COMPLETER]` sont des emplacements, pas du contenu.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Categorie | `faire-connaitre` — installer le nom et la categorie — pas de vente directe |
| Objet | `produit` — la suite metier Leopardo (module `—`) |
| Angle | la suite, pas un outil isole |
| Cible | LinkedIn |
| Duree cible | 45-90 s (max plateforme : 3 min) |
| Format | horizontal 16:9 (YouTube/LinkedIn) ou vertical 9:16 — ratio plateforme 1:1 ou 16:9 |

## Accroche (3 premieres secondes)

> [A COMPLETER] Une phrase qui nomme le probleme du spectateur, pas le produit.

## Plan

0-5 s contexte (qui est concerne) ; 5-25 s ce que fait le produit, en une idee ; 25-60 s trois capacites concretes ; 60-90 s ou en savoir plus

| # | Plan | Visuel | Voix / texte | Duree |
|---|---|---|---|---|
| 1 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |
| 2 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |
| 3 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |

## Preuves a l'ecran

- [A COMPLETER] Ecran reel du module, sur donnees de demonstration.
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

En savoir plus — lien vers la vitrine

## Lien et UTM

```
utm_source=linkedin
utm_medium=social
utm_campaign=faire-connaitre-produit
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video, une fois le brief rempli.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis une video faire-connaitre sur la suite metier Leopardo (module —) pour LinkedIn,
a partir du brief ci-dessus, sans rien inventer.

LIVRABLE
- Duree 45-90 s (max plateforme 3 min), format 1:1 ou 16:9, 25 fps, H.264 High, AAC 320 kbps
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Remplacer chaque [A COMPLETER] du brief par du contenu verifie ; ne jamais livrer un brief non rempli.

SORTIES
- leopardo_faire-connaitre_produit_{lang}_1x1 ou 16x9_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree dans la fourchette cible a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- UTM du brief reportes a l'identique dans la description publiee
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| installer le nom et la categorie la suite metier Leopardo | faire-connaitre | produit | LinkedIn | a faire | — | — | [A COMPLETER] |`
