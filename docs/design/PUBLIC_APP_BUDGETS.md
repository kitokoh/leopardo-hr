# Couloir 16 — Budgets de l'application grand public

- **Livrable** : complément M0 (`COULOIR16_PLAN.md` §8.4, `COULOIR16_CARTOGRAPHIE.md` §2.1)
- **Portée** : PWA (`front/web`, routes `/decouvrir`) et app Flutter (`front/mobile_apps/leopardo_public`)
- **Statut** : **proposition chiffrée, non encore calibrée par mesure.** Voir §7 — c'est le point le plus important de ce document.
- **Langues** : `fr`, `en`, `ar` (RTL), `tr`

---

## 0. Pourquoi ce document existe

Le cahier des charges du couloir 16 impose des seuils de qualité (« premier écran utile en moins de 3 s en réseau lent simulé », « taille de l'application et poids des images dans un budget fixé en M0 ») et une règle de fermeture : **« un lot ne se ferme pas si un seuil est dépassé »**.

Une règle de fermeture sans chiffre mesurable ne ferme rien. Ce document transforme les intentions en **budgets vérifiables**, chacun associé à :

1. une **valeur cible** ;
2. une **méthode de mesure reproductible** ;
3. le **moment** où la mesure est prise (local, CI, appareil réel) ;
4. le **comportement en cas de dépassement**.

### Ce que ce document n'est pas

Ce n'est **pas** un relevé de mesures. Aucune mesure n'a été exécutée pour l'écrire : le sandbox de rédaction ne dispose ni de PHP, ni de Flutter, ni de Docker, ni d'un appareil Android (vérifié : `command -v php dart flutter docker` → vide). Les valeurs ci-dessous sont des **propositions argumentées**, à confirmer ou corriger en M0 par la campagne de mesure décrite au §7. **Aucun chiffre de ce document ne doit être présenté comme mesuré.**

---

## 1. Principe directeur : le budget se mesure là où l'utilisateur est

Un budget mesuré sur un poste de développement ne dit rien de l'expérience réelle. Trois environnements, trois usages :

| Environnement | Ce qu'on y mesure | Ce qu'on n'y mesure pas |
|---|---|---|
| **Poste de développement** | régressions relatives (avant/après une PR), taille des bundles, nombre de requêtes | temps de chargement absolu, ressenti |
| **CI** | budgets **bloquants** : poids des assets, nombre de requêtes par écran, couverture, Lighthouse | performance perçue sur matériel réel |
| **Appareil Android d'entrée de gamme, réseau bridé** | temps jusqu'au premier écran utile, fluidité, plantages | — (c'est la référence) |

**Règle** : un budget qui ne peut pas être mesuré en CI est un budget **de campagne**, pas un budget de PR. Les deux existent, mais ils ne bloquent pas au même endroit (§6).

---

## 2. Appareil de référence

Les budgets de temps n'ont de sens que sur un matériel nommé. Proposition de référence, à figer en M0 :

| Caractéristique | Valeur proposée | Justification |
|---|---|---|
| Classe | Android d'entrée de gamme, ~100–130 € neuf | représente la majorité des premiers smartphones des marchés visés |
| RAM | 3 Go | en dessous, le système tue l'app en arrière-plan |
| CPU | 4 cœurs, milieu de gamme bas (ex. Helio G-series / Snapdragon 4-series) | — |
| Écran | 720 × 1600, ~360 dp de large | **360 px est la largeur de référence des maquettes** (`docs/design/couloir16-mockups/`) |
| Android | 11 ou 12 (API 30/31) | `minSdk 26` est le plancher du dépôt (#8776) ; la référence doit être au-dessus du plancher, pas dessus |
| Réseau | profil « Slow 4G » : 1,6 Mbit/s descendant, 750 kbit/s montant, RTT 150 ms | profil DevTools standard, reproductible |

**Pourquoi pas le minimum supporté (`minSdk 26`)** : un budget calibré sur le pire matériel supporté produit des cibles si basses qu'elles n'améliorent rien sur le matériel réellement utilisé. On calibre sur le matériel **représentatif**, et on documente le comportement sur le minimum supporté sans en faire la cible.

---

## 3. Budgets de temps — PWA

Mesure : Lighthouse mobile (profil « Mobile », throttling simulé) **et** une mesure réelle sur l'appareil de référence via Chrome remote debugging.

| Métrique | Cible | Plafond (échec) | Méthode |
|---|---|---|---|
| **Premier écran utile** (résultats de recherche visibles) | **≤ 3,0 s** | > 3,5 s | mesure réelle, réseau Slow 4G, cache froid |
| **LCP** (Largest Contentful Paint) | ≤ 2,5 s | > 4,0 s | Lighthouse mobile |
| **INP** (Interaction to Next Paint) | ≤ 200 ms | > 500 ms | Lighthouse + mesure réelle |
| **CLS** (Cumulative Layout Shift) | ≤ 0,10 | > 0,25 | Lighthouse |
| **TTFB** (réponse serveur, `/decouvrir`) | ≤ 600 ms | > 1 200 ms | `curl -w '%{time_starttransfer}'` × 20, p95 |
| **Temps d'ouverture d'une fiche** (depuis un résultat) | ≤ 1,2 s | > 2,0 s | mesure réelle, navigation client |
| **Premier écran utile, cache chaud** | ≤ 1,0 s | > 1,8 s | mesure réelle, service worker actif |

**Le « premier écran utile » n'est pas le `load`.** C'est le moment où l'utilisateur voit des commerces qu'il peut toucher. Un `load` à 1,8 s avec un squelette pendant 4 s est un échec, pas une réussite — c'est pourquoi la métrique est définie par le contenu, pas par l'événement navigateur.

**Pourquoi 3 s et pas 2 s** : le cahier des charges fixe 3 s. Descendre en dessous serait un objectif d'équipe, pas un critère d'acceptation. Le plafond à 3,5 s laisse une marge de bruit de mesure de ~15 % — en dessous, on ferme des lots sur du bruit.

---

## 4. Budgets de poids — PWA

Mesure : `next build` (rapport de tailles) + analyse du bundle + poids des assets servis.

| Élément | Cible | Plafond | Note |
|---|---|---|---|
| **JS initial** (route `/decouvrir`, gzip) | ≤ 180 Ko | 250 Ko | hors polyfills |
| **CSS initial** (gzip) | ≤ 30 Ko | 50 Ko | — |
| **Poids total du premier écran** (HTML + JS + CSS + images above-the-fold, gzip) | ≤ 450 Ko | 650 Ko | — |
| **Image de tuile de résultat** | ≤ 40 Ko | 60 Ko | AVIF/WebP, dimensionnée au besoin réel |
| **Image d'en-tête de fiche** | ≤ 120 Ko | 180 Ko | — |
| **Police** | ≤ 2 fichiers, ≤ 90 Ko total | 3 fichiers / 140 Ko | sous-ensemble latin + arabe |
| **Icônes PWA** | ≤ 5 fichiers | 6 | tailles 192/512 + maskable |
| **Nombre de requêtes au premier écran** | ≤ 12 | 18 | hors images de tuiles |

**Pourquoi un budget d'images séparé** : sur les marchés visés, le forfait data est une contrainte réelle. Une grille de 20 résultats à 200 Ko l'unité coûte 4 Mo — soit plusieurs minutes de forfait pour une recherche. Le budget par image est donc plus contraignant que le budget total.

**Règle de dégradation** : au-delà du plafond, l'image n'est **pas** servie en pleine résolution — elle est remplacée par un placeholder de marque. Un écran qui se charge est préférable à un écran beau qui ne se charge pas.

---

## 5. Budgets de poids et de temps — Flutter

Mesure : `flutter build apk --release --split-per-abi` + `flutter build appbundle`, et mesure réelle sur l'appareil de référence.

| Élément | Cible | Plafond |
|---|---|---|
| **APK arm64-v8a (release)** | ≤ 22 Mo | 30 Mo |
| **AAB (téléchargé par l'utilisateur, estimation Play)** | ≤ 18 Mo | 25 Mo |
| **Assets embarqués** (images, polices, Lottie) | ≤ 4 Mo | 6 Mo |
| **Démarrage à froid → premier écran utile** | ≤ 2,5 s | 3,5 s |
| **Démarrage à chaud → premier écran utile** | ≤ 1,0 s | 1,5 s |
| **Mémoire résidente au repos** (accueil chargé) | ≤ 180 Mo | 250 Mo |
| **Taille du cache disque** (recherches + favoris) | ≤ 15 Mo | 25 Mo |

**Pourquoi un plafond mémoire** : sur 3 Go de RAM, une app à 250 Mo se fait tuer dès que l'utilisateur bascule vers l'appareil photo. Le budget mémoire n'est pas du confort, c'est la condition pour que « reprendre où vous étiez » fonctionne réellement.

**Note sur `--split-per-abi`** : le budget porte sur l'APK **arm64-v8a**, celui que reçoit la quasi-totalité des appareils. Un APK universel est structurellement plus gros et ne doit pas servir de base de comparaison.

---

## 6. Budgets de qualité — plantages, tests, accessibilité

| Métrique | Cible | Plafond | Où |
|---|---|---|---|
| **Plantages sur les parcours principaux** | **0** sur les 20 dernières exécutions e2e | 1 | CI |
| **Couverture de la logique critique** (état, modèles, repositories) | ≥ 80 % | 70 % | CI |
| **Couverture globale** (indicative, non bloquante) | ≥ 60 % | — | CI |
| **Tests de contrat OpenAPI** | 100 % des endpoints publics couverts | — | CI |
| **Widget tests avec captures de référence LTR + RTL** | chaque écran livré | — | CI |
| **Violations d'accessibilité AA** (axe / `flutter_test` semantics) | 0 | 0 | CI |
| **Cibles tactiles** | ≥ 48 × 48 dp | 44 × 44 dp | revue + test |
| **Chaînes non traduites** (4 locales) | 0 | 0 | CI (`I18N_VALIDATION_OK`) |
| **Débordement de mise en page à 360 px** | 0 | 0 | test widget + capture |

**Pourquoi 0 pour les plantages et l'accessibilité, alors que tout le reste a un plafond** : un plantage et une violation AA sont des **défauts binaires**, pas des mesures continues. Il n'existe pas de « 0,3 plantage acceptable ». En revanche, un taux de couverture est une mesure continue : lui donner un plafond dur ferme des lots sur du bruit de mesure.

---

## 7. Calibration en M0 — la campagne de mesure

**Aucun budget de ce document n'est validé avant cette campagne.** Elle produit les valeurs définitives et remplace les propositions ci-dessus.

### 7.1 Protocole

1. **Figer l'appareil** : un modèle précis, acheté ou emprunté, consigné par référence exacte dans ce document.
2. **Figer le réseau** : profil Slow 4G tel que défini au §2, appliqué par le routeur de test (pas seulement par DevTools, qui ne bride pas l'app Flutter).
3. **Mesurer 20 fois**, jeter les 2 extrêmes, retenir la **médiane** et le **p95**.
4. **Mesurer trois états** : cache froid, cache chaud, hors ligne.
5. **Consigner la date, la version de l'app, le commit** de chaque campagne.

### 7.2 Ce qu'on mesure en priorité

| Priorité | Mesure | Pourquoi d'abord |
|---|---|---|
| 1 | Premier écran utile, PWA, cache froid | c'est le seuil du cahier des charges |
| 2 | Premier écran utile, Flutter, démarrage à froid | idem, autre surface |
| 3 | Poids APK arm64 | conditionne la distribution |
| 4 | Poids du premier écran PWA | conditionne le forfait data |
| 5 | Mémoire résidente Flutter | conditionne la survie en arrière-plan |

### 7.3 Règle d'ajustement

Si la mesure contredit la proposition, **c'est la mesure qui gagne** — mais l'ajustement doit être écrit ici avec sa justification, pas appliqué silencieusement dans un test. Un budget ajusté sans trace est un budget qui n'existe plus.

**Cas particulier** : si le premier écran utile dépasse 3 s sur l'appareil de référence, on ne relève **pas** le budget à 4 s. On documente l'écart, on identifie la cause (poids JS ? requête lente ? rendu ?), et le lot reste ouvert. Relever un seuil pour fermer un lot est exactement ce que la règle « un lot ne se ferme pas si un seuil est dépassé » interdit.

---

## 8. Où chaque budget est vérifié

| Budget | Local (PR) | CI (bloquant) | Campagne (jalon) |
|---|---|---|---|
| Poids JS/CSS/écran PWA | ✅ | ✅ | — |
| Poids des images | ✅ | ✅ | — |
| Nombre de requêtes | ✅ | ✅ | — |
| Lighthouse (score + LCP/INP/CLS) | ✅ | ✅ (seuils larges) | ✅ (seuils fins) |
| Premier écran utile (réel) | — | — | ✅ |
| Poids APK / AAB | ✅ | ✅ | — |
| Démarrage Flutter (réel) | — | — | ✅ |
| Mémoire résidente | — | — | ✅ |
| Plantages e2e | — | ✅ | — |
| Couverture | ✅ | ✅ | — |
| Accessibilité AA | ✅ | ✅ | — |
| i18n 4 locales | ✅ | ✅ | — |

**Lecture** : la CI attrape les régressions **structurelles** (poids, requêtes, couverture, i18n, AA). La campagne attrape la **performance perçue**. Aucune des deux ne remplace l'autre — une PR peut passer toute la CI et dégrader le premier écran utile de 800 ms.

---

## 9. Budgets de données de développement

Le générateur de données synthétiques (`api/app/Console/Commands/PublicSyntheticDataCommand.php`) doit rester dans un budget, sinon il devient inutilisable en local :

| Élément | Cible | Plafond |
|---|---|---|
| Temps d'exécution (3 villes, ~60 commerces) | ≤ 20 s | 45 s |
| Nombre de tenants créés | 3 (un par ville) | 3 |
| Commerces par ville | 15–25 | 30 |
| Taille de la base générée | ≤ 40 Mo | 80 Mo |
| Idempotence | rejouer ne crée aucun doublon | — |
| Nettoyage (`--clean`) | supprime tout ce qui a été créé | — |

**Pourquoi 3 tenants et pas 1** : les trois villes doivent être **isolées** pour que les tests d'isolation inter-tenant aient un objet réel. Un générateur mono-tenant ne peut pas prouver qu'un employé de la ville A ne voit pas les données de la ville B.

---

## 10. Ce que ce document ne couvre pas

- **Budgets de l'API** (p95 par endpoint) : ils existent déjà dans `dev-hub/tools/performance-budgets.json` et ne relèvent pas de ce couloir. Les endpoints publics y seront ajoutés quand ils seront livrés (M2/M3), pas avant.
- **Budgets de coût d'infrastructure** : hors périmètre (lancement, ville pilote).
- **Budgets de la ville pilote** : hors périmètre par décision du cahier des charges.
- **Mesures réelles** : voir §7 — c'est le travail de M0, pas de ce document.

---

## 11. Décisions en attente

| # | Décision | Qui | Impact si non tranchée |
|---|---|---|---|
| B1 | Modèle exact de l'appareil de référence | Lead + design | les budgets de temps restent non calibrés |
| B2 | Achat ou emprunt de l'appareil | Lead (porte (c) : dépense) | bloque la campagne §7 |
| B3 | Seuils Lighthouse bloquants en CI vs campagne | Lead + qualité | la CI peut fermer un lot sur du bruit |
| B4 | Politique de dégradation des images (placeholder de marque) | Design | le budget image reste théorique |
| B5 | Budget mémoire Flutter : 180 Mo est-il atteignable sur 3 Go ? | Mobile | à mesurer, pas à supposer |

**B2 est une porte (c)** : l'achat d'un appareil est une dépense nouvelle. Aucune dépense n'est engagée par ce document.
