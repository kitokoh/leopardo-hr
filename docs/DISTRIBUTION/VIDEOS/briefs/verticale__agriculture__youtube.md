# Brief video — verticale D11 · La saison se lit dans un seul historique · YouTube

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`--vertical D11` × `youtube`), depuis le script detaille §6 du programme `SCRIPTS_VIDEOS_SUITE.md`.
> **Brief rempli** : le contenu produit vient du script, pas d'un gabarit. Seuls les `[A COMPLETER]` de tournage restent a renseigner.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Verticale | **D11** — La saison se lit dans un seul historique |
| Objet | `agriculture` — l'agriculture (module `FarmOs`) |
| Cible | agriculteurs, eleveurs, exploitations et cooperatives agricoles |
| Objectif | montrer la parcelle, les intrants et la recolte |
| Plateforme | YouTube |
| Duree cible | 30 s (max plateforme : 10 min) |
| Format | 16:9 master — ratios 16:9 / 9:16 / 1:1 ; ratio plateforme 16:9 |

## Accroche (3 premieres secondes)

> Vous savez ce que vous avez seme. Savez-vous ce que la parcelle a rapporte ?

## Plan

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Parcelle a l'aube, un carnet de travaux a la main |
| 4-10 s | Situation | Le carnet qui ne dit ni ce qui a ete applique ni ce qui a ete recolte |
| 10-24 s | 3 plans produit | Parcelle : superficie, source de la superficie, culture et cycle cultural · Intrants : stock, seuil d'alerte, lots · Recolte : lot de recolte, quantite recoltee, qualite, destination de stockage |
| 24-30 s | Signature | « Leopardo, pour l'agriculture. » + CTA |

**Note de montage.** Farm OS enregistre des rendements DECLARES par l'exploitant (attendus et reels, sur le cycle cultural), mais ne les ESTIME jamais automatiquement : c'est la regle du registre FarmProductionType (« aucune estimation automatique de rendement »). Ne jamais montrer de rendement calcule par le systeme, ni de marge. Chaque superficie porte sa source (declaree, mesuree, importee) : c'est ce qui distingue un chiffre d'une estimation. Une recolte cree un seul lot de stock, et ce lot garde sa tracabilite jusqu'a la vente.

## Preuves a l'ecran

- Les trois ecrans de l'agriculture, captures sur l'application livree, sur le jeu de demonstration TechCorp DZ (fictif, capte une seule fois puis derive).
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

Essayer gratuitement 14 jours — lien vers l'essai.

## Lien et UTM

```
utm_source=youtube
utm_medium=social
utm_campaign=verticale-agriculture
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video. Le brief est deja rempli : l'agent produit, il n'improvise pas.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D11 « La saison se lit dans un seul historique » (verticale : l'agriculture, 30 s, 16:9) pour YouTube, a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Nappe organique, percussions discretes, 98 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Farm OS enregistre des rendements declares par l'exploitant (attendus et reels) mais ne les estime jamais automatiquement : ne jamais montrer de rendement calcule par le systeme, ni de marge. Chaque superficie porte sa source (declaree, mesuree, importee) : ne jamais afficher une superficie sans sa source. Une recolte cree un seul lot de stock qui garde sa tracabilite jusqu'a la parcelle : ne pas montrer de lot sans lien vers la parcelle.

SORTIES
- leopardo_d11_agriculture_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « rendement estime », « rendement calcule », « marge », « rentabilite garantie »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| Verticale D11 — La saison se lit dans un seul historique | verticale | agriculture | YouTube | a faire | — | — | [A COMPLETER] |`
