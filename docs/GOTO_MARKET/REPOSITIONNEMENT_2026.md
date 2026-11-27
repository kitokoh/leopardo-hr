# Repositionnement 2026 — cible grands comptes

> **Statut** : document de décision · **Version** : v2 (grand compte) · **Date** : 2026-11-28 · **Issue** : #10024
> **Remplace** : la v1 du 2026-11-27 (cible PME de terrain, canal cabinet comptable).
> **Étude de référence** : [`ETUDE_MARCHE_GRAND_COMPTE_2026.md`](ETUDE_MARCHE_GRAND_COMPTE_2026.md).
> **Cadre bloquant** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (pitch canonique, promesses interdites), `METRIQUES_VITRINE.md` (chiffres datés uniquement).
> **Rappel de catégorie** : Leopardo est une **suite métier**. Jamais « logiciel RH », « HR SaaS » ni « Business OS ».

---

## 1. Le constat, sans détour

Le dépôt porte **42 modules API**, **12 applications Flutter**, **56 modules de recette**, **32 profils** et **994 endpoints**. C'est un actif d'ingénierie rare. Et il n'a **aucune traction** : 14 stars, 2 abonnés, 0 revenu publié.

Le problème n'est pas la capacité de construire. Le problème est que le produit répond à toutes les questions sauf à la seule qui compte au lancement : **« pour qui, d'abord ? »**

La v1 de ce document répondait « la PME de terrain de 5 à 150 salariés ». **Cette réponse est abandonnée.** Elle avait un défaut structurel : un ticket de 1 500 à 6 000 € par an ne finance pas un cycle de vente, et une PME ne produit pas de référence capable d'ouvrir un marché.

**Décision v2 : viser le groupe multi-entités de 500 à 5 000 salariés.** Un seul gros poisson vaut mieux que cinquante petits — non par préférence, mais par arithmétique : 72 000 €/an sur 5 ans avec 95 % de rétention, contre 3 000 €/an avec 75 % de rétention.

**Ce que ça ne change pas :** le code. Il est écrit, il ne coûte plus rien à exister. Ce qui change, c'est **ce qu'on vend, à qui, et ce qu'il faut construire avant de vendre**.

---

## 2. Ce que le marché achète réellement (2026)

Lecture par **besoin**, pas par module. Sept besoins, classés par intensité de douleur — et cette fois pour un groupe, pas pour une PME.

| # | Besoin réel | Intensité | Ce que Leopardo a déjà | Écart à combler |
|---|---|---|---|---|
| 1 | **Une paie juste, par entité et consolidée** | 🔴 Critique | Moteur multi-pays (21 pays résolus), runs DZ/MA vérifiés | **Multi-entités non modélisé** |
| 2 | **Prouver les heures à l'échelle** | 🔴 Critique | Pointage mobile/GPS/kiosque, EdgeSync hors-ligne | Charge non testée à 1 000+ |
| 3 | **Contrôler et auditer** | 🔴 Critique | RBAC, SSO OIDC, journal IA | **Pas d'audit immuable général** |
| 4 | **S'intégrer au SI existant** | 🟠 Forte | API versionnée, 994 endpoints, exports comptables | **Pas de SCIM** |
| 5 | **Maîtriser l'hébergement** | 🟠 Forte | Auto-hébergement (MIT, Docker, Render) | À documenter |
| 6 | **Consolider le reporting groupe** | 🟡 Moyenne | Rapports Platform (headcount, turnover, absentéisme) | Pas de consolidation |
| 7 | **IA gouvernée** | 🟡 Moyenne | Assistant Leo, journal d'audit IA | Secondaire |

**La différence avec la PME :** en PME, seuls les besoins 1 et 2 déclenchaient l'achat. En grand compte, **le besoin 3 est aussi critique que les deux premiers** — un groupe n'achète pas la paie sans le contrôle. C'est pourquoi le troisième pilier du message devient **Contrôle & conformité**, et non plus « RH & absences ».

---

## 3. Le segment : groupe multi-entités, 500 à 5 000 salariés

### 3.1 Le profil précis

**Groupe privé familial ou holding diversifiée, 800 à 3 000 salariés, 5 à 12 entités juridiques, secteurs à main-d'œuvre horaire.**

Secteurs, par ordre de priorité :

1. **Distribution & retail multi-sites** — 40 à 200 points de vente, horaires décalés, paie par entité régionale.
2. **BTP & travaux publics** — chantiers mobiles, pointage géolocalisé, heures contestées.
3. **Agroalimentaire & industrie** — 3 équipes, kiosques biométriques, conventions multiples.
4. **Santé privée** — gardes, astreintes, plusieurs établissements.
5. **Transport & logistique** — chauffeurs, heures de conduite, multi-dépôts.
6. **Concessions automobiles & équipement** — plusieurs marques, plusieurs sociétés.

### 3.2 Pourquoi ce segment et pas un autre

| Critère | Pourquoi le groupe multi-entités gagne |
|---|---|
| **Budget** | 10 à 50 M DZD/an de masse salariale administrative ; un DAF qui signe |
| **Complexité** | 4 à 15 entités, plusieurs conventions, 2-3 pays : le changement se justifie |
| **Non servi** | Workday/SAP/Oracle ne font pas la paie DZ/MA/TN ; les ERP locaux ne font ni pointage ni multi-pays |
| **Économie** | Ticket 30-80 k€/an, rétention 90-95 %, valeur de référence très forte |
| **Décision** | Famille propriétaire ou dirigeant actionnaire : décision rapide, pas de comité de 12 personnes |

### 3.3 Ce qu'on ne vise pas

- **Multinationale 20 000+ salariés** : SOC 2, ISO 27001, 3 références du même secteur. Objectif à 24 mois, pas plan de lancement.
- **Secteur public et entreprises d'État** : appels d'offres, agréments, cycles de 18 mois.
- **Banques et assurances** : exigences réglementaires hors de portée aujourd'hui.
- **PME de 5 à 150 salariés** : flux entrant qu'on ne refuse pas, mais qui ne structure ni le message ni l'organisation commerciale.
- **Cabinet comptable comme canal principal** : un cabinet gère des PME. Ce n'est plus le cœur du discours — il reste un apporteur d'affaires occasionnel, pas un canal.

---

## 4. Par où démarrer — la recommandation

### 4.1 Le point de départ : **un pilote payant sur une entité d'un groupe**

**C'est la réponse à « par où démarrer ».**

Pas un POC gratuit sur l'ensemble du groupe — il ne se termine jamais et ne se signe jamais. Pas un déploiement complet — il échoue et ferme le marché.

**Un pilote payant, 12 semaines, une entité, un site, 150 à 400 salariés, 8 000 à 15 000 €.**

Le message, unique :

> **« Une seule paie pour toutes vos entités, et la preuve des heures de chaque site. »**

Pourquoi ce pilote précisément :

| Critère | Pourquoi il gagne |
|---|---|
| **Douleur** | Paie fausse et heures contestées = douleur chiffrable en dinars, non négociable |
| **Preuve technique** | Le moteur tourne : run DZ calculé, validé, verrouillé, PDF généré (§6) |
| **Conformité** | Référentiel DZ présent ; c'est le fossé face aux suites internationales |
| **Boucle fermée** | Pointage → heures → paie → bulletin : démontrable en 20 minutes |
| **Risque maîtrisé** | Une entité, pas le groupe : le DAF peut signer sans engager sa carrière |
| **Sortie** | Succès → contrat groupe. Échec → on documente et on corrige avant le suivant |

### 4.2 Les 3 modules à mettre en avant (et seulement 3)

1. **Paie multi-pays & multi-entités** — un moteur, 21 pays, une paie par entité et un total groupe.
2. **Pointage & preuve des heures à l'échelle** — mobile, GPS, kiosque biométrique, hors-ligne.
3. **Contrôle & conformité** — audit, exports comptables, RBAC, SSO, hébergement maîtrisé.

Tout le reste — RH & absences, CRM, comptabilité, notes de frais, recrutement, formation, les 8 verticales, agriculture, négoce — reste **dans le produit**, activable, mais **hors du message de lancement**. C'est la différence entre « ce que le produit sait faire » et « ce qu'on vend d'abord ».

### 4.3 Les interlocuteurs à cibler

Un grand compte n'a pas un décideur, il a **cinq à huit**. Les ignorer est la première cause d'échec.

| Rôle | Ce qu'il veut | Ce qu'il peut bloquer | Quand l'approcher |
|---|---|---|---|
| **DAF** (entrée) | Une paie juste, un coût maîtrisé, pas de redressement | Le budget | **Premier** — c'est lui qui chiffre le coût d'une paie fausse |
| **DRH groupe** | Moins de ressaisie, des dossiers propres, du self-service | L'adoption | Semaine 2 |
| **DSI** | SSO, API, hébergement, intégration à l'ERP | **Techniquement, tout** | Semaine 2-3 |
| **RSSI** | Audit, chiffrement, résidence, PRA | La revue de sécurité | Semaine 3-4 |
| **Achats** | Prix, contrat, conditions | La signature | Fin |
| **DG / famille** | Le risque, la réputation, la continuité | Tout, en dernier recours | Clôture |

**Règle :** on entre par le **DAF**, jamais par la DRH. Dans un groupe, la DRH gère les personnes ; le DAF paie et subit les erreurs.

### 4.4 La séquence de lancement (180 jours)

| Phase | Durée | Objectif | Livrable |
|---|---|---|---|
| **P0 — Mise à niveau bloquante** | Semaines 1-6 | SAML + multi-entités + audit immuable | Build démontrable à un DSI |
| **P1 — Dossier de sécurité** | Semaines 3-8 | Répondre à un questionnaire de 60 questions | Dossier + architecture + PRA |
| **P2 — Un pilote payant** | Semaines 6-16 | Une entité d'un groupe, paie réelle + un site pointé | 1 référence chiffrée |
| **P3 — Deuxième groupe** | Semaines 12-24 | Répliquer sur un secteur différent | 2 références |

**Condition de passage P1 → P2 :** SAML fonctionnel avec Entra ID **et** ADFS, testé par le DSI du client — pas par nous.

---

## 5. Le cycle de décision — ce qui change tout

C'est la différence structurelle avec la PME, et c'est ce qui doit réorganiser l'entreprise.

| Étape | Durée | Ce qui se passe | Ce qu'il faut fournir |
|---|---|---|---|
| **1. Prise de contact** | 1-2 sem. | Le DAF reconnaît la douleur | Un message, pas une brochure |
| **2. Découverte** | 2-3 sem. | Cartographie des entités, des conventions, du SI | Un questionnaire d'audit |
| **3. Démonstration** | 1 sem. | La boucle fermée sur **leurs** données | Un jeu de données anonymisé du client |
| **4. Revue technique** | 3-6 sem. | DSI + RSSI : SSO, API, audit, hébergement | Dossier de sécurité, architecture, PRA |
| **5. Pilote** | 12 sem. | Une entité en production réelle | Contrat de pilote, critères de succès écrits |
| **6. Négociation** | 4-8 sem. | Achats, prix, contrat, SLA | Grille tarifaire, contrat type, SLA |
| **7. Déploiement** | 8-16 sem. | Extension aux autres entités | Plan de déploiement, formation |

**Total : 6 à 12 mois.** C'est long, c'est coûteux, et c'est le prix du ticket à 72 000 €.

**Conséquence sur l'organisation :** il faut **2 commerciaux** capables de parler à un DAF et à un DSI, **1 avant-vente technique**, et **1 responsable de la conformité** (interne ou externe). Une équipe de 4, pas un fondateur seul.

---

## 6. Preuve d'exécution — le socle payant tourne réellement

Le socle a été **démarré et testé** dans un environnement local reconstruit (PHP 8.4, PostgreSQL 15 + PostGIS, Redis), pas seulement lu :

| Test | Résultat observé |
|---|---|
| `GET /api/v1/health` | **200** |
| Login démo (compte seedé du tenant DZ) | Token émis, contexte tenant DZ |
| `POST /api/v1/payroll-runs` + `/calculate` (DZ) | **9 bulletins**, 842 059,10 DZD brut, 626 085,54 DZD net, 1 060 994,47 DZD coût employeur |
| `/validate` puis `/lock` | Statuts `validated` → `locked` |
| `GET /api/v1/pay-slips/1/pdf` | **PDF de 879 730 octets** généré |
| `GET /api/v1/payroll-runs/1/anomalies` | 0 anomalie |
| Run Maroc (`MoroccoPayrollRules`) | 6 bulletins, 53 614,27 MAD brut |
| Moteurs pays en direct | **21 pays** résolus (DZ, MA, TN, FR, TR, SN, CM, CF, TD, CG, GA, GQ, CI, ML, BF, BJ, TG, NE, CA, GB, US) |

**Ce que ça prouve :** la partie payante n'est pas une promesse, c'est un moteur qui calcule, valide, verrouille et produit des bulletins.

**Ce que ça ne prouve pas :** la conformité légale (référentiels en statut `pilot`, à valider par un conseil local), la tenue en charge à 1 000+ salariés, et le multi-entités (non modélisé).

---

## 7. Les écarts enterprise — ce qu'il faut construire avant de vendre

Issu d'une **lecture du code**, pas d'une intention. Détail complet dans l'étude §7.

| Chantier | Bloquant ? | Effort | Quand |
|---|---|---|---|
| **SSO SAML** — `SSOService.php` déclare `'saml' => false` et refuse la connexion | 🔴 Oui | 2-3 sem. | **Mois 1** |
| **Multi-entités + consolidation** — pas d'entité juridique, pas de vue groupe | 🔴 Oui | 6-10 sem. | **Mois 1-3** |
| **Audit immuable** — seul le journal IA existe | 🔴 Oui | 3-4 sem. | **Mois 2** |
| **SCIM** — aucune occurrence dans le code | 🟠 Non | 3-4 sem. | Mois 3-4 |
| **Test de charge 1 000+** | 🟠 Non | 2 sem. | Mois 3 |
| **Validation paie par conseil local** | 🔴 Oui | 4-6 sem. | Mois 1-2 |
| **SOC 2 Type II** | 🟡 Non (étage B) | 6-12 mois | Mois 6+ |

**Total pour être vendable : 4 à 5 mois de développement.** C'est le prix d'entrée du gros poisson. Il n'y a pas de raccourci, et le dire maintenant évite de brûler un pilote.

---

## 8. Scénario de repli — si vous choisissez de tout lancer

Vous pouvez lancer le périmètre complet. Voici comment le faire **sans diluer le message** :

1. **Un seul message public** reste « une paie pour toutes vos entités » — c'est l'accroche.
2. **Tout le reste devient « packs métier activables »** : une page par verticale, atteignable depuis la page principale, jamais dans le pitch d'entrée.
3. **Un ordre de mise en avant** : Paie/Pointage/Contrôle → RH → Comptabilité → CRM → verticales → IA.
4. **Trois pays en avant** (DZ, MA, TN) ; les 18 autres en « disponible sur demande ».

Le périmètre complet devient alors un **catalogue**, pas un argumentaire.

---

## 9. Critères d'arrêt (kill criteria)

À évaluer à **J+180** :

| Signal | Seuil | Décision |
|---|---|---|
| SAML + multi-entités livrés | Non | **Bloquant** : on ne démarre pas la prospection |
| Groupes en discussion avancée | < 3 | Le message ne passe pas → revoir l'accroche, pas le produit |
| Pilote payant signé | 0 | Le segment ne convertit pas → **redescendre à l'étage C** (ETI 200-500) |
| Cycle réel | > 14 mois | Le modèle ne tient pas la trésorerie → chercher un canal intégrateur |
| Coût d'acquisition | > 40 k€ | Le ticket ne couvre pas l'acquisition → monter l'ARPU ou changer de canal |

**Règle :** si deux critères sont franchis négativement, on **redescend d'un étage** plutôt que d'élargir. Un groupe de 300 salariés signé vaut mieux que six groupes en discussion.

---

## 10. Ce que ce document change concrètement

| Avant (v1, PME) | Après (v2, grand compte) |
|---|---|
| « La paie et le pointage de vos équipes de terrain » | « **Une seule paie pour toutes vos entités, et la preuve des heures de chaque site** » |
| PME de terrain 5-150 salariés | **Groupe multi-entités 500-5 000 salariés** |
| Cabinet comptable = canal principal | **Vente directe + intégrateurs SI** |
| 3 modules : Paie, Pointage, RH/absences | 3 modules : Paie multi-entités, Pointage, **Contrôle** |
| 38 modules en catalogue | 39 modules en catalogue |
| 1 pays en avant (DZ) | **3 pays** (DZ, MA, TN) |
| 2 cibles (dirigeant PME, cabinet) | **6 interlocuteurs** (DAF, DRH, DSI, RSSI, achats, DG) |
| « Essayer 14 jours » | **« Pilote payant 12 semaines »** |
| Ticket 1,5-6 k€/an | **Ticket 30-80 k€/an** |
| Cycle 2-6 semaines | Cycle 6-12 mois |

**Le produit ne change pas. Le client change. Et avec lui, ce qu'il faut construire avant de vendre.**

---

## 11. Prochaines actions

- [ ] Implémenter SAML 2.0 (issue dédiée, mois 1).
- [ ] Modéliser groupe → entité juridique → établissement, avec consolidation (mois 1-3).
- [ ] Ajouter la piste d'audit immuable (mois 2).
- [ ] Faire valider le référentiel de paie DZ par un conseil fiscal local.
- [ ] Constituer le dossier de sécurité (60 questions type).
- [ ] Identifier 10 groupes cibles selon les 6 critères de l'étude §9.2.
- [ ] Signer 1 pilote payant sur une entité.
