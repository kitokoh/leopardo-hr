# Étude de marché — cible grands comptes (2026)

> **Statut** : document de décision · **Date** : 2026-11-28 · **Issue** : #10023
> **Remplace** : la cible « PME de terrain 5-150 salariés » de la v1 du repositionnement.
> **Cadre bloquant** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (promesses interdites), `METRIQUES_VITRINE.md` (chiffres datés uniquement).
> **Rappel de catégorie** : Leopardo est une **suite métier**. Jamais « logiciel RH », « HR SaaS » ni « Business OS ».

---

## 0. La décision, en une page

**Cible : le groupe privé multi-entités de 500 à 5 000 salariés, en Algérie d'abord, puis Maroc et Tunisie.**

Pas la multinationale de 20 000 salariés — elle n'achètera pas avant 18 à 24 mois, et le dire franchement fait gagner du temps. Pas la PME de 30 salariés — elle ne paie pas assez pour financer un cycle de vente long.

Le **groupe national multi-entités** est le seul segment qui réunit les quatre conditions :

1. **Il a le budget** — 10 à 50 M DZD/an de masse salariale administrative, un DAF qui signe.
2. **Il a la complexité qui justifie un changement** — 4 à 15 entités juridiques, plusieurs conventions collectives, des filiales dans 2 ou 3 pays, des sites industriels et des sièges.
3. **Il n'est pas servi** — Workday, SAP SuccessFactors et Oracle HCM ne font pas la paie algérienne, coûtent 95 à 180 $ par salarié et par an, et exigent 9 à 18 mois d'intégration. Les ERP locaux (IntelliX, S2I, Wanasis) font de la paie mais pas de pointage terrain ni de multi-pays.
4. **Il achète par référence** — un groupe parle à un autre groupe. Deux références ouvrent un secteur entier.

**Le message :** « Une seule paie pour toutes vos entités, et la preuve des heures de chaque site. »

**Par où démarrer :** un pilote payant sur **une seule entité** d'un groupe, avec la paie réelle et le pointage d'un site — pas un POC gratuit sur l'ensemble.

**Ce qui bloque aujourd'hui :** le SSO SAML n'est pas implémenté (vérifié dans le code, §8.1). Aucun grand compte ne signe sans SAML. C'est le premier chantier, avant tout effort commercial.

---

## 1. Ce que « grand compte » veut dire — trois étages, un seul attaquable

Le mot « grand compte » recouvre trois réalités très différentes. Les confondre est l'erreur qui fait échouer les plans de vente.

| Étage | Taille | Qui décide | Ce qu'il exige | Attaquable en 2026 ? |
|---|---|---|---|---|
| **A — Multinationale** | 20 000+ salariés, 10+ pays | Comité groupe, DSI centrale, achats | SOC 2 Type II, ISO 27001, SAML + SCIM, audit immuable 7 ans, résidence des données, 10× la charge testée, 3 références du même secteur | ❌ **Non.** 18-24 mois de mise à niveau. |
| **B — Groupe national multi-entités** | 500-5 000 salariés, 2-15 entités, 1-3 pays | DG, DAF, DRH groupe, DSI | Paie locale juste, multi-entités, pointage terrain, exports comptables, SSO, hébergement maîtrisé | ✅ **Oui — c'est la cible.** |
| **C — ETI mono-site** | 200-500 salariés | DRH, DAF | Paie + pointage, prix, mise en route rapide | ⚠️ Oui, mais ticket trop faible pour financer le cycle. |

**Décision : étage B.** L'étage C reste un flux entrant (inbound) qu'on ne refuse pas, mais il ne structure ni le message ni l'organisation commerciale. L'étage A est un objectif à 24 mois, pas un plan de lancement.

### 1.1 Le profil précis à viser en premier

**Groupe privé familial ou holding diversifiée, 800 à 3 000 salariés, 5 à 12 entités juridiques, secteurs à main-d'œuvre horaire.**

Secteurs, par ordre de priorité :

1. **Distribution & retail multi-sites** — 40 à 200 points de vente, des centaines de vendeurs, des horaires décalés, une paie par entité régionale.
2. **BTP & travaux publics** — chantiers mobiles, pointage géolocalisé, heures supplémentaires contestées, intérim.
3. **Agroalimentaire & industrie** — 3 équipes, kiosques biométriques, conventions collectives multiples.
4. **Santé privée (cliniques, groupes de polycliniques)** — gardes, astreintes, paie complexe, plusieurs établissements.
5. **Transport & logistique** — chauffeurs, heures de conduite, multi-dépôts.
6. **Concessions automobiles & équipement** — plusieurs marques, plusieurs sociétés, un siège.

**Pourquoi ces six-là :** ce sont les seuls où la douleur (prouver les heures, payer juste, consolider) est chiffrable en dinars, et où le pointage terrain est un besoin de premier rang — pas un accessoire.

### 1.2 Ce qu'on ne vise pas

- **Secteur public et entreprises d'État** : appels d'offres, agréments, cycles de 18 mois, exigences de souveraineté non couvertes. À reconsidérer après deux références privées.
- **Banques et assurances** : exigences réglementaires (audit, continuité, homologation) hors de portée aujourd'hui.
- **Multinationales déjà équipées de Workday/SAP** : le coût de remplacement est prohibitif ; on ne les attaque pas de front.

---

## 2. Le marché en chiffres

### 2.1 Le marché mondial (contexte, pas cible)

| Indicateur | Valeur 2026 | Source |
|---|---|---|
| Marché HCM mondial | **44,3 à 46,9 Md$** | Vyansa Intelligence ; Mordor Intelligence |
| Croissance | 8,1 à 8,7 %/an jusqu'en 2031-2032 | idem |
| Part des grandes entreprises | **57,5 à 58 %** du marché | Precedence Research ; Grand View Research |
| Part de la paie dans le marché HCM | **43,8 à 47,3 %** | Mordor ; Market Research Future |
| Paie multi-pays (plateformes) | **12,06 Md$** en 2026 → 20,55 Md$ en 2031 | Mordor Intelligence |
| Concentration paie multi-pays | Top 5 (ADP, Ceridian, Deel, G-P, CloudPay) = **40-45 %** | Mordor Intelligence |

**Lecture :** la paie est la plus grosse sous-catégorie du marché, et c'est précisément là que Leopardo a un moteur qui tourne. Ce n'est pas un hasard si le pack de lancement est la paie.

### 2.2 Le marché adressable (SAM) — recalculé pour l'étage B

Méthode, posée explicitement pour être contestable :

| Étape | Hypothèse | Valeur |
|---|---|---|
| Groupes privés 500-5 000 salariés, DZ + MA + TN | ~2 500 groupes (estimation : densité de l'emploi formel privé et structure du tissu productif) | 2 500 |
| Taux équipables à 3 ans (maturité numérique, budget) | 30 % | 750 |
| ARPU moyen (500-2 000 salariés, 2,5-3 €/salarié/mois) | ~30 000 €/an | 30 k€ |
| **SAM** | 750 × 30 k€ | **≈ 22 M€/an** |

**Le chiffre est modeste, et c'est le point.** Le SAM grand compte est **plus petit** que le SAM PME (≈ 0,6 Md€) — mais l'économie par client est radicalement différente :

| | PME de terrain | Groupe multi-entités |
|---|---|---|
| Ticket annuel | 1 500 - 6 000 € | **30 000 - 80 000 €** |
| Cycle de vente | 2-6 semaines | **6-12 mois** |
| Décideurs | 1 (le dirigeant) | **5-8** |
| Coût d'acquisition | Faible | **Élevé** (15-30 k€) |
| Valeur de référence | Faible | **Très forte** (un groupe en ouvre dix) |
| Rétention attendue | 70-80 % | **90-95 %** (coût de sortie élevé) |

**SOM à 3 ans :** 5 à 8 groupes signés → **400 000 à 700 000 € ARR**. C'est atteignable avec une équipe de 2 commerciaux et 2 références publiables. Ce n'est pas un marché de volume, c'est un marché de **comptes**.

### 2.3 Le vrai calcul : ce que coûte un client grand compte

Un groupe de 2 000 salariés à 3 €/salarié/mois = **72 000 €/an**. Sur 5 ans, avec 95 % de rétention : **≈ 340 000 € de valeur vie**. Un CAC de 25 000 € est donc rentable en 4 mois de revenu — mais il faut **avancer** ces 25 000 € avant d'encaisser, et c'est là que la trésorerie se joue, pas dans le produit.

---

## 3. Les besoins réels du segment, par intensité

Lecture par besoin, pas par module. Sept besoins, classés par douleur.

| # | Besoin réel | Intensité | Ce que Leopardo a déjà | Écart |
|---|---|---|---|---|
| 1 | **Une paie juste, par entité et consolidée** — chaque société a sa convention, le groupe veut un total | 🔴 Critique | Moteur multi-pays (21 pays résolus), runs DZ/MA vérifiés | **Multi-entités non modélisé** (§8.3) |
| 2 | **Prouver les heures à l'échelle** — 1 000 salariés horaires, contestations, contentieux | 🔴 Critique | Pointage mobile/GPS/kiosque, EdgeSync hors-ligne | Charge non testée au-delà de quelques centaines |
| 3 | **Contrôler et auditer** — qui a changé quoi, quand, avec quelle valeur avant/après | 🔴 Critique | RBAC, SSO OIDC, journal IA | **Pas d'audit immuable général** (§8.4) |
| 4 | **S'intégrer au SI existant** — ERP, comptabilité, badgeuses, Active Directory | 🟠 Forte | API versionnée, 994 endpoints, connecteurs, exports comptables | **Pas de SCIM** (§8.2) |
| 5 | **Maîtriser l'hébergement** — données de paie, souveraineté, RGPD/local | 🟠 Forte | Auto-hébergement possible (MIT, Docker, Render) | Atout réel, à documenter |
| 6 | **Consolider le reporting groupe** — masse salariale, turnover, absentéisme par entité | 🟡 Moyenne | Rapports Platform (headcount, turnover, absentéisme, paie) | Pas de consolidation multi-entités |
| 7 | **IA gouvernée** — copilote utile, pas boîte noire | 🟡 Moyenne | Assistant Leo, journal d'audit IA | Secondaire à ce stade |

**Conclusion de lecture :** les besoins 1, 2 et 3 sont **critiques et simultanés** — c'est la différence avec la PME, où seul le 1+2 comptait. Un grand compte n'achète pas la paie sans le contrôle. C'est pourquoi le troisième pilier du message devient **Contrôle & conformité**, et non plus « RH & absences ».

---

## 4. Concurrence

### 4.1 Les cinq familles d'acteurs

| Famille | Acteurs | Force | Faiblesse sur notre segment |
|---|---|---|---|
| **Suites HCM mondiales** | Workday (27,9 %), SAP SuccessFactors (25,5 %), Oracle HCM (23,3 %) | Profondeur fonctionnelle, références, écosystème | **Pas de paie DZ/MA/TN officielle** ; 95-180 $/salarié/an ; 9-18 mois d'intégration ; >75 % du consideration set mais uniquement au-dessus de 2 000 salariés |
| **Paie multi-pays** | ADP, Ceridian/Dayforce, Deel, G-P, CloudPay (40-45 % du marché) | Couverture mondiale, conformité, service | Modèle EOR/service, coût par salarié élevé, **peu de présence terrain en Afrique du Nord**, pas de pointage industriel |
| **Spécialistes paie MENA/Afrique** | HROPAL (60+ pays, 500+ clients MENA/Afrique/APAC), Ajiel (Maroc), éditeurs locaux | Conformité locale, prix, proximité | Faible volet pointage terrain, UX datée, pas de suite métier |
| **ERP locaux** | IntelliX (Silwane), S2I, Wanasis, Artec (Algérie) | Présence locale, références, intégration comptable | Paie + compta, **pas de pointage biométrique/GPS sérieux**, pas de multi-pays, pas d'API moderne |
| **Pointage / T&A seuls** | ZKTeco et intégrateurs, éditeurs de badgeuse | Matériel, prix, simplicité | **Ne font pas la paie** — c'est exactement le trou que Leopardo comble |

### 4.2 Matrice de score sur le segment (0-5)

| Critère | Workday/SAP/Oracle | ADP/Deel | HROPAL/Ajiel | ERP locaux | ZKTeco & Cie | **Leopardo** |
|---|---|---|---|---|---|---|
| Paie DZ/MA/TN | 0 | 2 | 4 | 4 | 0 | **4** |
| Pointage terrain (GPS, kiosque, hors-ligne) | 2 | 1 | 2 | 1 | 4 | **4** |
| Multi-pays dans un seul moteur | 5 | 5 | 4 | 1 | 0 | **4** |
| Multi-entités / consolidation groupe | 5 | 4 | 3 | 2 | 0 | **1** |
| Sécurité & conformité enterprise (SAML, SCIM, SOC 2, audit) | 5 | 5 | 3 | 2 | 1 | **2** |
| Coût total pour un groupe de 1 000 salariés | 1 | 2 | 4 | 4 | 5 | **5** |
| Modernité de l'API / extensibilité | 4 | 4 | 2 | 2 | 1 | **5** |
| **Total /35** | **22** | **23** | **22** | **16** | **11** | **25** |

**Lecture honnête :** Leopardo gagne sur le total, mais **perd sur les deux critères qui bloquent une signature** — multi-entités (1/5) et conformité enterprise (2/5). Un acheteur ne fait pas la moyenne : il élimine sur les critères éliminatoires. **Ces deux lignes sont le programme des 6 prochains mois**, pas un détail.

### 4.3 Le coin d'attaque

> **Un groupe de 1 000 salariés répartis sur 6 entités et 3 pays paie aujourd'hui : un ERP local pour la paie DZ, un tableur pour la consolidation, un prestataire pour la paie MA, et des badgeuses ZKTeco dont personne n'exporte les données proprement. Coût : 3 systèmes, 2 ressaisies, 1 audit annuel qui coûte une semaine.**

Aucun acteur ne couvre cette phrase en entier. Les suites mondiales couvrent la consolidation mais pas la paie locale ; les ERP locaux couvrent la paie locale mais pas la consolidation ni le pointage ; les badgeuses ne font que le pointage.

---

## 5. Tendances 2026

| # | Tendance | Ce que ça change pour Leopardo |
|---|---|---|
| 1 | **Consolidation des fournisseurs** — l'acheteur moyen utilise 5 prestataires et veut descendre à 4 ; 29 % des organisations opèrent dans plus de 30 pays | Argument central : « un moteur au lieu de trois ». À porter sur le multi-entités. |
| 2 | **La conformité devient temps réel** — e-invoicing France (sept. 2026), SAF-T, eSocial, contrôles instantanés | La paie « qui se met à jour » devient un critère d'achat, pas un confort. Fenêtre pour un éditeur agile. |
| 3 | **IA agentique dans la paie** — copilotes, détection d'anomalies, résolution de tickets | Leopardo a un journal d'audit IA : c'est un différenciateur **si** l'IA reste gouvernée et traçable. |
| 4 | **Souveraineté et résidence des données** — Schrems II, localisation, centres régionaux | **Atout majeur** : Leopardo s'auto-héberge (MIT, Docker). Peu de concurrents peuvent le dire. À documenter sérieusement. |
| 5 | **Pénurie de main-d'œuvre horaire** — rétention, planning, preuve des heures | Le pointage n'est plus de la surveillance, c'est de la **paie défendable**. Recadrer le discours. |
| 6 | **Le prix par salarié se comprime** — les suites mondiales perdent du terrain sur le mid-market | Un groupe de 1 000 salariés à 95-180 $/salarié = 95-180 k$/an. À 3 €/salarié = 36 k€/an. L'écart est l'argument. |

---

## 6. SWOT

### Forces
- **Moteur de paie réellement multi-pays** — 21 pays résolus en exécution, runs DZ et MA vérifiés (9 bulletins, 842 059,10 DZD brut ; 6 bulletins, 53 614,27 MAD).
- **Pointage terrain complet** — mobile, GPS, kiosque biométrique, mode hors-ligne avec resynchronisation.
- **Boucle fermée** — pointage → heures → paie → bulletin PDF → run verrouillé. Démontrable en 20 minutes.
- **Auto-hébergement** — licence MIT, Docker, Render. Répond à la souveraineté sans négociation.
- **Étendue fonctionnelle** — 42 modules, 994 endpoints : un groupe peut tout mettre chez un seul éditeur.
- **Coût total 3 à 5 fois inférieur** aux suites mondiales.

### Faiblesses
- **Aucune traction** — 14 stars, 2 abonnés, 0 revenu publié, 0 référence grand compte.
- **SAML non implémenté** — refus explicite dans le code. **Éliminatoire en grand compte.**
- **Pas de SCIM** — provisioning manuel de centaines de comptes.
- **Multi-entités non modélisé** — pas d'entité juridique, pas de consolidation groupe.
- **Pas d'audit immuable général** — seulement le journal IA.
- **Pas de SOC 2 ni ISO 27001** — le premier filtre de tout dossier de sécurité.
- **Charge non prouvée** — jamais testé à 1 000+ salariés simultanés.
- **Référentiels de paie en statut `pilot`** — non validés par un conseil local.

### Opportunités
- **Le trou de la paie locale** chez les suites mondiales, sur un marché où la paie pèse 44-47 % du HCM.
- **Le mid-market grandit** : les groupes de 500-5 000 salariés sont trop gros pour un ERP local, trop petits pour Workday.
- **Souveraineté** : l'auto-hébergement devient un argument de vente, pas une contrainte.
- **Consolidation fournisseurs** : le discours « un moteur au lieu de trois » tombe juste.
- **Francophone Africa** : mêmes besoins, moins de concurrence, effet de référence entre pays.

### Menaces
- **Un éditeur local ajoute le pointage** — c'est la menace la plus rapide (6-12 mois).
- **ADP ou Deel descend sur l'Afrique du Nord** avec un partenaire local.
- **Un pilote raté** sur un groupe visible ferme le marché algérien pour deux ans.
- **Le cycle de vente épuise la trésorerie** avant la première signature.
- **L'étendue du produit** continue de diluer le message si le périmètre n'est pas tenu.

---

## 7. Écarts enterprise — l'audit qui décide de tout

C'est la section la plus importante du document. Elle est issue d'une **lecture du code**, pas d'une intention.

### 7.1 SSO SAML — 🔴 bloquant

`api/app/Core/Auth/Infrastructure/Services/SSO/SSOService.php` déclare :

```php
'oidc' => true,
'saml' => false,
```

et `handleSAMLResponse()` refuse explicitement la connexion : « La validation des assertions SAML n'est pas encore implémentée — connexion refusée. »

**Conséquence :** OIDC fonctionne (Okta, Entra ID en mode OIDC, Google). **SAML 2.0 ne fonctionne pas.** Or SAML est le standard que demandent la majorité des DSI, et il est explicitement listé comme « deal killer » dans les grilles d'achat enterprise.

**Effort :** intégration `onelogin/php-saml`, 2 à 3 semaines, plus les tests avec Entra ID et ADFS.

### 7.2 SCIM — 🟠 fort

Aucune occurrence de SCIM dans le code applicatif. Un groupe de 2 000 salariés ne crée pas 2 000 comptes à la main, et surtout ne les **supprime** pas à la main au départ d'un salarié.

**Effort :** endpoint SCIM 2.0 (Users, Groups), 3 à 4 semaines.

### 7.3 Multi-entités — 🔴 bloquant

Le modèle est `companies.features` : un tenant = une entreprise. Il n'existe **pas d'entité juridique** rattachée à un groupe, ni de consolidation. Un groupe de 6 sociétés devrait aujourd'hui créer 6 tenants séparés, sans vue consolidée — ce qui annule l'argument principal.

**Effort :** modèle `group` → `legal_entity` → `establishment`, consolidation de paie et de reporting, 6 à 10 semaines. **C'est le plus gros chantier et le plus différenciant.**

### 7.4 Audit immuable — 🔴 bloquant

Il existe `AIAuditLog` (journal des actions IA) mais pas de piste d'audit générale, immuable, avec valeur avant/après, conservée 1 à 7 ans. C'est une exigence de contrôle interne et de toute revue de sécurité.

**Effort :** table append-only, journalisation des écritures sensibles (paie, contrats, rôles), export, 3 à 4 semaines.

### 7.5 SOC 2 / ISO 27001 — 🟠 fort

Aucune certification. SOC 2 Type II prend 6 à 12 mois et coûte 25-60 k€ (audit + outils + remédiation). **Ce n'est pas un prérequis pour l'étage B**, mais ça le devient pour l'étage A et pour les groupes cotés.

### 7.6 Charge et résidence — 🟡 moyen

Aucun test de charge publié au-delà de quelques centaines d'utilisateurs. L'auto-hébergement répond à la résidence, mais il faut le documenter (architecture, chiffrement, sauvegardes, PRA).

### 7.7 Synthèse — le chemin de mise à niveau

| Chantier | Bloquant ? | Effort | Quand |
|---|---|---|---|
| SSO SAML | 🔴 Oui | 2-3 sem. | **Mois 1** |
| Multi-entités + consolidation | 🔴 Oui | 6-10 sem. | **Mois 1-3** |
| Audit immuable | 🔴 Oui | 3-4 sem. | **Mois 2** |
| SCIM | 🟠 Non | 3-4 sem. | Mois 3-4 |
| Test de charge 1 000+ | 🟠 Non | 2 sem. | Mois 3 |
| Validation paie par conseil local | 🔴 Oui | 4-6 sem. | Mois 1-2 |
| SOC 2 Type II | 🟡 Non (étage B) | 6-12 mois | Mois 6+ |

**Total pour être vendable à l'étage B : 4 à 5 mois de développement.** C'est le prix d'entrée du « gros poisson ». Il n'y a pas de raccourci.

---

## 8. Implication sur le périmètre

### 8.1 Les 3 modules en avant (et seulement 3)

Le trio change par rapport à la cible PME, parce que le troisième motif d'achat change.

1. **Paie multi-pays & multi-entités** — un moteur, 21 pays, une paie par entité et un total groupe. C'est le cœur et le fossé.
2. **Pointage & preuve des heures à l'échelle** — mobile, GPS, kiosque biométrique, hors-ligne. C'est là que l'argent fuit dans un groupe à main-d'œuvre horaire.
3. **Contrôle & conformité** — audit, exports comptables, RBAC, SSO, hébergement maîtrisé. **C'est ce que la DSI, le RSSI et le DAF achètent.** Remplace « RH & absences » du trio PME.

### 8.2 Ce qui passe en catalogue

**39 modules** restent dans le produit, activables, hors du message d'entrée : RH & absences (inclus mais non vendu), CRM, comptabilité, notes de frais, recrutement, formation, communication, et les 8 verticales (restauration, hôtellerie, pharmacie, santé, école, station-service, VTC, livraison), plus agriculture et négoce.

**Règle :** le catalogue se présente **après** la démonstration, jamais avant. Un grand compte qui voit 42 modules en page d'accueil conclut « éditeur qui ne sait pas ce qu'il vend ».

### 8.3 Ce que ça change dans le produit

| Avant (cible PME) | Après (cible grand compte) |
|---|---|
| 3 modules en avant : Paie, Pointage, RH/absences | 3 modules en avant : Paie multi-entités, Pointage, **Contrôle** |
| 1 pays en avant (DZ) | **3 pays** en avant (DZ, MA, TN) — le multi-pays devient l'argument |
| 38 modules en catalogue | 39 modules en catalogue |
| Canal cabinet comptable | **Vente directe** + intégrateurs SI |
| Cycle 2-6 semaines | Cycle 6-12 mois, 5-8 interlocuteurs |
| Prix 29-79 €/mois | **Contrat annuel 30-80 k€** |

---

## 9. Par où démarrer — la séquence

### 9.1 Les 90 premiers jours

| Phase | Durée | Objectif | Livrable |
|---|---|---|---|
| **P0 — Mise à niveau bloquante** | Semaines 1-6 | SAML + multi-entités + audit | Build démontrable à un DSI |
| **P1 — Dossier de sécurité** | Semaines 3-8 | Répondre à un questionnaire de sécurité de 60 questions | Dossier + architecture + PRA |
| **P2 — Un pilote payant** | Semaines 6-16 | **Une entité** d'un groupe, paie réelle + un site pointé | 1 référence chiffrée |
| **P3 — Deuxième groupe** | Semaines 12-24 | Répliquer sur un secteur différent | 2 références |

**Condition de passage P1 → P2 :** SAML fonctionnel avec Entra ID **et** ADFS, testé par le DSI du client, pas par nous.

### 9.2 Le premier groupe à approcher

**Critères de sélection, dans l'ordre :**

1. 800 à 2 000 salariés — assez gros pour payer, assez petit pour décider vite.
2. 4 à 10 entités juridiques — la complexité qui justifie le changement.
3. Main-d'œuvre horaire majoritaire — la douleur du pointage.
4. DSI existante mais petite (2-5 personnes) — elle comprend la valeur, elle n'impose pas Workday.
5. Pas de ERP groupe verrouillé sur SAP/Oracle — sinon la bataille est perdue d'avance.
6. Dirigeant actionnaire ou famille propriétaire — décision rapide, pas de comité.

**Comment entrer :** par le **DAF**, pas par la DRH. Dans un groupe, la DRH gère les personnes ; le DAF paie et subit les erreurs. C'est lui qui chiffre le coût d'une paie fausse.

### 9.3 Le pilote, précisément

- **Périmètre :** une entité, un site, 150 à 400 salariés.
- **Durée :** 12 semaines.
- **Prix :** payant, 8 000 à 15 000 € — un pilote gratuit ne se termine jamais et ne se signe jamais.
- **Critère de succès, écrit d'avance :** 3 paies consécutives sans erreur imputable à l'outil, et 100 % des heures du site prouvées.
- **Sortie :** si succès, contrat groupe ; si échec, on documente et on corrige avant le suivant.

---

## 10. Critères d'arrêt

À évaluer à **J+180** :

| Signal | Seuil | Décision |
|---|---|---|
| SAML + multi-entités livrés | Non | **Bloquant** : on ne démarre pas la prospection |
| Groupes en discussion avancée | < 3 | Le message ne passe pas → revoir l'accroche, pas le produit |
| Pilote payant signé | 0 | Le segment ne convertit pas → redescendre à l'étage C |
| Cycle réel | > 14 mois | Le modèle ne tient pas la trésorerie → chercher un canal intégrateur |
| Coût d'acquisition | > 40 k€ | Le ticket ne couvre pas l'acquisition → monter l'ARPU ou changer de canal |

**Règle :** si deux critères sont franchis négativement, on **redescend d'un étage** (B → C) plutôt que d'élargir. Un groupe de 300 salariés signé vaut mieux que six groupes en discussion.

---

## 11. Ce que cette étude change

| Avant | Après |
|---|---|
| PME de terrain 5-150 salariés | **Groupe multi-entités 500-5 000 salariés** |
| Cabinet comptable = canal principal | **Vente directe + intégrateurs SI** |
| 1 pays (DZ) | **3 pays (DZ, MA, TN)** |
| Paie + Pointage + RH/absences | Paie multi-entités + Pointage + **Contrôle** |
| Ticket 1,5-6 k€/an | **Ticket 30-80 k€/an** |
| Cycle 2-6 semaines | Cycle 6-12 mois |
| « Essayer 14 jours » | **« Pilote payant 12 semaines »** |
| 38 modules en catalogue | 39 modules en catalogue |

**Le produit ne change pas. Le client change. Et avec lui, ce qu'il faut construire avant de vendre.**

---

## 12. Sources

- Vyansa Intelligence — *Human Capital Management Software Market* (44,34 Md$ 2026, 8,12 % CAGR)
- Mordor Intelligence — *HCM Software Market* (46,92 Md$ 2026, 8,67 % CAGR ; paie 43,78 % du revenu 2025)
- Mordor Intelligence — *Multi-Country Payroll Platform Market* (12,06 Md$ 2026 → 20,55 Md$ 2031 ; top 5 = 40-45 %)
- Precedence Research — *HCM Market* (grandes entreprises 57,5 % en 2025)
- Grand View Research — *HR Software Market* (grandes entreprises 58 % en 2025)
- Market Research Future — *HCM Software Market* (grandes entreprises 30,01 Md$ 2025 ; paie 47,28 %)
- Futurum Group (via Outsail) — parts Workday 27,9 % / SAP SF 25,5 % / Oracle HCM 23,3 % ; >75 % du consideration set au-dessus de 2 000 salariés
- VendorBenchmark — TCO SAP SuccessFactors (95-180 $/salarié/an)
- EY Global Payroll Survey (via Slasify) — 29 % des organisations dans 30+ pays ; 5 fournisseurs → 4
- Keka / Iterators — exigences d'achat enterprise (SOC 2, ISO 27001, SAML, SCIM, audit immuable, résidence)
- Gartner Peer Insights — *Multicountry Payroll Solutions* 2026 (HROPAL, iiPay, Payslip)
- IBOS — *Top ERP Algérie* (IntelliX/Silwane, S2I, Wanasis, Artec)
- Code du dépôt : `SSOService.php`, `SSOProviderConfig.php`, `api/app/Modules/*`, `docs/REFERENTIEL_PRODUIT/APV.md`
