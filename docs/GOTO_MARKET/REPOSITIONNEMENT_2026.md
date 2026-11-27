# Repositionnement 2026 — par où démarrer, et pourquoi

> **Statut** : document de décision · **Date** : 2026-11-27 · **Portée** : périmètre de lancement, go-to-market, séquence.
> **Issues liées** : #10001 (étude & réduction de périmètre), #10002 (onglet marketing profils/modules), #10003 (mission & livraison).
> **Sources internes** : `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`, `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/01_AUDIT_MARCHE_ET_PRODUIT.md`, `03_DIRECTION_COMMERCIALE_ET_OFFRES.md`, `docs/REFERENTIEL_PRODUIT/APV.md`.
> **Rappel de catégorie** : Leopardo est une **suite métier** (business suite). Jamais « logiciel RH », « HR SaaS » ni « Mobile-First Company OS » comme catégorie.

---

## 1. Le constat, sans détour

Le dépôt porte **41 modules API**, **12 applications Flutter**, **56 modules de recette**, **32 profils** et **994 endpoints**. C'est un actif d'ingénierie rare. Et il n'a **aucune traction** : 14 stars, 2 abonnés, 0 revenu publié.

Le problème n'est donc pas la capacité de construire. Le problème est que **le produit répond à toutes les questions sauf à la seule qui compte au lancement : « pour qui, d'abord ? »**

Un périmètre large n'est pas un avantage au lancement : c'est un **coût de distribution**. Chaque module supplémentaire ajoute une page de vente, un argumentaire, un cas de test, une objection à traiter — et dilue le message. Le marché n'achète pas « plus de modules » ; il achète **moins de dispersion**.

**Décision de ce document** : ne pas réduire le *code* (il est écrit, il ne coûte plus rien à exister), mais réduire le **périmètre de lancement** — ce qu'on vend, à qui, dans quel ordre.

---

## 2. Ce que le marché achète réellement (2026)

Lecture par **besoin**, pas par module. Sept besoins, classés par intensité de douleur :

| # | Besoin réel | Intensité | Ce que Leopardo a déjà |
|---|---|---|---|
| 1 | **Unification** — moins d'outils, moins de double saisie, moins d'Excel | 🔴 Très forte | Suite unique : RH, paie, pointage, CRM, compta, opérations |
| 2 | **Paie et présence fiables** — erreurs de paie et absences = douleur immédiate | 🔴 Très forte | Moteur de paie multi-pays (DZ, MA, TN, FR, TR, SN) + pointage GPS/kiosque |
| 3 | **Mobile self-service** — l'employé agit sans attendre le RH | 🟠 Forte | App Employee mobile-first, kiosque biométrique, mode hors-ligne |
| 4 | **Conformité locale** — paie, jours fériés, RGPD, audit | 🟠 Forte | Référentiels pays, calendriers islamiques, registre RGPD |
| 5 | **Automatisation** — workflows, approbations, documents, notifications | 🟠 Forte | Workflows, notifications, génération PDF |
| 6 | **Analytics actionnables** — alertes et décisions, pas des graphiques | 🟡 Moyenne | Tableaux de bord, anomalies de paie |
| 7 | **IA utile mais gouvernée** — copilote, pas boîte noire | 🟡 Moyenne | Assistant Leo (scaffoldé, derrière feature flag) |

**Conclusion de lecture** : les besoins 1 et 2 sont **douloureux aujourd'hui** et Leopardo y est **déjà fort**. Les besoins 6 et 7 sont **désirables mais pas déclencheurs d'achat** en PME de terrain. On lance donc sur 1+2, on garde 3+4 comme différenciateurs, et 5-7 comme extension.

---

## 3. Le segment qui paie : PME de terrain, Afrique francophone d'abord

### 3.1 Le segment primaire (à attaquer maintenant)

**PME de terrain de 5 à 150 salariés, en Algérie, au Maroc et en Tunisie**, dans les secteurs où le travail est **horaire, multi-sites et à preuve terrain** :

- sécurité privée, BTP, logistique, restauration, commerce multi-sites, santé, services.

Pourquoi ce segment :

1. **La douleur est quotidienne** (pointage, horaires, absences, avances, preuve terrain) — plus forte que dans un bureau classique.
2. **Leopardo y est déjà conforme** : les référentiels DZ/MA/TN existent et tournent (vérifié en exécution, §6).
3. **Le canal est identifiable** : les **cabinets comptables** gèrent des portefeuilles de PME et deviennent prescripteurs (voir §5).
4. **La concurrence y est faible** : les suites internationales (Odoo, Deel, PaySpace) n'ont pas de paie locale officielle DZ/MA/TN.

### 3.2 Le segment secondaire (à ouvrir ensuite)

**Diaspora et filiales francophones en Europe** (France, Belgique) : mêmes besoins, paie FR déjà codée, mais **concurrence dense** (PayFit, Lucca, Silae). À ouvrir **après** une première preuve de traction en Afrique du Nord, pas avant.

### 3.3 Ce qu'on ne fait PAS au lancement

- Pas de marché anglophone (US/UK) : paie codée mais zéro canal, zéro référence, concurrence maximale.
- Pas de verticales exotiques (Farm OS, Trade OS) : elles exigent des environnements de démo qui n'existent pas encore.
- Pas de vente « suite complète » : c'est le message qui a échoué jusqu'ici.

---

## 4. Par où démarrer — la recommandation

### 4.1 Le point de départ : **Paie + Pointage, vendus ensemble, en Algérie**

**C'est la réponse à « par où démarrer ».**

Lancer **un seul pack**, sur **un seul pays**, avec **un seul message** :

> **« La paie et le pointage de vos équipes de terrain, dans une seule application. »**

Pourquoi ce pack précisément :

| Critère | Pourquoi Paie + Pointage gagne |
|---|---|
| **Douleur** | Erreur de paie et contestation d'heures = douleur immédiate, chiffrable, non négociable |
| **Preuve technique** | Le moteur tourne réellement : run DZ calculé, validé, verrouillé, PDF généré (§6) |
| **Conformité** | Référentiel DZ (IRG, CNAS) présent ; c'est le fossé face aux suites internationales |
| **Boucle fermée** | Pointage → heures → paie → bulletin : la valeur se démontre en une démo de 20 minutes |
| **Prix défendable** | On facture un résultat (paie juste, heures prouvées), pas des modules |
| **Canal** | Le cabinet comptable vend exactement ça à son portefeuille |

### 4.2 Les 3 modules à mettre en avant (et seulement 3)

1. **Paie multi-pays** — le moteur, la conformité, le bulletin PDF.
2. **Pointage & présence** — mobile, GPS, kiosque biométrique, hors-ligne.
3. **RH & absences** — dossiers employés, contrats, congés, documents.

Tout le reste (CRM, comptabilité, verticales, IA, caméras, flotte…) reste **dans le produit**, activable, mais **hors du message de lancement**. C'est la différence entre « ce que le produit sait faire » et « ce qu'on vend d'abord ».

### 4.3 Les 2 profils à cibler en priorité

1. **Le dirigeant de PME de terrain** (manager principal) — il signe, il veut la paix sur la paie.
2. **Le cabinet comptable** (comptable-cabinet) — il prescrit, il gère plusieurs dossiers, il multiplie la distribution.

Le profil « employé » n'est pas une cible de vente : c'est un **utilisateur** dont l'adoption fait la rétention.

### 4.4 La séquence de lancement (90 jours)

| Phase | Durée | Objectif | Livrable |
|---|---|---|---|
| **P0 — Gel du message** | Semaine 1 | Un seul pitch, un seul pack, un seul pays | Page de vente « Paie + Pointage DZ » |
| **P1 — 3 pilotes** | Semaines 2-6 | 3 PME algériennes en production réelle | 3 cas d'usage documentés |
| **P2 — Canal cabinet** | Semaines 4-10 | 2 cabinets comptables partenaires | Kit partenaire + 1 dossier pilote |
| **P3 — Preuve publique** | Semaines 8-12 | Références publiables | 2 témoignages + 1 étude de cas chiffrée |

**Condition de passage P1 → P2** : au moins **1 pilote avec paie réelle validée** par un comptable externe.

---

## 5. Le canal qui change tout : le cabinet comptable

C'est l'angle le plus sous-exploité du dépôt. Un cabinet comptable gère **20 à 200 PME**. S'il adopte Leopardo, il ne vend pas un logiciel : il **industrialise sa production de paie** et devient un canal de distribution à coût d'acquisition quasi nul.

**Ce qu'il faut lui donner** (et qui existe déjà en partie) :

- un **espace cabinet** multi-dossiers (module `Cabinet` présent) ;
- des **exports comptables** propres (journal de paie, grand livre — vérifiés en exécution) ;
- une **grille de marge** claire (il revend, il garde une commission) ;
- un **argument de conformité** : « la paie DZ est dans l'outil, pas dans un tableur ».

**Pourquoi ça marche** : le cabinet ne prend pas de risque technologique, il prend un risque de **productivité**. C'est un achat rationnel, pas un pari.

---

## 6. Preuve d'exécution — le socle payant tourne réellement

Le socle a été **démarré et testé** dans un environnement local reconstruit (PHP 8.4, PostgreSQL 15 + PostGIS, Redis), pas seulement lu :

| Test | Résultat observé |
|---|---|
| `GET /api/v1/health` | **200** |
| Login démo (`ahmed.benali@techcorp-algerie.dz`) | Token émis, contexte tenant DZ |
| `POST /api/v1/payroll-runs` + `/calculate` (DZ) | **9 bulletins**, 842 059,10 DZD brut, 626 085,54 DZD net, 1 060 994,47 DZD coût employeur |
| `/validate` puis `/lock` | Statuts `validated` → `locked` |
| `GET /api/v1/pay-slips/1/pdf` | **PDF de 879 730 octets** généré |
| `GET /api/v1/payroll-runs/1/anomalies` | 0 anomalie |
| Run Maroc (`MoroccoPayrollRules`) | 6 bulletins, 53 614,27 MAD brut |
| Moteurs pays en direct | **DZ, MA, TN, FR, TR, SN** calculent tous |

**Ce que ça prouve** : la partie payante n'est pas une promesse, c'est un moteur qui calcule, valide, verrouille et produit des bulletins. **Ce que ça ne prouve pas** : la conformité légale (les référentiels sont en niveau `pilot`, à valider par un conseil local) ni la charge en production.

---

## 7. Scénario de repli — si vous choisissez de tout lancer

Vous pouvez lancer le périmètre complet. Voici comment le faire **sans diluer le message** :

1. **Un seul message public** reste « Paie + Pointage » — c'est l'accroche.
2. **Tout le reste devient « packs métier activables »** : une page par verticale, atteignable depuis la page principale, jamais dans le pitch d'entrée.
3. **Un ordre de mise en avant** : Paie/Pointage → RH → Comptabilité → CRM → verticales → IA.
4. **Un seul pays en avant** (DZ) ; les autres pays en « disponible sur demande ».

Le périmètre complet devient alors un **catalogue**, pas un argumentaire. C'est la seule façon de tout garder sans tout diluer.

---

## 8. Critères d'arrêt (kill criteria)

À évaluer à **J+90** :

| Signal | Seuil | Décision |
|---|---|---|
| Pilotes actifs | < 2 sur 3 | Revoir le segment, pas le produit |
| Paie validée par un comptable externe | 0 | **Bloquant** : la conformité n'est pas prouvée |
| Cabinets partenaires signés | 0 | Le canal ne fonctionne pas → repenser la distribution |
| Coût d'acquisition | > 3 mois de revenu par client | Le modèle économique ne tient pas |
| Rétention à 60 jours | < 70 % | Le produit n'est pas adopté → revoir l'onboarding |

**Règle** : si deux critères sont franchis négativement, on **arrête d'élargir** et on revient à un seul pilote, un seul pays, un seul module.

---

## 9. Ce que ce document change concrètement

| Avant | Après |
|---|---|
| « Suite métier complète pour entreprises de terrain » | « La paie et le pointage de vos équipes de terrain » |
| 41 modules en avant | 3 modules en avant, 38 en catalogue |
| 6 pays en avant | 1 pays en avant (DZ), 5 sur demande |
| 32 profils | 2 cibles (dirigeant PME, cabinet comptable) |
| Vente directe | Vente directe + canal cabinet comptable |

**Le produit ne change pas. Le message change. C'est tout l'enjeu du repositionnement.**

---

## 10. Prochaines actions

- [ ] Valider le pack de lancement « Paie + Pointage DZ » (issue #10001).
- [ ] Publier l'onglet marketing « Profils & modules » (issue #10002).
- [ ] Recruter 3 PME pilotes à Alger + 2 cabinets comptables.
- [ ] Faire valider le référentiel de paie DZ par un conseil fiscal local.
- [ ] Construire la page de vente unique et le kit partenaire cabinet.
