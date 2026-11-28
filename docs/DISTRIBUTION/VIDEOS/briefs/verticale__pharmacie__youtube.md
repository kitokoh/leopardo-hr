# Brief video — verticale D4 · L'officine tient son stock et sa vitrine · YouTube

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`--vertical D4` × `youtube`), depuis le script detaille §6 du programme `SCRIPTS_VIDEOS_SUITE.md`.
> **Brief rempli** : le contenu produit vient du script, pas d'un gabarit. Seuls les `[A COMPLETER]` de tournage restent a renseigner.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Verticale | **D4** — L'officine tient son stock et sa vitrine |
| Objet | `pharmacie` — la pharmacie (module `Pharmacy`) |
| Cible | pharmacies d'officine, groupements officinaux, pharmacies de garde |
| Objectif | montrer la vente sur ordonnance, le stock par lot, la fiche publique |
| Plateforme | YouTube |
| Duree cible | 30 s (max plateforme : 10 min) |
| Format | 16:9 master — ratios 16:9 / 9:16 / 1:1 ; ratio plateforme 16:9 |

## Accroche (3 premieres secondes)

> Une erreur de lot n'est pas une erreur de stock. C'est une question de sante publique.

## Plan

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Comptoir d'officine, une boite posee sur le comptoir, numero de lot visible |
| 4-10 s | Situation | Le lot, la peremption, et ce qui se passe quand on les confond |
| 10-24 s | 3 plans produit | Vente au comptoir, rattachee a une ordonnance quand il y en a une · Stock par lot : numero de lot, date de peremption, quantite, cout unitaire · Fiche publique : nom public, ville, pharmacie de garde, services |
| 24-30 s | Signature | « Leopardo, pour la pharmacie. » + CTA |

**Note de montage.** La fiche publique est publique : elle n'expose NI stock NI prix, seulement le nom, la ville, la garde et les services. Ne jamais afficher de nom de medicament reel associe a une quantite reelle, ni suggerer une conformite reglementaire que le produit ne certifie pas.

## Preuves a l'ecran

- Les trois ecrans de la pharmacie, captures sur l'application livree, sur le jeu de demonstration TechCorp DZ (fictif, capte une seule fois puis derive).
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

Essayer gratuitement 14 jours — lien vers l'essai.

## Lien et UTM

```
utm_source=youtube
utm_medium=social
utm_campaign=verticale-pharmacie
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video. Le brief est deja rempli : l'agent produit, il n'improvise pas.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D4 « L'officine tient son stock et sa vitrine » (verticale : la pharmacie, 30 s, 16:9) pour YouTube, a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Neutre, precis, 100 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. La fiche publique n'expose ni stock ni prix : ne jamais montrer de quantite ni de tarif sur la page publique. Le stock se tient lot par lot (numero de lot, peremption, quantite, cout). Ne jamais afficher de nom de medicament reel avec une quantite reelle : utiliser des libelles de demonstration fictifs.

SORTIES
- leopardo_d4_pharmacie_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « conforme », « certifie », « agree »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| Verticale D4 — L'officine tient son stock et sa vitrine | verticale | pharmacie | YouTube | a faire | — | — | [A COMPLETER] |`
