# Brief video — verticale D6 · Le bulletin est publie, les parents le voient · YouTube

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`--vertical D6` × `youtube`), depuis le script detaille §6 du programme `SCRIPTS_VIDEOS_SUITE.md`.
> **Brief rempli** : le contenu produit vient du script, pas d'un gabarit. Seuls les `[A COMPLETER]` de tournage restent a renseigner.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Verticale | **D6** — Le bulletin est publie, les parents le voient |
| Objet | `ecole` — l'ecole (module `EduManager`) |
| Cible | ecoles privees, groupes scolaires, centres de formation |
| Objectif | montrer l'appel, les notes et l'espace parents |
| Plateforme | YouTube |
| Duree cible | 30 s (max plateforme : 10 min) |
| Format | 16:9 master — ratios 16:9 / 9:16 / 1:1 ; ratio plateforme 16:9 |

## Accroche (3 premieres secondes)

> L'absence est notee le matin. Le parent l'apprend la semaine suivante.

## Plan

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Salle de classe, appel sur un cahier, un parent qui appelle l'accueil |
| 4-10 s | Situation | Le delai entre ce qui se passe en classe et ce que la famille en sait |
| 10-24 s | 3 plans produit | Appel : present, absent, en retard, excuse · Notes : saisie par evaluation, bulletin construit puis valide puis publie · Espace parents : lien a duree limitee, journal des acces |
| 24-30 s | Signature | « Leopardo, pour l'ecole. » + CTA |

**Note de montage.** Le bulletin suit un cycle explicite : construit, valide, publie. C'est la publication qui rend la note visible au parent, pas la saisie. L'espace parents passe par un lien a duree limitee, revocable, avec journal d'acces : montrer le lien et sa date d'expiration, c'est ce qui rassure. Ne jamais promettre « le jour meme » (aucune mesure ne l'etablit) et ne jamais afficher de note d'eleve reelle.

## Preuves a l'ecran

- Les trois ecrans de l'ecole, captures sur l'application livree, sur le jeu de demonstration TechCorp DZ (fictif, capte une seule fois puis derive).
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

Essayer gratuitement 14 jours — lien vers l'essai.

## Lien et UTM

```
utm_source=youtube
utm_medium=social
utm_campaign=verticale-ecole
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video. Le brief est deja rempli : l'agent produit, il n'improvise pas.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D6 « Le bulletin est publie, les parents le voient » (verticale : l'ecole, 30 s, 16:9) pour YouTube, a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Leger, piano et clics, 105 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le bulletin suit un cycle construit, valide, publie : ne jamais montrer une note visible du parent avant la publication. L'espace parents passe par un lien a duree limitee, revocable, avec journal d'acces : montrer l'expiration du lien. Ne jamais afficher de note d'eleve reelle ni de nom d'eleve lisible. Ne pas promettre de delai de publication chiffre.

SORTIES
- leopardo_d6_ecole_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « le jour meme », « instantane », « temps reel »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| Verticale D6 — Le bulletin est publie, les parents le voient | verticale | ecole | YouTube | a faire | — | — | [A COMPLETER] |`
