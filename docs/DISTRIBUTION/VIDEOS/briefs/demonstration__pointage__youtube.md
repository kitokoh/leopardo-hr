# Brief video — prouver que ca marche · le pointage terrain · YouTube

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`demonstration` × `pointage` × `youtube`).
> **Gabarit a remplir** : les `[A COMPLETER]` sont des emplacements, pas du contenu.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Categorie | `demonstration` — prouver que ca marche — montrer le parcours reel, sans coupe magique |
| Objet | `pointage` — le pointage terrain (module `Attendance`) |
| Angle | pointer depuis le terrain, sans badge |
| Cible | YouTube |
| Duree cible | 2-5 min (max plateforme : 10 min) |
| Format | horizontal 16:9 (YouTube), capture d'ecran commentee — ratio plateforme 16:9 |

## Accroche (3 premieres secondes)

> [A COMPLETER] Une phrase qui nomme le probleme du spectateur, pas le produit.

## Plan

0-15 s ce qu'on va voir et pour qui ; 15 s-4 min parcours pas a pas sur donnees de demonstration ; 4-5 min limites et cas non couverts

| # | Plan | Visuel | Voix / texte | Duree |
|---|---|---|---|---|
| 1 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |
| 2 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |
| 3 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |

## Preuves a l'ecran

- [A COMPLETER] Ecran reel du module, sur donnees de demonstration.
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

Essayer sur vos donnees — lien vers l'essai

## Lien et UTM

```
utm_source=youtube
utm_medium=social
utm_campaign=demonstration-pointage
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video, une fois le brief rempli.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis une video demonstration sur le pointage terrain (module Attendance) pour YouTube,
a partir du brief ci-dessus, sans rien inventer.

LIVRABLE
- Duree 2-5 min (max plateforme 10 min), format 16:9, 25 fps, H.264 High, AAC 320 kbps
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
- leopardo_demonstration_pointage_{lang}_16x9_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree dans la fourchette cible a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- UTM du brief reportes a l'identique dans la description publiee
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| prouver que ca marche le pointage terrain | demonstration | pointage | YouTube | a faire | — | — | [A COMPLETER] |`
