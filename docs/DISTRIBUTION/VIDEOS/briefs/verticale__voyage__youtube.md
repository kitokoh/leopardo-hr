# Brief video — verticale D10 · La vitrine vend, l'agence gere · YouTube

> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` (`--vertical D10` × `youtube`), depuis le script detaille §6 du programme `SCRIPTS_VIDEOS_SUITE.md`.
> **Brief rempli** : le contenu produit vient du script, pas d'un gabarit. Seuls les `[A COMPLETER]` de tournage restent a renseigner.

## Contraintes (non negociables)

- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.
- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.
- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
- Aucun client, temoignage ou metrique invente.

## Parametres

| Parametre | Valeur |
|---|---|
| Verticale | **D10** — La vitrine vend, l'agence gere |
| Objet | `voyage` — l'agence de voyage (module `TravelAgency`) |
| Cible | agences de voyage, tour-operateurs, agences d'affaires |
| Objectif | montrer la boutique publique, la reservation et le devis corporate |
| Plateforme | YouTube |
| Duree cible | 30 s (max plateforme : 10 min) |
| Format | 16:9 master — ratios 16:9 / 9:16 / 1:1 ; ratio plateforme 16:9 |

## Accroche (3 premieres secondes)

> Vos clients reservent sur WhatsApp. Vos dossiers vivent dans un cahier.

## Plan

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Bureau d'agence, trois ecrans ouverts : messagerie, tableur, PDF d'un partenaire |
| 4-10 s | Situation | La reservation dispersee entre trois outils |
| 10-24 s | 3 plans produit | Boutique publique : trajets et annonces en ligne · Reservation : un dossier unique (reference, passagers, paiement, consentement aux notifications) · Devis corporate rattache a un compte entreprise, puis converti en reservation |
| 24-30 s | Signature | « Leopardo, pour l'agence de voyage. » + CTA |

**Note de montage.** La reservation enregistre le CONSENTEMENT AUX NOTIFICATIONS et sa date : c'est un point a montrer, pas a cacher. Le devis corporate est rattache a un compte entreprise et devient une reservation, il ne reste pas un document isole. Ne jamais afficher de tarif aerien reel ni de disponibilite « temps reel » de compagnies qui ne sont pas connectees.

## Preuves a l'ecran

- Les trois ecrans de l'agence de voyage, captures sur l'application livree, sur le jeu de demonstration TechCorp DZ (fictif, capte une seule fois puis derive).
- Ne jamais montrer une maquette en la faisant passer pour le produit.

## Appel a l'action

Essayer gratuitement 14 jours — lien vers l'essai.

## Lien et UTM

```
utm_source=youtube
utm_medium=social
utm_campaign=verticale-voyage
utm_content=[A COMPLETER]
```

## Prompt pour agent IA de production

A coller tel quel dans un agent de production video. Le brief est deja rempli : l'agent produit, il n'improvise pas.

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D10 « La vitrine vend, l'agence gere » (verticale : l'agence de voyage, 30 s, 16:9) pour YouTube, a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Nappe ouverte, cordes, 108 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. La reservation enregistre le consentement aux notifications et sa date : montrer le consentement, ne jamais le passer sous silence. Le devis corporate est rattache a un compte entreprise puis converti en reservation : ne pas le presenter comme un simple document. Ne jamais afficher de tarif aerien reel ni de disponibilite temps reel d'une compagnie non connectee.

SORTIES
- leopardo_d10_agence-de-voyage_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « temps reel », « disponibilite garantie »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## Suivi

Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :

`| Verticale D10 — La vitrine vend, l'agence gere | verticale | voyage | YouTube | a faire | — | — | [A COMPLETER] |`
