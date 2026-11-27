# SCRIPTS VIDÉO — LEOPARDO, SUITE MÉTIER

**Programme vidéo complet : présenter, vendre et promouvoir la solution sur tous les projets.**

| | |
|---|---|
| **Statut** | Spécification de production (v1) |
| **Portée** | Toutes les verticales et briques du monorepo `kitokoh/leopardo-hr` |
| **Complément** | `SCRIPTS_VIDEOS_COURTES.md` (8 formats sociaux 5 s) — conservé tel quel, ce document le prolonge |
| **Référence de positionnement** | `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md` (décision P03) |
| **Langues** | FR (master), EN, AR, TR — voir §9 |

> ⚠️ **Règle de langage non négociable.** Leopardo est une **suite métier** (*business suite*), jamais un « logiciel RH », jamais un « SaaS RH ». La brique RH & paie reste nommable comme **contenu** d'une phrase, jamais comme **catégorie**. Toute voix off de ce programme respecte les phrases canoniques du référentiel P03.

---

## 1. Le problème que ce programme résout

Le dépôt porte **42 modules métier DDD**, **11 applications mobiles Flutter** plus le paquet partagé `leopardo_core`, un poste desktop, un kiosque, un pont Edge et des surfaces publiques. *(Mesure du 2026-11-27 : `ls -d api/app/Modules/*/`, `ls -d front/mobile_apps/leopardo_*/`.)* Une seule vidéo « produit » ne peut pas vendre cela : elle produit un catalogue illisible et ne convainc personne.

Le programme est donc **stratifié par intention d'achat**, pas par fonctionnalité :

| Étage | Intention du spectateur | Ce que la vidéo doit obtenir |
|---|---|---|
| **A. Marque** | « Qui êtes-vous ? » | Mémorisation + crédibilité |
| **B. Démonstration** | « À quoi ça ressemble ? » | Envie d'essayer |
| **C. Vente** | « Est-ce pour moi ? Combien ? » | Prise de rendez-vous / essai |
| **D. Verticales** | « Vous connaissez mon métier ? » | Reconnaissance immédiate |
| **E. Confiance** | « Est-ce sûr ? Et si je n'ai pas de réseau ? » | Lever l'objection bloquante |
| **F. Social** | « Montrez-moi en 5 s » | Portée et partage |
| **G. Activation** | « Je viens de m'inscrire, et maintenant ? » | Time-to-value |
| **H. Partenaires** | « Puis-je le revendre / l'intégrer ? » | Candidature partenaire |

**Règle de production :** une vidéo = **une seule** promesse. Si le script a besoin de deux phrases pour dire ce qu'elle vend, elle est mal découpée.

---

## 2. Spécifications techniques communes

| Paramètre | Valeur |
|---|---|
| **Master** | 3840×2160 (UHD), 25 fps, H.264 High, AAC 320 kbps |
| **Déclinaisons** | 1920×1080 (web), 1080×1920 (9:16 social), 1080×1080 (1:1 feed) |
| **Sous-titres** | `.srt` + `.vtt` brûlés en version sociale, fichiers séparés en version web |
| **Typographie** | Inter (titres, Bold/SemiBold), Inter (corps, Regular) — jamais plus de 2 graisses par plan |
| **Palette** | Fond nuit `#0B1220`, accent principal `#2563EB`, validation `#16A34A`, alerte `#DC2626`, neutre `#94A3B8` |
| **Voix off** | Voix féminine ou masculine, débit 150–165 mots/min, ton posé, jamais publicitaire criard |
| **Musique** | Lit d'ambiance électronique doux, −18 dB sous la voix ; montée à −12 dB sur le CTA |
| **Captation produit** | **Écran réel de l'application**, jamais une maquette inventée. Si une fonction n'est pas livrée, elle ne figure pas au plan. |
| **Données à l'écran** | Données de démonstration explicitement fictives (noms d'entreprises inventés, jamais un client réel sans autorisation écrite) |
| **CTA final** | Logo Leopardo + `leopardo-rh.com` + « Essai gratuit 14 jours » + QR code. Le domaine est un identifiant technique figé (décision P03 §4) : il s'affiche comme URL, jamais comme nom de produit. |

**Interdits de production** (garde anti-sur-promesse) :
- aucune mention de certification, de conformité légale ou de label non obtenu ;
- aucun chiffre de performance non mesuré (voir §8, règle des chiffres) ;
- aucun logo client sans autorisation écrite ;
- aucune promesse de « remplacement de votre comptable / DRH ».

---

## 3. A — Films de marque

### A1. « Le terrain ne s'arrête jamais » — film manifeste, 90 s

**Objectif :** poser la catégorie (suite métier) et l'ancrage terrain.
**Cible :** dirigeants de PME multi-sites, distributeurs, intégrateurs.
**Diffusion :** page d'accueil, salons, LinkedIn, ouverture de webinaire.

| Temps | Visuel | Voix off | Texte à l'écran |
|---|---|---|---|
| 0–6 s | Boutique qui ouvre, rideau de fer, lumière rasante | « 7 h 40. La ville se réveille. » | — |
| 6–14 s | Chantier, entrepôt, salle de restaurant, officine — montage rapide au même rythme | « Une entreprise de terrain, ce n'est pas un organigramme. C'est du monde, des horaires, du stock, des camions. » | — |
| 14–24 s | Écran de téléphone : pointage, puis tableau de bord qui s'allume | « Et pourtant, on demande encore à tout ça de tenir dans un tableur. » | *Un tableur ne tient pas une entreprise.* |
| 24–38 s | Enchaînement de 6 interfaces réelles : pointage, planning, caisse, stock, comptabilité, fiche publique | « Leopardo réunit ce que votre entreprise fait vraiment : RH et paie, pointage, absences, comptabilité, ventes, opérations. » | *RH & paie · Pointage · Compta · Ventes · Opérations* |
| 38–52 s | Plan large : gérant dans son bureau, employé sur le terrain, même écran | « Sur le web, sur mobile, sur une borne. Et quand le réseau tombe, le travail continue. » | *Web · Mobile · Borne · Hors-ligne* |
| 52–68 s | Succession de métiers : restaurant, pharmacie, école, station, agence | « Parce qu'un restaurant, une pharmacie et une école ne se gèrent pas avec le même outil. » | *Une suite. Des métiers.* |
| 68–82 s | Retour au plan d'ouverture, jour levé | « Leopardo. La suite métier des entreprises de terrain. » | **Leopardo** — *La suite métier des entreprises de terrain* |
| 82–90 s | Logo + URL + QR | « Essai gratuit, 14 jours, sans carte. » | `leopardo-rh.com` · Essai 14 j |

**Note de montage :** le film ne montre **aucun** chiffre. Il vend une catégorie et une sensation de justesse, pas une performance.

---


#### Prompt pour agent IA de production — A1

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video A1 « Le terrain ne s'arrete jamais » (film manifeste 90 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Aucun chiffre a l'ecran. Le film vend une categorie et une sensation de justesse, pas une performance.

SORTIES
- leopardo_a1_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### A2. « Une suite, pas un patchwork » — spot 30 s

**Objectif :** version courte de A1 pour pré-roll et LinkedIn.
**Structure :** 0–8 s problème (tableur + 4 outils déconnectés) · 8–20 s les 6 interfaces s'assemblent en un seul écran · 20–30 s signature + CTA.

**Voix off (30 s, 78 mots) :**
> « Quatre outils. Trois tableurs. Deux boîtes mail. Et toujours la même question : où est la vraie information ? Leopardo réunit RH et paie, pointage, comptabilité, ventes et opérations dans une seule suite. Sur le web, sur mobile, sur borne. Leopardo — la suite métier des entreprises de terrain. »

---


#### Prompt pour agent IA de production — A2

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video A2 « Une suite, pas un patchwork » (spot 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Montage dans les rushes de A1 uniquement, aucune prise nouvelle. 78 mots de voix off, pas un de plus.

SORTIES
- leopardo_a2_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## 4. B — Démonstrations produit

### B1. « Une journée avec Leopardo » — 2 min 30

**Objectif :** montrer la continuité entre les rôles sur une même journée. C'est la vidéo qui **vend le mieux** parce qu'elle ne parle pas de fonctionnalités mais d'enchaînements.
**Cible :** dirigeant, DRH, gérant multi-sites.
**Décor :** une entreprise fictive, **TechCorp DZ**, 3 établissements, 120 collaborateurs.

| Temps | Rôle | Visuel (écran réel) | Voix off |
|---|---|---|---|
| 0–10 s | — | Horloge 06:55, ville | « Une journée ordinaire. On suit une entreprise, pas un logiciel. » |
| 10–28 s | Employé | App mobile : pointage géolocalisé, confirmation | « 7 h 02. Karim pointe depuis le site. Sa position est vérifiée, son heure est enregistrée. » |
| 28–46 s | Manager | Web : planning de la semaine, un conflit signalé en rouge | « 7 h 30. Sa manager voit le planning du jour. Un conflit d'affectation est déjà signalé. » |
| 46–64 s | Employé | App : demande d'absence, envoi | « 9 h 15. Karim demande deux jours. La demande part. » |
| 64–84 s | Manager | Web : notification, validation en un geste, mise à jour du planning | « 9 h 17. Elle valide. Le planning se met à jour, le solde de congés aussi. » |
| 84–104 s | Gérant | POS : vente, ticket, stock décrémenté | « 12 h 40. Au comptoir, une vente. Le stock bouge, le ticket est émis. » |
| 104–124 s | Comptable | Web : écritures du jour, balance | « 17 h. La comptable retrouve les écritures du jour. Rien à ressaisir. » |
| 124–142 s | Gérant | Tableau de bord consolidé, 3 établissements | « 18 h 30. Le gérant ouvre un seul écran. Trois établissements, une seule vérité. » |
| 142–150 s | — | Logo + CTA | « Leopardo. La suite métier des entreprises de terrain. » |

**Point de vente clé à ne pas rater :** le plan 64–84 s. C'est le moment où le spectateur comprend que **la donnée ne se ressaisit jamais**. Le montage doit le rendre évident (même ligne, même identifiant, qui se déplace d'un écran à l'autre).

> 📄 **Storyboard détaillé :** [`STORYBOARD_B1_JOURNEE_LEOPARDO.md`](STORYBOARD_B1_JOURNEE_LEOPARDO.md) — découpage plan par plan (24 plans), voix off complète avec timecodes, shot list de captation, notes de montage, déclinaisons et checklist de diffusion.

---


#### Prompt pour agent IA de production — B1

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video B1 « Une journee avec Leopardo » (demonstration 2 min 30, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le plan 64-84 s est le point de vente : la meme ligne, le meme identifiant, qui se deplace d'un ecran a l'autre sans ressaisie. Le montage doit le rendre evident.

SORTIES
- leopardo_b1_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### B2. « Le tour complet » — 4 min, pour webinaire et page produit

**Objectif :** vidéo de référence, chapitrée, à mettre en page produit et en ouverture de démo commerciale.
**Structure en 8 chapitres** (chacun avec son timecode affiché, pour permettre le partage d'un chapitre) :

| # | Chapitre | Durée | Contenu à l'écran |
|---|---|---|---|
| 1 | **Socle & rôles** | 0:00–0:30 | Connexion, sélection d'établissement, permissions par rôle |
| 2 | **RH & paie** | 0:30–1:05 | Dossier employé, contrat, bulletin, avances sur salaire |
| 3 | **Temps & présence** | 1:05–1:35 | Pointage mobile, kiosque, planning, absences, heures sup |
| 4 | **Comptabilité** | 1:35–2:05 | Journaux, écritures, balance, clôture, export cabinet |
| 5 | **Ventes & opérations** | 2:05–2:45 | POS, stock, catalogue, tournées, réservations |
| 6 | **Surfaces publiques** | 2:45–3:10 | Fiche commerce, page carrières, boutique, SEO multilingue |
| 7 | **Hors-ligne & Edge** | 3:10–3:35 | Coupure réseau, file locale, resynchronisation sans doublon |
| 8 | **Pilotage** | 3:35–4:00 | Tableau de bord consolidé, exports, API |

**Voix off d'ouverture (chapitre 1) :**
> « Voici Leopardo, en quatre minutes. Pas fonctionnalité par fonctionnalité — par ce que vous faites, dans l'ordre où vous le faites. »

**Voix off de clôture :**
> « Huit chapitres, une seule suite. Vous pouvez commencer par un module et ajouter les autres quand votre entreprise grandit. »

---


#### Prompt pour agent IA de production — B2

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video B2 « Le tour complet » (video de reference 4 min, 16:9, chapitree) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Huit chapitres avec timecodes affiches, chacun partageable. Chaque chapitre = une capture reelle, jamais une maquette.

SORTIES
- leopardo_b2_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### B3. « Un métier, un écran » — 60 s, multi-vertical

**Objectif :** montrer l'étendue sans catalogue. Un plan par verticale, 3 s chacun, sur un rythme unique.
**Structure :** 12 plans de 3 s (restauration, retail, hôtellerie, pharmacie, santé, école, station, VTC, livraison, voyage, agriculture, bibliothèque) + 12 s de signature.
**Voix off :** une seule phrase, dite sur le premier plan puis laissée respirer :
> « Douze métiers. Une seule suite. »

---


#### Prompt pour agent IA de production — B3

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video B3 « Un metier, un ecran » (60 s, 16:9, 12 plans de 3 s) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Une seule phrase de voix off sur le premier plan, puis respiration. Rythme unique, aucune variation de tempo.

SORTIES
- leopardo_b3_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## 5. C — Vidéos de vente

### C1. « Le coût du désordre » — 60 s, pour dirigeant

**Objectif :** créer l'urgence économique sans inventer de chiffres.
**Cible :** dirigeant de PME 20–300 salariés, multi-sites.

| Temps | Visuel | Voix off | Texte |
|---|---|---|---|
| 0–8 s | Bureau, 4 onglets ouverts, téléphone qui sonne | « Combien de fois par semaine ressaisissez-vous la même information ? » | — |
| 8–20 s | Split : tableur RH / caisse / compta / messagerie | « Le pointage dans un fichier. Les ventes dans la caisse. La paie dans un autre outil. Et personne ne sait quel chiffre est le bon. » | *4 sources. 0 vérité.* |
| 20–36 s | Un seul écran : la même donnée circule | « Leopardo fait circuler la donnée une seule fois. Le pointage alimente la paie. La vente alimente le stock et la comptabilité. » | *Saisie unique* |
| 36–48 s | Gérant qui ferme son ordinateur à 18 h | « Vous ne rachetez pas un logiciel. Vous arrêtez de payer en heures perdues. » | — |
| 48–60 s | Logo + CTA | « Essai gratuit 14 jours. Sans carte, sans engagement. » | `leopardo-rh.com` |

> **Règle des chiffres (§8) :** aucun pourcentage, aucun « −30 % de temps ». Si un chiffre doit apparaître, il doit venir d'une mesure réelle documentée et datée, avec sa source affichée à l'écran.

---


#### Prompt pour agent IA de production — C1

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video C1 « Le cout du desordre » (60 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Aucun pourcentage, aucun gain de temps chiffre. Si un chiffre doit apparaitre, il vient d'une mesure datee et sourcee a l'ecran.

SORTIES
- leopardo_c1_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### C2. « Pour votre métier » — 45 s × 4 personas

Quatre variantes du **même squelette**, seuls les plans produit et deux phrases changent. C'est le format le plus rentable : un tournage, quatre vidéos.

**Squelette commun :**

| Temps | Bloc | Contenu |
|---|---|---|
| 0–6 s | Accroche métier | Un plan du lieu de travail réel du persona |
| 6–16 s | Douleur spécifique | 2 phrases, une seule douleur |
| 16–34 s | Preuve produit | 3 plans d'écran réels du module concerné |
| 34–40 s | Bénéfice | 1 phrase, formulée du point de vue du persona |
| 40–45 s | CTA | Logo + essai |

**Variante 1 — Dirigeant multi-sites**
> Douleur : « Trois établissements, trois façons de compter. »
> Preuve : tableau de bord consolidé, comparaison d'établissements, export.
> Bénéfice : « Un seul écran pour savoir où vous en êtes, ce soir. »

**Variante 2 — DRH / Responsable RH**
> Douleur : « La paie se prépare encore dans un tableur. »
> Preuve : dossier employé, cycle de paie, absences, documents à échéance.
> Bénéfice : « Le mois se clôture sans course aux justificatifs. »

**Variante 3 — Gérant de commerce / restaurant**
> Douleur : « La caisse dit une chose, le stock une autre. »
> Preuve : POS, plan de salle, écran cuisine, stock, ticket.
> Bénéfice : « Vous savez ce que vous avez vendu, et ce qu'il vous reste. »

**Variante 4 — Cabinet comptable**
> Douleur : « Chaque client a son format d'export. »
> Preuve : portefeuille de dossiers, écritures, clôture, export normalisé.
> Bénéfice : « Vos dossiers arrivent prêts, dans le même format. »

---


#### Prompt pour agent IA de production — C2

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video C2 « Pour votre metier » (45 s x 4 variantes, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Un seul tournage, quatre montages : seuls les plans produit et deux phrases changent. Le squelette temporel est identique au plan pres.

SORTIES
- leopardo_c2_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### C3. « Objections » — 6 × 30 s, format interview face caméra

**Objectif :** traiter les six objections qui bloquent réellement la vente. Format volontairement **non publicitaire** : une personne réelle répond, sans musique, sans motion design. La sobriété est l'argument.

| # | Objection | Réponse (script, 30 s) |
|---|---|---|
| 1 | « C'est trop compliqué pour mon équipe. » | « Un employé n'utilise qu'un écran : pointer, demander une absence, voir son bulletin. Trois gestes. Le reste, c'est pour vous. » |
| 2 | « On n'a pas de réseau sur le terrain. » | « C'est prévu. Le travail continue hors-ligne et se resynchronise au retour du réseau, sans doublon. » |
| 3 | « On a déjà un comptable / un outil de paie. » | « On ne remplace personne. On arrête la ressaisie : ce que votre équipe saisit une fois arrive chez votre comptable dans le bon format. » |
| 4 | « Et nos données, elles sont où ? » | « Chez vous ou chez nous, au choix. Chaque entreprise est isolée, chaque accès est tracé. » |
| 5 | « Combien de temps pour démarrer ? » | « Vous créez votre espace, vous importez vos collaborateurs, vous pointez le lendemain. Les autres modules s'ajoutent quand vous voulez. » |
| 6 | « Pourquoi pas un outil par métier ? » | « Parce qu'alors rien ne se parle. Ici, le pointage alimente la paie, la vente alimente le stock et la comptabilité. » |

---


#### Prompt pour agent IA de production — C3

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video C3 « Objections » (6 x 30 s, 16:9, interview face camera) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Format volontairement non publicitaire : une personne reelle repond, sans musique, sans motion design. La sobriete est l'argument.

SORTIES
- leopardo_c3_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## 6. D — Vidéos verticales (30 s chacune)

**Format identique pour les 15 verticales**, ce qui permet une production industrialisée :

| Temps | Bloc | Contenu |
|---|---|---|
| 0–4 s | Lieu | Plan réel du lieu (comptoir, salle, officine, salle de classe…) |
| 4–10 s | Situation | 1 phrase de contexte métier |
| 10–24 s | 3 plans produit | Les 3 écrans qui comptent pour ce métier |
| 24–30 s | Signature | « Leopardo, pour [métier]. » + CTA |

| # | Verticale | 3 écrans à montrer | Phrase de contexte |
|---|---|---|---|
| D1 | **Restauration** | Plan de salle · Écran cuisine · Ticket | « Le service ne s'arrête pas pour une saisie. » |
| D2 | **Commerce de détail** | POS · Stock · Boutique en ligne | « Ce qui est vendu est déduit. Ce qui reste est juste. » |
| D3 | **Hôtellerie** | Planning des unités · Arrivée/départ · État des unités | « Une chambre ne se loue pas deux fois. » |
| D4 | **Pharmacie** | Vente sur ordonnance · Stock · Fiche publique | « L'officine tient son stock et sa vitrine. » |
| D5 | **Santé** | Agenda · Dossier de soin · Ordonnance | « Le dossier reste confidentiel, et il est là. » |
| D6 | **École / Formation** | Appel · Notes · Espace parents | « Les notes arrivent aux parents le jour même. » |
| D7 | **Station-service** | Index pompes · Ventes carburant · Clôture | « La journée se clôture sur des chiffres qui tombent juste. » |
| D8 | **VTC** | Course · Grille tarifaire · Suivi | « Le tarif est connu avant de démarrer. » |
| D9 | **Livraison** | Tournée · Preuve de livraison · Suivi client | « Chaque colis a une preuve, pas une promesse. » |
| D10 | **Agence de voyage** | Boutique publique · Réservation · Devis corporate | « La vitrine vend, l'agence gère. » |
| D11 | **Agriculture** | Parcelle · Intrants · Récolte | « La saison se lit dans un seul historique. » |
| D12 | **Bibliothèque** | Catalogue · Accès · Lecture | « Un catalogue qui ne ment pas. » |
| D13 | **Cagnottes / collecte** | Cagnotte · Don · Reversement | « La collecte se suit en direct, le solde se reverse au bénéficiaire. » |
| D14 | **Flotte & caméras** | Véhicules · Échéances · Supervision | « Ce qui doit rouler roule. Ce qui doit filmer filme. » |
| D15 | **Cabinet comptable** | Partage documentaire · Lettrage · Export FEC | « Le dossier arrive prêt, dans le format attendu. » |

> **Les quinze verticales ont désormais leur script détaillé** (§6.1 à §6.15 ci-dessous) : le gabarit de 30 s y est décliné plan par plan, avec son prompt de production propre. Aucune verticale ne reste au gabarit commun seul.
>
> **Deux corrections de véracité dans le gabarit ci-dessus** : D12 ne parle plus de « prêt » ni de « rappels » (le module Bibliothèque gère des ressources **numériques**, pas des ouvrages papier) ; D13 ne promet plus que « les reçus partent seuls » (le reçu fiscal automatique n'est pas livré — le code le mentionne comme « reçu phase 2 »).

---


#### Prompt pour agent IA de production — D

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la serie D « Videos verticales » (15 x 30 s, 16:9, gabarit unique) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Gabarit identique pour les 15 verticales : lieu, situation, 3 plans produit, signature. Un seul jeu de donnees de demo, capte une fois, derive sur les 15 films.

SORTIES
- leopardo_d_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.1 D9. « Chaque colis a une preuve » — Livraison, 30 s

**Objectif :** montrer le module Livraison (BC-26) sur les trois écrans qui comptent pour un métier de tournée.
**Cible :** transporteurs, agences de livraison, e-commerçants, restaurants qui livrent.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0–4 s | Lieu | Dépôt, colis empilés, livreur qui charge sa tournée |
| 4–10 s | Situation | « Un colis livré sans preuve, c'est une contestation qui commence. » |
| 10–24 s | 3 plans produit | Tournée et arrêts (statut par arrêt) · Preuve de livraison (photo, signature, horodatage) · Suivi destinataire et opt-out |
| 24–30 s | Signature | « Leopardo, pour la livraison. » + CTA |

**Note de montage :** le module Livraison est un moteur dernier-kilomètre **générique** — tout tenant qui livre (agence, restaurant, retail, e-commerce, pharmacie) active le même moteur. Ne pas montrer de carte temps réel si l'environnement de démonstration ne l'a pas activée.

#### Prompt pour agent IA de production — D9

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D9 « Chaque colis a une preuve » (verticale Livraison, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : percussions regulieres, tempo de tournee, 110 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le module Livraison est un moteur dernier-kilometre generique : tout tenant qui livre active le meme moteur. Ne pas montrer de carte temps reel si l'environnement de demonstration ne l'a pas activee.

SORTIES
- leopardo_d9_livraison_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.2 D12. « Un catalogue qui ne ment pas » — Bibliothèque, 30 s

**Objectif :** montrer le module Library sur les trois écrans qui comptent pour un catalogue de ressources.
**Cible :** établissements de formation, entreprises, organisations qui diffusent des ressources.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0–4 s | Lieu | Bureau, catalogues papier et tableur ouvert |
| 4–10 s | Situation | « Un catalogue de ressources qui vit dans un tableur finit toujours par mentir. » |
| 10–24 s | 3 plans produit | Catalogue et collections (livres, ebooks, guides, manuels, rapports, articles) · Modèle d'accès par ressource (libre, lecture seule, achat, abonnement, privé) · Progression de lecture reprise |
| 24–30 s | Signature | « Leopardo, pour la bibliothèque. » + CTA |

**Note de montage :** le module Bibliothèque gère des ressources **numériques**, pas des ouvrages papier. Ne jamais montrer de prêt physique, de gestion d'emprunteurs ni de rappels de retour : ce n'est pas ce que fait le module.

#### Prompt pour agent IA de production — D12

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D12 « Un catalogue qui ne ment pas » (verticale Bibliotheque, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : nappe douce, piano discret, 90 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le module Bibliotheque gere des ressources NUMERIQUES (livres, ebooks, guides, manuels, rapports, articles) avec un modele d'acces par ressource. Ne jamais montrer de pret d'ouvrages papier, de gestion d'emprunteurs ni de rappels de retour.

SORTIES
- leopardo_d12_bibliotheque_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.3 D14. « Ce qui doit rouler roule » — Flotte & caméras, 30 s

**Objectif :** montrer les modules Fleet et Cameras sur les trois écrans qui comptent pour un parc et des sites à superviser.
**Cible :** entreprises avec parc de véhicules, sites à superviser, gestionnaires de flotte.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0–4 s | Lieu | Véhicule au bord de la route, puis écran de supervision avec une caméra hors ligne |
| 4–10 s | Situation | « Un véhicule qui tombe en panne, c'est une tournée qui s'arrête. Une caméra muette, c'est un doute qui reste. » |
| 10–24 s | 3 plans produit | Véhicules et affectations (chauffeur, site) · Échéances (assurance, contrôle technique) et alertes · Caméras supervisées, accès à durée limitée et journalisé |
| 24–30 s | Signature | « Leopardo, pour la flotte et les caméras. » + CTA |

**Note de montage :** ne jamais afficher de flux vidéo d'un site réel — utiliser un flux de démonstration. Les alertes caméra portent sur des **types d'événement** (mouvement, personne, véhicule, franchissement de ligne, sabotage), jamais sur l'identité d'une personne.

#### Prompt pour agent IA de production — D14

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D14 « Ce qui doit rouler roule » (verticale Flotte & cameras, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : basse reguliere, nappe tendue, 100 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Ne jamais afficher de flux video d'un site reel : utiliser un flux de demonstration. Ne pas promettre de reconnaissance faciale ni d'identification de personnes : les alertes camera portent sur des types d'evenement (mouvement, personne, vehicule, franchissement de ligne, sabotage), pas sur l'identite.

SORTIES
- leopardo_d14_flotte-cameras_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.4 D2. « Ce qui est vendu est déduit » — Commerce de détail, 30 s

**Objectif :** montrer le module Retail (BC-17) sur les trois écrans qui comptent pour une boutique.
**Cible :** commerçants, boutiques, supérettes, e-commerçants, gérants de point de vente.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0–4 s | Lieu | Comptoir de boutique, un ticket imprimé d'un côté, un cahier de stock de l'autre |
| 4–10 s | Situation | « La caisse dit une chose, le stock une autre. » |
| 10–24 s | 3 plans produit | Caisse POS (session, encaissement, clôture avec écart) · Stock tenu par mouvements (achat, vente, ajustement, retour, transfert, perte) · Boutique en ligne et marketplace |
| 24–30 s | Signature | « Leopardo, pour le commerce de détail. » + CTA |

**Note de montage :** le stock n'est jamais négatif et chaque mouvement est tracé ; la marketplace n'expose **aucune quantité**, seulement une disponibilité. Ne pas afficher de chiffre de vente, de marge ni de taux de démarque.

#### Prompt pour agent IA de production — D2

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D2 « Ce qui est vendu est deduit » (verticale Commerce de detail, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : percussions seches, tempo de comptoir, 108 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le module Retail est un moteur de commerce generique : caisse, stock, boutique en ligne et marketplace. Ne jamais afficher de chiffre de vente, de marge, de taux de demarque ni de nombre de clients. La marketplace n'expose aucune quantite de stock, seulement une disponibilite (disponible / indisponible) : ne pas montrer de quantite sur la fiche publique.

SORTIES
- leopardo_d2_retail_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.5 D13. « La collecte se suit en direct » — Cagnottes solidaires, 30 s

**Objectif :** montrer le module Fundraising sur les trois écrans qui comptent pour une collecte.
**Cible :** associations, ONG, mutuelles, écoles, entreprises citoyennes, collectifs.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0–4 s | Lieu | Table de collecte : carnet de dons manuscrit, téléphone qui sonne |
| 4–10 s | Situation | « Une collecte qui vit dans un carnet et un téléphone, personne ne peut la vérifier. » |
| 10–24 s | 3 plans produit | Cagnotte publiée par lien court (page de don, montants suggérés) · Contributions carte et mobile money (montant borné, don anonyme possible) · Suivi de la collecte et reversement au bénéficiaire |
| 24–30 s | Signature | « Leopardo, pour les cagnottes solidaires. » + CTA |

**Note de montage :** le reçu fiscal automatique **n'est pas livré** (le code le mentionne comme « reçu phase 2 ») — ne jamais montrer ni promettre un reçu qui partirait tout seul. Le reversement est traité par le responsable, pas instantané.

#### Prompt pour agent IA de production — D13

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D13 « La collecte se suit en direct » (verticale Cagnottes solidaires, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : nappe chaleureuse, cordes discretes, 92 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le recu fiscal automatique n'existe PAS encore dans le produit (le code le mentionne comme « recu phase 2 ») : ne jamais montrer ni promettre un recu qui partirait tout seul au donateur. Ne pas afficher de montant collecte reel, de nom de beneficiaire reel ni de nombre de donateurs. Ne pas promettre de collecte sans frais ni de reversement instantane : le reversement est traite par le responsable.

SORTIES
- leopardo_d13_cagnottes_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « recu automatique », « recu fiscal », « recu qui part seul »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.6 D15. « Le dossier, le partage, l'export » — Cabinet comptable, 30 s

**Objectif :** montrer le parcours du cabinet sur les trois écrans qui comptent : partage documentaire, lettrage, export FEC.
**Cible :** experts-comptables, cabinets partenaires, DAF.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0–4 s | Lieu | Bureau d'expert-comptable : dossiers papier empilés, boîte mail ouverte |
| 4–10 s | Situation | « Chaque client a son format d'export. » |
| 10–24 s | 3 plans produit | Partage d'un document par lien à durée limitée · Écritures et lettrage avec trace · Export FEC de la période |
| 24–30 s | Signature | « Leopardo, pour le cabinet et son client. » + CTA |

**Note de montage :** le parcours cabinet s'appuie sur le module **comptable** (documents, journaux, lettrage, export FEC, clôture d'exercice), **pas** sur le module `Cabinet` — ce dernier est un placard personnel de documents. Ne pas montrer de portefeuille multi-clients consolidé : le produit n'expose pas de vue agrégée de plusieurs entreprises.

#### Prompt pour agent IA de production — D15

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D15 « Le dossier, le partage, l'export » (parcours Cabinet comptable, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : neutre, precis, metronomique, 96 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Le parcours cabinet s'appuie sur le module comptable (documents, journaux, lettrage, export FEC, cloture d'exercice). Ne pas montrer de portefeuille multi-clients consolide ni de tableau de bord inter-dossiers : le produit n'expose pas de vue agregee de plusieurs entreprises. Ne pas afficher de montants reels ni de taux de cotisation : ils dependent du pays et de l'annee. Le FEC est un format francais : preciser le pays si le film est diffuse hors France.

SORTIES
- leopardo_d15_cabinet_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « portefeuille de dossiers », « vue consolidee multi-clients »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.7 D1. « Le service ne s'arrete pas pour une saisie » — Restauration, 30 s

**Objectif :** montrer le module RestaurantManager sur les trois ecrans qui comptent pendant le service : la salle, l'ecran cuisine, le ticket.
**Cible :** restaurateurs, gerants de plusieurs etablissements, chaines de restauration rapide.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Salle pleine, service en cours, une commande prise debout sur un carnet |
| 4-10 s | Situation | « Pendant le coup de feu, chaque saisie retire une seconde au service. » |
| 10-24 s | 3 plans produit | Plan de salle par zone et par table (session ouverte, fermee, annulee) · Ecran cuisine : la commande passe de « a preparer » a « en cours » a « prete » · Ticket : bon cuisine et recu client imprimes separement |
| 24-30 s | Signature | « Leopardo, pour la restauration. » + CTA |

**Note de montage :** le module couvre les types de commande (sur place, a emporter, livraison) et les sources (caisse, web, telephone, application de livraison, en ligne, borne). L'ecran cuisine est un vrai ecran : il fait avancer la commande, il ne l'affiche pas seulement. Ne jamais montrer de chiffre d'affaires, de marge ni de nombre de couverts.

#### Prompt pour agent IA de production — D1

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D1 « Le service ne s'arrete pas pour une saisie » (verticale Restauration, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Groove leger, ambiance service, 115 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. La salle se montre par zones et tables avec une session de table (ouverte, fermee, annulee). L'ecran cuisine est un poste de travail : la commande y change d'etat (a preparer, en cours, prete). Ne jamais afficher de ticket portant un montant total reel ni de marge : le film montre le flux, pas le chiffre.

SORTIES
- leopardo_d1_restauration_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « marge », « chiffre d'affaires », « benefice »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.8 D3. « Une chambre ne se loue pas deux fois » — Hotellerie, 30 s

**Objectif :** montrer le module HospitalityManager sur les trois ecrans qui comptent pour un etablissement : le planning, l'arrivee et le depart, l'etat des unites.
**Cible :** hoteliers, gestionnaires de residences, exploitants de locations courte et longue duree.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Reception d'hotel, deux clients arrives en meme temps, un planning papier surligne |
| 4-10 s | Situation | « Une chambre vendue deux fois, c'est une arrivee qui commence mal. » |
| 10-24 s | 3 plans produit | Planning des unites : reservations posees sur l'inventaire reel · Arrivee et depart : en attente, confirmee, installee, partie, annulee, non presentee · Etat de chaque unite : disponible, occupee, en maintenance, hors service |
| 24-30 s | Signature | « Leopardo, pour l'hotellerie. » + CTA |

**Note de montage :** le module HospitalityManager ne contient **aucune gestion de menage ni de housekeeping** (verifie dans api/app/Modules/HospitalityManager au 2026-11-27 : aucun modele, service ni route de menage). Ne jamais filmer de valet de chambre, de checklist de nettoyage ni de « chambre prete ». Les baux et loyers de location longue duree existent bien, mais appartiennent au film Hotellerie long format (VID-08), pas a ce format court.

#### Prompt pour agent IA de production — D3

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D3 « Une chambre ne se loue pas deux fois » (verticale Hotellerie, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

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

### 6.9 D4. « L'officine tient son stock et sa vitrine » — Pharmacie, 30 s

**Objectif :** montrer le module Pharmacy sur les trois ecrans qui comptent au comptoir : la vente sur ordonnance, le stock par lot, la fiche publique.
**Cible :** pharmacies d'officine, groupements officinaux, pharmacies de garde.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Comptoir d'officine, une boite posee sur le comptoir, numero de lot visible |
| 4-10 s | Situation | « Une erreur de lot n'est pas une erreur de stock. C'est une question de sante publique. » |
| 10-24 s | 3 plans produit | Vente au comptoir, rattachee a une ordonnance quand il y en a une · Stock par lot : numero de lot, date de peremption, quantite, cout unitaire · Fiche publique : nom public, ville, pharmacie de garde, services |
| 24-30 s | Signature | « Leopardo, pour la pharmacie. » + CTA |

**Note de montage :** la fiche publique est publique : elle n'expose **ni stock ni prix**, seulement le nom, la ville, la garde et les services. Ne jamais afficher de nom de medicament reel associe a une quantite reelle, ni suggerer une conformite reglementaire que le produit ne certifie pas.

#### Prompt pour agent IA de production — D4

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D4 « L'officine tient son stock et sa vitrine » (verticale Pharmacie, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

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

### 6.10 D5. « Le dossier reste confidentiel, et il est la » — Sante, 30 s

**Objectif :** montrer le module HealthManager sur les trois ecrans qui comptent pour un soin : l'agenda, le dossier de soin, l'ordonnance.
**Cible :** cliniques privees, polycliniques, centres de soins, cabinets de groupe.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Couloir de clinique, un dossier papier qui circule entre trois mains |
| 4-10 s | Situation | « Le dossier patient est souvent le dernier endroit ou l'information arrive. » |
| 10-24 s | 3 plans produit | Agenda : rendez-vous par praticien et par service · Dossier de soin : identite et donnees medicales, affichees masquees par defaut · Ordonnance : lignes rattachees a une consultation |
| 24-30 s | Signature | « Leopardo, pour la sante. » + CTA |

**Note de montage :** toutes les donnees sensibles (identite, telephone, allergies, antecedents, assurance) sont stockees **chiffrees** et s'affichent masquees a l'ecran : c'est le point a montrer, pas un detail. L'acces est porte par les roles du module (administrateur, praticien, accueil, facturation). Ne jamais afficher un nom de patient lisible ni une donnee clinique reelle.

#### Prompt pour agent IA de production — D5

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D5 « Le dossier reste confidentiel, et il est la » (verticale Sante, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Nappe calme, cordes discretes, 88 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Les donnees sensibles sont stockees chiffrees et s'affichent masquees : ne jamais montrer un nom de patient lisible, un numero de telephone, une allergie ni un antecedent reel. L'acces est borne par les roles du module (administrateur, praticien, accueil, facturation) : ne pas inventer de role supplementaire. Aucune allegation de conformite reglementaire.

SORTIES
- leopardo_d5_sante_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « conforme RGPD », « certifie », « dossier medical legal »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.11 D6. « Le bulletin est publie, les parents le voient » — Ecole / Formation, 30 s

**Objectif :** montrer le module EduManager sur les trois ecrans qui comptent pour un etablissement : l'appel, les notes, l'espace parents.
**Cible :** ecoles privees, groupes scolaires, centres de formation.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Salle de classe, appel sur un cahier, un parent qui appelle l'accueil |
| 4-10 s | Situation | « L'absence est notee le matin. Le parent l'apprend la semaine suivante. » |
| 10-24 s | 3 plans produit | Appel : present, absent, en retard, excuse · Notes : saisie par evaluation, bulletin construit puis valide puis publie · Espace parents : lien a duree limitee, journal des acces |
| 24-30 s | Signature | « Leopardo, pour l'ecole. » + CTA |

**Note de montage :** le bulletin suit un cycle explicite : construit, valide, publie. C'est la publication qui rend la note visible au parent, pas la saisie. L'espace parents passe par un **lien a duree limitee, revocable, avec journal d'acces** : montrer le lien et sa date d'expiration, c'est ce qui rassure. Ne jamais promettre « le jour meme » (aucune mesure ne l'etablit) et ne jamais afficher de note d'eleve reelle.

#### Prompt pour agent IA de production — D6

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D6 « Le bulletin est publie, les parents le voient » (verticale Ecole / Formation, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

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

### 6.12 D7. « La journee se cloture sur des chiffres qui tombent juste » — Station-service, 30 s

**Objectif :** montrer le module FuelStation sur les trois ecrans qui comptent pour une station : les pompes, les ventes, la cloture.
**Cible :** reseaux de stations-service, gerants independants, stations de nuit.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Station de nuit, pompe eclairee, gerant seul dans la boutique |
| 4-10 s | Situation | « Une station ne ferme jamais. La cloture, elle, doit tomber juste. » |
| 10-24 s | 3 plans produit | Index des pompes : pompe, produit, etat · Ventes carburant : poste, volume, montant, mode de paiement · Cloture : fond d'ouverture, attendu, compte, ecart ; puis reconciliation de stock ouverture plus livraisons moins ventes |
| 24-30 s | Signature | « Leopardo, pour la station-service. » + CTA |

**Note de montage :** la cloture affiche un **ecart**, jamais une « marge ». La reconciliation de stock compare un theorique a un mesure, avec une tolerance : c'est ce qui rend la cloture verifiable. Ne jamais afficher de prix au litre reel : ils varient par pays et par jour.

#### Prompt pour agent IA de production — D7

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D7 « La journee se cloture sur des chiffres qui tombent juste » (verticale Station-service, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : Basse reguliere, nappe tendue, 100 BPM ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. La cloture montre un ecart entre un attendu et un compte, jamais une marge. La reconciliation de stock compare ouverture plus livraisons moins ventes a une jauge mesuree, avec une tolerance : ne pas inventer d'autre formule. Ne jamais afficher de prix au litre reel, ni de volume total reel : ils dependent du pays et du jour.

SORTIES
- leopardo_d7_station-service_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « marge », « benefice », « prix au litre »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### 6.13 D8. « Le tarif est connu avant de demarrer » — VTC, 30 s

**Objectif :** montrer le module Vtc sur les trois ecrans qui comptent pour une course : la reservation, la grille tarifaire, le suivi.
**Cible :** flottes VTC et taxi, plateformes locales de mobilite.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Standard telephonique, carte papier, trois chauffeurs appeles en meme temps |
| 4-10 s | Situation | « Le client appelle. On cherche qui est le plus proche. A la main. » |
| 10-24 s | 3 plans produit | Course : demandee, en dispatch, acceptee, arrivee, en cours, terminee · Grille tarifaire : prise en charge, prix au kilometre, prix a la minute, minimum · Suivi : position du chauffeur et code de suivi partage au passager |
| 24-30 s | Signature | « Leopardo, pour le VTC. » + CTA |

**Note de montage :** le module VTC ne calcule **aucune distance lui-meme** : il interroge le noyau geospatial, et la distance routiere est une estimation (vol d'oiseau multiplie par un facteur de route). Le prix affiche avant le depart est donc une **estimation**, pas un engagement : le dire a l'ecran si l'environnement de demonstration n'a pas de grille configuree. Ne pas promettre de dispatch temps reel si le noyau geospatial n'est pas active.

#### Prompt pour agent IA de production — D8

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D8 « Le tarif est connu avant de demarrer » (verticale VTC, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

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

### 6.14 D10. « La vitrine vend, l'agence gere » — Agence de voyage, 30 s

**Objectif :** montrer le module TravelAgency sur les trois ecrans qui comptent pour une agence : la boutique publique, la reservation, le devis corporate.
**Cible :** agences de voyage, tour-operateurs, agences d'affaires.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Bureau d'agence, trois ecrans ouverts : messagerie, tableur, PDF d'un partenaire |
| 4-10 s | Situation | « Vos clients reservent sur WhatsApp. Vos dossiers vivent dans un cahier. » |
| 10-24 s | 3 plans produit | Boutique publique : trajets et annonces en ligne · Reservation : un dossier unique (reference, passagers, paiement, consentement aux notifications) · Devis corporate rattache a un compte entreprise, puis converti en reservation |
| 24-30 s | Signature | « Leopardo, pour l'agence de voyage. » + CTA |

**Note de montage :** la reservation enregistre le **consentement aux notifications** et sa date : c'est un point a montrer, pas a cacher. Le devis corporate est rattache a un compte entreprise et devient une reservation, il ne reste pas un document isole. Ne jamais afficher de tarif aerien reel ni de disponibilite « temps reel » de compagnies qui ne sont pas connectees.

#### Prompt pour agent IA de production — D10

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D10 « La vitrine vend, l'agence gere » (verticale Agence de voyage, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

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

### 6.15 D11. « La saison se lit dans un seul historique » — Agriculture, 30 s

**Objectif :** montrer le module FarmOs sur les trois ecrans qui comptent pour une exploitation : la parcelle, les intrants, la recolte.
**Cible :** agriculteurs, eleveurs, exploitations et cooperatives agricoles.
**Diffusion :** page pack, LinkedIn, YouTube.

| Temps | Bloc | Contenu |
|---|---|---|
| 0-4 s | Lieu | Parcelle a l'aube, un carnet de travaux a la main |
| 4-10 s | Situation | « Vous savez ce que vous avez seme. Savez-vous ce que la parcelle a rapporte ? » |
| 10-24 s | 3 plans produit | Parcelle : superficie, source de la superficie, culture et cycle cultural · Intrants : stock, seuil d'alerte, lots · Recolte : lot de recolte, quantite recoltee, qualite, destination de stockage |
| 24-30 s | Signature | « Leopardo, pour l'agriculture. » + CTA |

**Note de montage :** Farm OS affiche des **libelles, jamais un rendement ni une marge chiffree** (ADR-FARM-0003, honnetete des donnees). Chaque superficie porte sa source (declaree, mesuree, importee) : c'est ce qui distingue un chiffre d'une estimation. Une recolte cree un seul lot de stock, et ce lot garde sa tracabilite jusqu'a la vente.

#### Prompt pour agent IA de production — D11

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video D11 « La saison se lit dans un seul historique » (verticale Agriculture, 30 s, 16:9) a partir du script ci-dessus, sans rien inventer.

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
7. Farm OS affiche des libelles, jamais un rendement ni une marge chiffree (ADR-FARM-0003). Chaque superficie porte sa source (declaree, mesuree, importee) : ne jamais afficher une superficie sans sa source. Une recolte cree un seul lot de stock qui garde sa tracabilite jusqu'a la vente : ne pas montrer de lot sans lien vers la parcelle.

SORTIES
- leopardo_d11_agriculture_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- 0 occurrence de « rendement », « marge », « rentabilite garantie »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## 7. E — Confiance, F — Social, G — Activation, H — Partenaires

### E1. « Vos données, votre choix » — 60 s (sécurité & RGPD)

| Temps | Visuel | Voix off |
|---|---|---|
| 0–10 s | Écran de connexion, plusieurs rôles | « Chaque personne voit ce que son rôle autorise. Rien de plus. » |
| 10–24 s | Isolation entre établissements, journal d'audit | « Chaque entreprise est isolée. Chaque accès est tracé, avec qui, quand, et sur quoi. » |
| 24–40 s | Registre des traitements, export, suppression | « Vous pouvez exporter les données d'une personne, ou les effacer. Le registre des traitements est tenu. » |
| 40–52 s | Choix d'hébergement | « Hébergé chez nous, ou chez vous. » |
| 52–60 s | Logo + CTA | « Leopardo. » |

> Ne jamais dire « conforme RGPD » sans base juridique documentée. Dire ce qui est **fait** : registre tenu, export, effacement, journalisation.


#### Prompt pour agent IA de production — E1

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video E1 « Vos donnees, votre choix » (60 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Ne jamais dire « conforme RGPD » sans base juridique documentee. Decrire ce qui est fait : registre tenu, export, effacement, journalisation.

SORTIES
- leopardo_e1_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### E2. « Quand le réseau tombe » — 45 s (Edge / hors-ligne)

**Script :** 0–8 s coupure réseau réelle filmée (câble, indicateur) · 8–20 s l'application continue de fonctionner, file locale visible · 20–34 s retour du réseau, resynchronisation, aucun doublon · 34–45 s signature.
**Voix off :** « Une coupure n'est pas une panne. Le travail continue, la file se vide au retour, et rien n'est compté deux fois. »


#### Prompt pour agent IA de production — E2

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video E2 « Quand le reseau tombe » (45 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. La coupure reseau doit etre filmee en vrai (cable, indicateur), pas simulee en animation. La resynchronisation doit montrer l'absence de doublon.

SORTIES
- leopardo_e2_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### E3. « Ouvert » — 45 s (open-source, API, auto-hébergement)

**Cible :** intégrateurs, DSI, partenaires techniques.
**Script :** 0–10 s dépôt GitHub, contrat OpenAPI · 10–24 s clé d'API, appel, webhook · 24–36 s auto-hébergement · 36–45 s CTA « Documentation API ».

#### Prompt pour agent IA de production — E3

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la video E3 « Ouvert » (45 s, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Montrer le depot, le contrat OpenAPI, un appel d'API et un webhook reels. Aucun chiffre d'endpoint non mesure.

SORTIES
- leopardo_e3_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### F — Social (5 s)

Les **8 formats existants** de `SCRIPTS_VIDEOS_COURTES.md` restent la base. Six ajouts pour couvrir les nouveaux projets, sur le même gabarit (5 s, 9:16, 5 séquences) :

| # | Sujet | Accroche |
|---|---|---|
| F9 | **Caisse & stock** | « Vendu. Déduit. Juste. » |
| F10 | **Fiche commerce publique** | « Votre commerce, trouvable en 3 secondes. » |
| F11 | **Hors-ligne** | « Pas de réseau. Pas d'arrêt. » |
| F12 | **Comptabilité** | « La clôture, sans la course. » |
| F13 | **École & parents** | « Les notes, le jour même. » |
| F14 | **Multi-établissements** | « Trois sites. Un écran. » |


#### Prompt pour agent IA de production — F

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la serie F « Social » (6 x 5 s, 9:16) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Chaque format tient en 5 secondes exactes, sous-titres brules, texte dans les 60 % centraux de l'ecran. Aucun chiffre invente.

SORTIES
- leopardo_f_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### G — Activation (post-inscription)

| # | Vidéo | Durée | Objectif |
|---|---|---|---|
| G1 | « Votre espace en 3 minutes » | 3 min | Inscription → premier collaborateur → premier pointage |
| G2 | « Importer votre équipe » | 2 min | Import CSV, vérification, rôles |
| G3 | « Votre premier bulletin » | 4 min | Cycle de paie de bout en bout |

**Règle d'activation :** chaque vidéo se termine par **l'écran exact** que l'utilisateur doit voir à la fin. Pas de « et voilà », mais le résultat à l'écran.


#### Prompt pour agent IA de production — G

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la serie G « Activation » (3 videos : 3 min, 2 min, 4 min) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Chaque video se termine par l'ecran exact que l'utilisateur doit voir a la fin. Pas de « et voila », le resultat a l'ecran.

SORTIES
- leopardo_g_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

### H — Partenaires

| # | Vidéo | Durée | Cible |
|---|---|---|---|
| H1 | « Devenir partenaire » | 90 s | Cabinets comptables, intégrateurs, revendeurs |
| H2 | « Intégrer Leopardo » | 2 min | Développeurs : API, webhooks, SDK |

---


#### Prompt pour agent IA de production — H

```text
ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.
Tu produis la serie H « Partenaires » (90 s et 2 min, 16:9) a partir du script ci-dessus, sans rien inventer.

LIVRABLE
- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)
- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)
- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan
- Musique : lit electronique doux a -18 dB sous la voix, montee a -12 dB sur le CTA

CONTRAINTES NON NEGOCIABLES
1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».
2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.
3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.
4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, Pharmacie Ennour sont fictifs et le restent a l'ecran.
5. Aucune allegation de conformite legale, de certification ou de label non obtenu.
6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.
7. Cible integrateurs et cabinets : montrer l'API, les webhooks, le SDK et le parcours de candidature partenaire, sans promesse de revenus.

SORTIES
- leopardo_h_{slug}_{lang}_{ratio}_v1.mp4 / .srt / .vtt
- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)

AUTO-CONTROLE
- duree conforme au script a +/- 2 %
- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »
- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date
- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees
```

## 8. Règles de production et de véracité

1. **Règle des chiffres.** Aucun chiffre de performance, de gain de temps ou d'économie n'apparaît sans mesure réelle, datée et sourcée à l'écran. En l'absence de mesure, on ne chiffre pas — on décrit.
2. **Règle de l'écran réel.** Chaque plan produit est une captation de l'application livrée. Une fonction non livrée ne figure pas au storyboard.
3. **Règle du client.** Aucun nom, logo ou donnée d'un client réel sans autorisation écrite. Les entreprises des scripts (TechCorp DZ, Riad Atlas, Pharmacie Ennour…) sont **fictives** et doivent le rester à l'écran.
4. **Règle de la catégorie.** « Suite métier » / « business suite ». Jamais « logiciel RH ».
5. **Règle de la promesse unique.** Une vidéo, une promesse. Au-delà, on découpe.
6. **Règle de la langue.** Le master est en français ; les versions EN/AR/TR sont des **adaptations**, pas des traductions littérales (voir §9).

### 8.1 Prompt pour agent IA de production

Chaque script de ce document est suivi d'un bloc **« Prompt pour agent IA de production »**. Ce bloc est le contrat d'exécution : il reprend le découpage du script et y ajoute ce qu'un agent doit savoir pour produire la vidéo sans supervision.

| Ce que le prompt fixe | Pourquoi |
|---|---|
| Format, durée, fps, codec, déclinaisons 16:9 / 9:16 / 1:1 | Un agent ne devine pas les specs de livraison |
| Palette, typographie, niveau de musique | Cohérence visuelle entre les films |
| Les 6 règles de véracité ci-dessus, en impératif, **plus une 7ᵉ contrainte propre au script** | Empêche l'invention de chiffres, de clients et de fonctions ; la 7ᵉ contrainte verrouille le point sensible du module montré |
| Nommage des fichiers de sortie | Livraison déterministe, pas de `final_v3(2).mp4` |
| Auto-contrôle avant livraison | L'agent vérifie lui-même avant de rendre |

**Usage** : copier le bloc tel quel dans l'agent, avec le script au-dessus en contexte. Le prompt ne remplace pas le script — il l'exécute. Si un chiffre doit apparaître à l'écran, il doit d'abord exister dans `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md` avec sa date de mesure ; sinon le plan se tourne sans chiffre.

---

## 9. Déclinaisons linguistiques

| Langue | Statut | Point d'attention |
|---|---|---|
| **FR** | Master | Référence de ton |
| **EN** | Adaptation | « business suite », jamais « HR software » |
| **AR** | Adaptation | Rendu RTL des incrustations ; relecture par un locuteur natif **obligatoire** avant diffusion |
| **TR** | Adaptation | « işletme yönetimi paketi », jamais « İK yazılımı » ; relecture native obligatoire |

Les phrases canoniques de positionnement (P03) sont reprises **telles quelles** dans les cartons de fin, sans reformulation.

---

## 10. Plan de production

| Vague | Livrables | Priorité | Dépendance |
|---|---|---|---|
| **V1 — Socle** | A1, A2, B1, C1, C2 (×4) | Critique | Captations produit disponibles |
| **V2 — Verticales** | D1 → D15 | Haute | Un jeu de données de démo par verticale |
| **V3 — Confiance** | E1, E2, E3, C3 (×6) | Haute | Validation juridique des formulations E1 |
| **V4 — Activation** | G1, G2, G3 | Moyenne | Parcours d'inscription stabilisé |
| **V5 — Social** | F9 → F14 | Moyenne | — |
| **V6 — Partenaires** | H1, H2 | Basse | Programme partenaire défini |

**Ordre de tournage recommandé :** capter **une seule fois** un jeu de données complet et cohérent (TechCorp DZ, 3 établissements, 120 collaborateurs, 1 mois d'activité), puis en dériver tous les plans produit. Cela garantit que les chiffres visibles d'une vidéo à l'autre ne se contredisent pas — un détail qui décrédibilise silencieusement une démonstration.

### 10.1 Couverture des verticales

*Mesure du 2026-11-27.*

Les **15 verticales** du §6 ont leur ligne au gabarit **et** un script détaillé plan par plan avec son prompt de production propre. La couverture est complète : aucune verticale ne reste au gabarit commun seul.

| Verticale | Script détaillé | Ce que la détaillation verrouille |
|---|---|---|
| D1 — Restauration | §6.7 | Écran cuisine comme poste de travail, pas comme affichage |
| D2 — Commerce de détail | §6.4 | Marketplace sans quantité exposée |
| D3 — Hôtellerie | §6.8 | **Ni ménage ni housekeeping** : le module n'en a pas |
| D4 — Pharmacie | §6.9 | Fiche publique sans stock ni prix |
| D5 — Santé | §6.10 | Données chiffrées, affichées masquées |
| D6 — École / Formation | §6.11 | Bulletin construit → validé → publié ; lien parents à durée limitée |
| D7 — Station-service | §6.12 | Clôture sur **écart**, jamais sur marge |
| D8 — VTC | §6.13 | Prix = estimation, distance calculée par le noyau géospatial |
| D9 — Livraison | §6.1 | Preuve de livraison, pas de promesse |
| D10 — Agence de voyage | §6.14 | Consentement aux notifications enregistré |
| D11 — Agriculture | §6.15 | Superficie toujours avec sa source ; aucun rendement chiffré |
| D12 — Bibliothèque | §6.2 | Ressources numériques, pas de prêt papier |
| D13 — Cagnottes solidaires | §6.5 | Pas de reçu fiscal automatique |
| D14 — Flotte & caméras | §6.3 | Alertes par type d'événement, jamais par identité |
| D15 — Cabinet comptable | §6.6 | Module comptable, pas le placard `Cabinet` |

> **Trois corrections de véracité trouvées en détaillant ces neuf scripts** (mesure du 2026-11-27) :
> - **D3 Hôtellerie** — le module `HospitalityManager` ne contient **aucune** gestion de ménage ni de housekeeping : ni modèle, ni service, ni route. Le gabarit promettait « Housekeeping » et le film long VID-08 disait « le ménage […] remonte dans le même système ». Les deux ont été corrigés : D3 montre le planning, l'arrivée/départ et l'état des unités.
> - **D1 Restauration** — « KDS cuisine » devient « écran cuisine » : c'est le même poste, mais nommé comme l'app l'affiche, et il fait avancer la commande au lieu de la montrer.
> - **D8 VTC** — le module ne calcule aucune distance : il interroge le noyau géospatial, et la distance routière est une estimation. Le prix affiché avant le départ est donc une estimation dès que la grille tarifaire n'est pas configurée — le script l'impose à l'écran.

> **Aucun environnement livré n'est annoncé sans film.** Les trois solutions qui n'avaient aucun script (Retail, Cagnottes solidaires, Cabinet comptable) ont désormais le leur : VID-36, VID-37 et VID-38 dans `docs/marketing/scripts-video.json`.

---

## 11. Checklist avant diffusion

- [ ] La catégorie annoncée est « suite métier » (aucune occurrence de « logiciel RH »)
- [ ] Chaque plan produit correspond à une fonction **livrée**
- [ ] Aucun chiffre non mesuré
- [ ] Aucun client réel identifiable sans autorisation
- [ ] Les données à l'écran sont cohérentes entre les plans
- [ ] Sous-titres relus (orthographe, terminologie produit)
- [ ] CTA et URL vérifiés (lien actif, QR testé sur deux téléphones)
- [ ] Versions 16:9 / 9:16 / 1:1 exportées
- [ ] Relecture native effectuée pour AR et TR
- [ ] Fichiers nommés selon la convention `leopardo_<etage>_<slug>_<langue>_<ratio>_v<n>`

---

## 12. Convention de nommage des livrables

```
leopardo_a1_manifeste_fr_16x9_v1.mp4
leopardo_b1_journee_fr_16x9_v1.mp4
leopardo_d3_hotellerie_fr_9x16_v1.mp4
leopardo_c3_objection-reseau_fr_1x1_v1.mp4
```

Étage (`a`–`h`), slug en minuscules avec tirets, langue (`fr`/`en`/`ar`/`tr`), ratio, version. Aucune majuscule, aucun espace, aucun accent dans les noms de fichiers.
