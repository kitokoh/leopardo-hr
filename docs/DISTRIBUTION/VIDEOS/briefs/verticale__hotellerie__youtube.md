# Brief video — verticale D3 · Une chambre ne se loue pas deux fois · YouTube

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`--vertical D3` × `youtube`), depuis le script detaille §6 du programme `SCRIPTS_VIDEOS_SUITE.md`.
> **Brief rempli** : le contenu produit vient du script, pas d'un gabarit. Seuls les `[A COMPLETER]` de tournage restent a renseigner.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Verticale | **D3** — Une chambre ne se loue pas deux fois |
| Objet | `hotellerie` — l'hotellerie (module `HospitalityManager`) |
| Cible | hoteliers, gestionnaires de residences, exploitants de locations courte et longue duree |
| Objectif | montrer le planning des unites, l'arrivee et le depart, l'etat des unites |
| Plateforme | YouTube |
| Duree cible | 30 s (max plateforme : 10 min) |
| Format | 16:9 master — ratios 16:9 / 9:16 / 1:1 ; ratio plateforme 16:9 |

## Accroche (3 premieres secondes)

> Une chambre vendue deux fois, c'est une arrivee qui commence mal.

## Plan

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Reception d'hotel, deux clients arrives en meme temps, un planning papier surligne |
| 4-10 s | Situation | La double reservation qui ne se voit qu'a l'arrivee |
| 10-24 s | 3 plans produit | Planning des unites : reservations posees sur l'inventaire reel · Arrivee et depart : en attente, confirmee, installee, partie, annulee, non presentee · Etat de chaque unite : disponible, occupee, en maintenance, hors service |
| 24-30 s | Signature | « Leopardo, pour l'hotellerie. » + CTA |

**Note de montage.** Le module HospitalityManager ne contient AUCUNE gestion de menage ni de housekeeping (verifie dans api/app/Modules/HospitalityManager au 2026-11-27 : aucun modele, service ni route de menage). Ne jamais filmer de valet de chambre, de checklist de nettoyage ni de « chambre prete ». Les baux et loyers de location longue duree existent bien, mais appartiennent au film Hotellerie long format (VID-08), pas a ce format court.

## Preuves a l'ecran

- Les trois ecrans de l'hotellerie, captures sur l'application livree, sur le jeu de demonstration TechCorp DZ (fictif, capte une seule fois puis derive).
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

Essayer gratuitement 14 jours — lien vers l'essai.

## Lien et UTM

```
utm_source=youtube
utm_medium=social
utm_campaign=verticale-hotellerie
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video. Le brief est deja rempli : l'agent produit, il n'improvise pas.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D3 « Une chambre ne se loue pas deux fois » (verticale : l'hotellerie, 30 s, 16:9) pour YouTube, a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Nappe discrete, piano, 95 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le module ne gere ni le menage ni le housekeeping : ne jamais montrer de checklist de nettoyage, de valet de chambre ni de statut « chambre prete ». Les etats d'unite sont exactement disponibles, occupee, en maintenance, hors service. Les etats de reservation sont en attente, confirmee, installee, partie, annulee, non presentee : ne pas en inventer d'autres.

SORTIES
- leopardo_d3_hotellerie_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « housekeeping », « menage », « femme de chambre », « chambre prete »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| Verticale D3 — Une chambre ne se loue pas deux fois | verticale | hotellerie | YouTube | a faire | — | — | [A COMPLETER] |`
