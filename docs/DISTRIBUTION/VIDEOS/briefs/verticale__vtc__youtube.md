# Brief video — verticale D8 · Le tarif est connu avant de demarrer · YouTube

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`--vertical D8` × `youtube`), depuis le script detaille §6 du programme `SCRIPTS_VIDEOS_SUITE.md`.
> **Brief rempli** : le contenu produit vient du script, pas d'un gabarit. Seuls les `[A COMPLETER]` de tournage restent a renseigner.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Verticale | **D8** — Le tarif est connu avant de demarrer |
| Objet | `vtc` — le VTC (module `Vtc`) |
| Cible | flottes VTC et taxi, plateformes locales de mobilite |
| Objectif | montrer la course, la grille tarifaire et le suivi |
| Plateforme | YouTube |
| Duree cible | 30 s (max plateforme : 10 min) |
| Format | 16:9 master — ratios 16:9 / 9:16 / 1:1 ; ratio plateforme 16:9 |

## Accroche (3 premieres secondes)

> Le client appelle. On cherche qui est le plus proche. A la main.

## Plan

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Standard telephonique, carte papier, trois chauffeurs appeles en meme temps |
| 4-10 s | Situation | Le dispatch a la main, et l'attente du client |
| 10-24 s | 3 plans produit | Course : demandee, en dispatch, acceptee, arrivee, en cours, terminee · Grille tarifaire : prise en charge, prix au kilometre, prix a la minute, minimum · Suivi : position du chauffeur et code de suivi partage au passager |
| 24-30 s | Signature | « Leopardo, pour le VTC. » + CTA |

**Note de montage.** Le module VTC ne calcule AUCUNE distance lui-meme : il interroge le noyau geospatial, et la distance routiere est une estimation (vol d'oiseau multiplie par un facteur de route). Le prix affiche avant le depart est donc une ESTIMATION, pas un engagement : le dire a l'ecran si l'environnement de demonstration n'a pas de grille configuree. Ne pas promettre de dispatch temps reel si le noyau geospatial n'est pas active.

## Preuves a l'ecran

- Les trois ecrans de le VTC, captures sur l'application livree, sur le jeu de demonstration TechCorp DZ (fictif, capte une seule fois puis derive).
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

Essayer gratuitement 14 jours — lien vers l'essai.

## Lien et UTM

```
utm_source=youtube
utm_medium=social
utm_campaign=verticale-vtc
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video. Le brief est deja rempli : l'agent produit, il n'improvise pas.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D8 « Le tarif est connu avant de demarrer » (verticale : le VTC, 30 s, 16:9) pour YouTube, a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Pulsation urbaine, 118 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le module ne calcule aucune distance lui-meme : il interroge le noyau geospatial, et la distance routiere est une estimation (vol d'oiseau x facteur de route). Le prix affiche avant le depart est une estimation des lors que la grille tarifaire n'est pas configuree : ne jamais le presenter comme un prix garanti. Ne pas promettre de dispatch temps reel sans noyau geospatial active.

SORTIES
- leopardo_d8_vtc_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « prix garanti », « tarif fixe », « dispatch temps reel »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| Verticale D8 — Le tarif est connu avant de demarrer | verticale | vtc | YouTube | a faire | — | — | [A COMPLETER] |`
