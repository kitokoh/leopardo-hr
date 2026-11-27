# Audit de l'espace marketing — état réel, écarts, corrections

> **Date** : 2026-11-27 · **Périmètre** : `docs/marketing/` (espace scripts vidéo) + `docs/GOTO_MARKET/ASSETS_PRODUCTION/` (supports) + `front/web/src/modules/vitrine/data/videos.ts` (page publique).
> **Méthode** : lecture des sources de vérité, comptage sur les fichiers, rendu réel de la page générée (Chromium, desktop 1440 px et mobile 390 px), et exécution du générateur.
> **Issue liée** : #10002.

---

## 1. Ce qui est déjà fait — et c'est solide

| Élément | État mesuré |
|---|---|
| Scripts vidéo écrits | **32** scripts, tous complets (hook, découpage, voix off, plans, CTA) |
| Séquences détaillées | **150** beats au total |
| Scripts avec CTA | **32 / 32** (100 %) |
| Catégories | **5** (présentation, verticales, profils, fonctionnalités, formats courts) |
| Locales | **4** (fr, en, tr, ar), arabe en RTL |
| Traduction du contenu | **32 / 32** scripts traduits en en, tr et ar |
| Pages générées | **168** pages HTML (4 locales × 42) |
| Calendrier de tournage | 6 vagues ordonnées, export CSV avec BOM UTF-8 |
| Supports écrits | 10 documents (landing spec, brochure, one-pager, pitch deck, storyboard B1, prompts visuels) |

**Ce n'est pas un chantier à refaire.** L'architecture de l'espace est bonne : source de vérité JSON, générateur Python, i18n séparé, sortie statique publiable. C'est exactement ce qu'il faut.

---

## 2. Les écarts réels — ce qu'il y a à améliorer

### 2.1 🔴 Bloquant — 32 scripts écrits étaient marqués « à écrire » (corrigé)

**Constat** : les 32 entrées de `statuts-tournage.json` portaient le statut `a_ecrire`, alors que **les 32 observations disaient** « Script rédigé et validé (hook, découpage, CTA, notes de production) ». Le tableau de bord affichait donc **0 % d'avancement** sur un travail fait à 100 % sur la partie écriture.

**Cause** : le vocabulaire de statut n'avait pas d'état intermédiaire entre « à écrire » et « tourné ». Un script écrit n'avait nulle part où se ranger.

**Correction appliquée** : ajout du statut `redige` (« Rédigé ») entre `a_ecrire` et `tourne`, dans les 4 locales, et reclassement des 32 scripts. Le récapitulatif affiche désormais **32 « Rédigé »** au lieu de 32 « À écrire ».

**Effet** : la production réelle devient visible. C'est la différence entre « rien n'est fait » et « tout est écrit, rien n'est tourné » — deux situations qui n'appellent pas du tout la même décision.

### 2.2 🟠 Incohérence de périmètre — 40 vidéos annoncées, 32 scripts

`docs/GOTO_MARKET/ASSETS_PRODUCTION/VIDEOS/README.md` annonce **« Total : 40 vidéos »** (étages A à H). `docs/marketing/scripts-video.json` en contient **32**.

Les 8 manquantes ne sont pas un oubli de rédaction : ce sont les étages **G (activation : 3 vidéos)** et **H (partenaires : 2 vidéos)**, plus 3 formats sociaux. Elles ne figurent pas dans le calendrier de tournage.

**À trancher** : soit on aligne le README sur 32, soit on ajoute les 8 scripts manquants. Recommandation : **aligner sur 32** et sortir G/H du programme de lancement — elles supposent des clients et des partenaires qui n'existent pas encore.

### 2.3 🟠 La page publique `/videos` ne reflète pas le travail

`front/web/src/modules/vitrine/data/videos.ts` liste **4 vidéos « à venir »** par locale, dont « Paie multi-pays : Algérie, Maroc, France » — alors que le moteur couvre **21 pays** (vérifié en exécution, §4). La vitrine sous-vend le produit et ne pointe vers aucun des 32 scripts.

**À faire** : brancher la page publique sur `scripts-video.json` (ou au minimum corriger le libellé « Algérie, Maroc, France » → « 21 pays »).

### 2.4 🟡 Le suivi de production n'a pas d'historique exploitable

`statuts-tournage.json` prévoit un historique horodaté par script, mais les 32 entrées n'ont qu'une seule ligne, toutes datées du même jour, avec le même auteur. Ce n'est pas un historique, c'est un instantané.

**À faire** : à chaque changement de statut, ajouter une ligne (statut, date, auteur, note). Sinon on ne saura pas, dans trois mois, qui a validé quoi.

### 2.5 🟡 Aucun lien entre un script et son asset produit

Les scripts décrivent des plans (« écran dossier employé », « bulletin qui se génère ») mais rien ne relie un script à une capture d'écran réelle, un rush ou un fichier monté. Le jour du tournage, il faut tout retrouver à la main.

**À faire** : ajouter un champ `assets` par script (chemins des captures, rushes, exports).

### 2.6 🟡 Le message de lancement n'est pas encore aligné sur le repositionnement

Les 32 scripts vendent la **suite complète** (« RH & paie, pointage, CRM, comptabilité, opérations »). Le repositionnement (§`REPOSITIONNEMENT_2026.md`) recommande de vendre **Paie + Pointage, en Algérie, d'abord**.

Ce n'est pas une erreur — c'est un décalage de phase. Mais **VID-01 (le film pilier) est le premier tournage de la vague V1** : s'il part avec le message « suite complète », tous les dérivés (30 s, 15 s, explainer) partiront avec. C'est le seul point où l'ordre de tournage et la stratégie se contredisent.

**Recommandation** : tourner VID-01 avec le message « paie + pointage » en ouverture, et garder la suite complète pour la deuxième moitié du film. Le film reste vrai, mais l'accroche est celle du lancement.

---

## 3. Ce qui manquait et qui est maintenant là

L'onglet **« Profils & modules »** (`profils-modules.html`, 4 locales) répond à la question qui n'avait pas de réponse dans le dépôt : *qui peut faire quoi, et qu'est-ce que chaque module sait réellement faire ?*

- **31 profils** — une à deux phrases concrètes par profil, à la deuxième personne, avec un verbe d'action et un résultat.
- **55 modules** — une à deux phrases par module, décrivant ce qu'il fait réellement, pas ce qu'il promet.
- Filtre texte + filtre type (profil / module), navigation dans les 4 locales, RTL arabe.

**Vérifié au rendu** : 86 cartes visibles, recherche « paie » → 13 résultats, filtre « modules » → 55, aucun débordement horizontal à 390 px.

---

## 4. Preuve d'exécution — ce que le produit fait vraiment

Pour que l'onglet ne raconte pas d'histoires, chaque affirmation a été confrontée au code exécuté :

| Affirmation de l'onglet | Vérification |
|---|---|
| « Paie multi-pays (DZ, MA, TN, FR, TR, SN) » | **21 pays** réellement résolus : DZ, MA, TN, FR, TR, SN, CM, CF, TD, CG, GA, GQ, CI, ML, BF, BJ, TG, NE, CA, GB, US |
| « Produit les bulletins en PDF » | PDF de **879 730 octets** généré via `/api/v1/pay-slips/1/pdf` |
| « Détecte les anomalies » | `/payroll-runs/1/anomalies` → 0 anomalie sur le run de test |
| « Verrouille un run une fois validé » | `validated` → `locked` confirmés |
| « Fonctionne hors connexion » | Mode hors-ligne présent (kiosque + EdgeSync), non testé en coupure réseau réelle |
| « 4 locales, arabe RTL » | 168 pages générées, `dir="rtl"` confirmé sur `ar/` |

**Ce qui n'est pas prouvé** : la conformité légale des référentiels (niveau `pilot`, à valider par un conseil local) et la tenue en charge.

---

## 5. Plan d'action, par ordre de rentabilité

| # | Action | Effort | Effet |
|---|---|---|---|
| 1 | ✅ Statut « Rédigé » + reclassement des 32 scripts | fait | La production réelle devient visible |
| 2 | ✅ Onglet « Profils & modules » (4 locales) | fait | Répond à « qui peut faire quoi » |
| 3 | Aligner le README 40 → 32 vidéos | 15 min | Supprime une promesse non tenue |
| 4 | Corriger « Algérie, Maroc, France » → « 21 pays » sur la vitrine | 15 min | Le produit n'est plus sous-vendu |
| 5 | Tourner VID-01 avec l'accroche « paie + pointage » | 1 tournage | Aligne le film pilier sur le lancement |
| 6 | Ajouter le champ `assets` par script | 1 h | Le tournage devient exécutable sans recherche |
| 7 | Historique de statut par script | 1 h | Traçabilité de la production |

Les actions 1 et 2 sont livrées dans cette mission. Les actions 3 à 7 sont des corrections courtes, listées ici pour ne pas les perdre.
