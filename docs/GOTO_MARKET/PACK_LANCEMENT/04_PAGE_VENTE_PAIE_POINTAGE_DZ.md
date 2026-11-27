# Page de vente — offre grands comptes

> **Statut** : livrable de la phase **P0 — Gel du message** · **Version** : v2 (grand compte) · **Date** : 2026-11-28 · **Issue** : #10025
> **Remplace** : la v1 « Paie + Pointage DZ » (cible PME de terrain). Le chemin du fichier est conservé pour ne pas casser les références ni le budget documentaire.
> **Décision de référence** : `../REPOSITIONNEMENT_2026.md` §4 — un seul message, un seul segment, un pilote payant.
> **Étude de référence** : `../ETUDE_MARCHE_GRAND_COMPTE_2026.md`.
> **Cadre bloquant** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (pitch canonique, promesses interdites), `METRIQUES_VITRINE.md` (chiffres datés uniquement).
> **Rappel de catégorie** : Leopardo est une **suite métier**. Jamais « logiciel RH », « HR SaaS » ni « Business OS ».

---

## 0. Comment utiliser ce document

Ce document est la **copie de référence** de la page de vente de l'offre grands comptes. Il se lit de haut en bas : chaque bloc correspond à une section de la page publique, dans l'ordre où le visiteur la voit.

Quatre règles d'écriture, non négociables :

1. **Un seul message.** Si une phrase ne parle pas de paie multi-entités, de preuve des heures ou de contrôle, elle n'a rien à faire dans les six premiers écrans.
2. **Un seul segment.** Le groupe multi-entités de 500 à 5 000 salariés. Pas de « convient aussi aux PME » : ça dilue et ça fait fuir les deux.
3. **Aucune promesse non prouvée.** Les référentiels de paie sont en statut **`pilot`**. On dit « le moteur calcule la paie algérienne », jamais « conformité validée ». On ne dit **jamais** « SSO SAML » : seul OIDC est implémenté (§8).
4. **Aucun prix affiché.** Un grand compte ne compare pas des prix par salarié sur une page publique ; il demande une proposition. Le prix se discute en rendez-vous, avec le périmètre.

---

## 1. Hero — l'accroche

**Titre (H1)**

> # Une seule paie pour toutes vos entités, et la preuve des heures de chaque site.

**Sous-titre**

> Vos filiales ont chacune leur convention, leur paie, leur tableur. Vos sites ont chacun leur badgeuse, et personne n'exporte proprement. Leopardo réunit la paie multi-pays et le pointage terrain dans un seul moteur : une paie par entité, un total groupe, et des heures que l'on peut prouver.

**Boutons**

- **Primaire** : `Demander une démonstration sur vos données`
- **Secondaire** : `Télécharger le dossier technique` (architecture, sécurité, hébergement)

**Bandeau de réassurance (4 items, une ligne chacun)**

| Item | Texte |
|---|---|
| 🌍 | Paie multi-pays : 21 moteurs pays, dont DZ, MA, TN |
| 🏢 | Multi-entités : une paie par société, une consolidation groupe |
| 📱 | Pointage mobile, GPS et borne biométrique, y compris hors réseau |
| 🔐 | Hébergement maîtrisé : déploiement chez vous ou chez votre hébergeur |

**Ce que le hero ne dit pas** — et c'est volontaire : ni « suite complète », ni « 42 modules », ni « 21 pays disponibles partout », ni « certifié ». Ces éléments existent (§6), mais ils ne sont pas l'accroche.

---

## 2. Le problème — trois phrases, pas un paragraphe

> **Votre paie est éclatée en autant de systèmes que vous avez de sociétés.**
> Une filiale sur l'ERP du groupe, une autre sur un logiciel local, une troisième sur un tableur tenu par une seule personne. Le total consolidé, personne ne l'a avant la fin du mois — et personne ne le croit.

> **Vos heures ne sont pas prouvées, elles sont déclarées.**
> Une badgeuse par site, dont les données sortent en fichier texte. Quand un salarié conteste ses heures supplémentaires, la réponse est « on va vérifier » — et on ne vérifie pas.

> **Et le jour de l'audit, tout le monde cherche la même chose.**
> Qui a changé cette ligne de paie, quand, et avec quelle valeur avant ? Si la réponse n'est pas dans un journal, elle n'existe pas.

---

## 3. Les 3 modules en avant

Trois, pas quatre. Un grand compte élimine un fournisseur qui ne sait pas hiérarchiser son offre.

### 3.1 Paie multi-pays & multi-entités — le moteur

**Ce qu'il fait.** Calcule la paie de chaque entité juridique selon sa convention et son pays, puis consolide le total groupe. Un moteur, 21 pays résolus, dont l'Algérie (IRG, CNAS), le Maroc et la Tunisie.

**Ce qu'il remplace.** Un ERP local par pays, un tableur de consolidation, et la ressaisie mensuelle entre les deux.

**Ce qu'il produit.** Bulletins PDF, journal de paie, exports comptables, run verrouillé après validation.

**Sa limite, dite franchement.** Les référentiels de paie sont en statut **`pilot`** : le moteur calcule, mais la conformité légale doit être validée par un conseil fiscal local avant une mise en production. Nous le disons avant la signature, pas après.

### 3.2 Pointage & preuve des heures — le terrain

**Ce qu'il fait.** Enregistre les heures depuis le mobile, le GPS ou une borne biométrique ZKTeco. Fonctionne en coupure réseau et resynchronise au retour du lien.

**Ce qu'il remplace.** Les badgeuses dont personne n'exporte les données, et les feuilles de présence papier des chantiers.

**Ce qu'il produit.** Une heure rattachée à une personne, un site et un horodatage — donc une réponse opposable à une contestation.

**Sa limite, dite franchement.** La tenue en charge au-delà de quelques centaines d'utilisateurs simultanés n'a pas encore été testée publiquement. C'est un point du plan de pilote, pas un acquis.

### 3.3 Contrôle & conformité — ce que la DSI achète

**Ce qu'il fait.** Rôles et permissions par entité, cloisonnement des données, exports d'audit, hébergement chez vous ou chez votre hébergeur, authentification unique OIDC.

**Ce qu'il remplace.** Les fichiers Excel de « qui a le droit de voir les salaires », et les réponses improvisées aux questionnaires de sécurité.

**Ce qu'il produit.** Un dossier de sécurité opposable, et une piste d'audit sur les actions sensibles.

**Sa limite, dite franchement.** Trois écarts sont en cours de comblement et sont annoncés comme tels : **SAML 2.0** (seul OIDC est opérationnel aujourd'hui), **SCIM** (provisionnement automatisé), et la **piste d'audit immuable généralisée**. Aucune certification SOC 2 ni ISO 27001 n'est détenue à ce jour.

---

## 4. La boucle fermée — le schéma qui vend

```
   Pointage (mobile / GPS / borne)
              │
              ▼
   Heures validées par le manager de site
              │
              ▼
   Paie de l'entité (convention + pays)
              │
              ▼
   Bulletin PDF  ──►  Run verrouillé
              │
              ▼
   Consolidation groupe + exports comptables
```

**Ce que ça veut dire en une phrase :** l'heure pointée sur un chantier devient la ligne du bulletin, sans ressaisie, et le total remonte au groupe sans tableur.

**Ce que ça vaut en démonstration :** 20 minutes, sur les données du client, avec un cas réel — un salarié, un mois, une contestation.

---

## 5. Le cycle d'achat — ce que le visiteur doit comprendre

Un grand compte ne clique pas « essayer ». Il doit voir que nous savons comment il achète.

| Étape | Durée | Ce que nous fournissons |
|---|---|---|
| **1. Cadrage** | 1 réunion | Compréhension de vos entités, conventions et systèmes |
| **2. Démonstration** | 1 session | La boucle fermée sur un jeu de données anonymisé de votre groupe |
| **3. Revue technique** | 3-6 semaines | Architecture, sécurité, hébergement, intégration à votre ERP |
| **4. Pilote payant** | 12 semaines | Une entité, un site, 150 à 400 salariés, critères de succès écrits d'avance |
| **5. Contrat groupe** | 4-8 semaines | Périmètre, SLA, déploiement des autres entités |

**Pourquoi un pilote payant et pas un POC gratuit :** un POC gratuit ne se termine jamais, ne se signe jamais, et ne prouve rien. Un pilote payant a des critères de succès écrits, une date de fin, et une décision à la sortie.

**Critères de succès du pilote, annoncés d'avance :**
1. Trois paies consécutives sans erreur imputable à l'outil.
2. 100 % des heures du site pilote prouvées et rattachées.
3. Le DSI du client valide l'authentification et l'audit sur son propre environnement.

---

## 6. Le catalogue — 39 modules activables

Ce qui suit n'est **pas** l'argumentaire. C'est ce que le client découvre après la démonstration, quand il demande « et sinon, vous faites quoi d'autre ? ».

### 6.1 Les 3 modules du pack (rappel, non répétés au catalogue)

Paie multi-pays & multi-entités · Pointage & preuve des heures · Contrôle & conformité.

### 6.2 Les 39 modules activables, par famille

**Socle RH** — Dossier employé, Congés & absences, Contrats, Recrutement, Formation, Onboarding, Communication interne, Notifications, Planning & tâches.

**Finance** — Comptabilité, Notes de frais, Abonnements & facturation, Encaissements, Exports & rapports.

**Relation client** — CRM, Catalogue produits & services, Vitrine & acquisition, Pages & contenu, Recherche & intelligence.

**Terrain & opérations** — Flotte, Géolocalisation, Caméras & supervision, EdgeSync & hors-ligne, Kiosque.

**Verticales** — Restauration & POS, Hôtellerie, Commerce de détail, Pharmacie, Santé, École & formation, Station-service, VTC, Livraison, Agence de voyage, Agriculture, Négoce & import-export.

**Plateforme** — Authentification & IAM, Multi-tenant & sécurité, Multilingue & RTL, Connecteurs & intégrations, API & intégrations, Applications mobiles, Poste de travail, Assistant IA, Audit & conformité, Marque & surfaces publiques, Bibliothèque, Cagnottes & collecte, Cabinet comptable, Programme partenaires.

**Deux modules hors catalogue de lancement** — `Agriculture` (Farm OS) et `Négoce & import-export` (Trade OS) : ils exigent des environnements de démonstration qui n'existent pas encore. Ils restent dans le produit, hors du discours public.

---

## 7. La démonstration de 20 minutes — script de vente

| Min | Ce qu'on montre | Ce qu'on dit |
|---|---|---|
| 0-2 | Le problème, avec leurs mots | « Combien de systèmes pour sortir votre masse salariale consolidée ? » |
| 2-5 | Le pointage d'un site | « Voilà une heure, rattachée à une personne, un site, un horodatage. » |
| 5-9 | La paie de l'entité | « IRG, CNAS, variables de pointage remontées. Le run se calcule. » |
| 9-12 | Validation et verrouillage | « Une fois validé, le run est verrouillé. Personne ne le modifie en silence. » |
| 12-15 | Le bulletin PDF | « Le bulletin sort. Pas de ressaisie. » |
| 15-18 | La consolidation | « Le total groupe, sans tableur. » |
| 18-20 | Le catalogue, en un écran | « Et si vous avez besoin d'autre chose, c'est là. » |

**Règle :** on ne montre jamais le catalogue avant la minute 18.

---

## 8. Ce que nous ne disons pas — et pourquoi

Cette section existe pour être lue par quiconque écrit la copie publique. Elle est aussi contraignante que le reste.

| Interdit | Pourquoi | Ce qu'on dit à la place |
|---|---|---|
| « SSO SAML » | `SSOService.php` déclare `'saml' => false` ; la connexion SAML est refusée dans le code | « Authentification unique OIDC (Entra ID, Okta, Google) ; SAML en cours » |
| « SCIM » | Aucune implémentation | « Provisionnement par API ; SCIM en cours » |
| « Certifié SOC 2 / ISO 27001 » | Aucune certification détenue | « Dossier de sécurité disponible ; certification en préparation » |
| « Conformité légale validée » | Référentiels en statut `pilot` | « Le moteur calcule la paie algérienne ; validation par conseil local requise » |
| « Testé à 10 000 utilisateurs » | Aucun test de charge publié | « Test de charge au programme du pilote » |
| « Multi-entités » sans réserve | Le modèle est un tenant = une entreprise ; la consolidation groupe est en construction | « Multi-entités : disponible à l'issue du chantier en cours (mois 1-3) » |

**Le dire avant la signature n'est pas une faiblesse. C'est ce qui fait revenir le client après le pilote.**

---

## 9. Objections — les six qui reviennent

**« Vous êtes trop petits pour nous. »**
Nous ne sommes pas un éditeur installé, c'est vrai. C'est pourquoi nous ne demandons pas un déploiement groupe : nous demandons une entité, 12 semaines, avec des critères de succès écrits. Si le pilote échoue, vous avez perdu 12 semaines et nous avons perdu un client.

**« Pourquoi pas Workday ou SAP ? »**
Parce qu'ils ne font pas la paie algérienne, marocaine ou tunisienne de façon native, et qu'ils coûtent 95 à 180 $ par salarié et par an avec 9 à 18 mois d'intégration. Si votre groupe est déjà standardisé sur SAP, nous ne sommes pas le bon choix — et nous le dirons.

**« Et la sécurité ? »**
Voici le dossier : architecture, chiffrement, cloisonnement par entité, rôles, hébergement. Trois écarts sont ouverts et documentés : SAML, SCIM, audit immuable généralisé. Nous ne prétendons pas avoir de certification.

**« On a déjà un ERP. »**
Nous ne remplaçons pas votre ERP. Nous remplaçons la paie et le pointage, et nous exportons vers votre comptabilité. L'intégration est un point du pilote, pas une promesse.

**« Combien ça coûte ? »**
Cela dépend du nombre de salariés, d'entités et de pays. Un groupe de 1 000 salariés sur 6 entités se situe dans une fourchette de 30 000 à 80 000 € par an. Le pilote, lui, est à 8 000-15 000 € et se déduit du contrat si vous signez.

**« Et si ça ne marche pas ? »**
Le pilote a une date de fin et trois critères écrits. S'ils ne sont pas atteints, vous ne signez pas, et nous documentons publiquement pourquoi.

---

## 10. CTA final

> **Douze semaines, une entité, trois critères.**
> Demandez une démonstration sur vos données — pas sur les nôtres.

- **Primaire** : `Demander une démonstration`
- **Secondaire** : `Télécharger le dossier technique`

---

## 11. Ce que cette page change, et ce qu'elle ne change pas

| Avant (v1, PME) | Après (v2, grand compte) |
|---|---|
| « La paie et le pointage de vos équipes de terrain » | « Une seule paie pour toutes vos entités, et la preuve des heures de chaque site » |
| 3 modules : Paie, Pointage, RH/absences | 3 modules : Paie multi-entités, Pointage, **Contrôle** |
| Canal cabinet comptable en §5 | **Cycle d'achat en §5** — le cabinet n'est plus le cœur |
| « Essayer 14 jours, sans carte » | **« Pilote payant 12 semaines »** |
| Prix affichés (29-79 €/mois) | **Aucun prix public** — fourchette en rendez-vous |
| 1 pays (DZ) | 3 pays en avant (DZ, MA, TN) |

**Ce qui ne change pas :** le produit, la boucle fermée, et l'exigence de ne rien promettre qui ne soit prouvé.

---

## 12. Checklist de publication

- [ ] Le H1 ne contient ni « PME », ni « terrain » seul, ni « suite complète ».
- [ ] Les trois modules en avant sont exactement Paie multi-entités, Pointage, Contrôle.
- [ ] Aucune occurrence de « SAML », « SCIM », « certifié », « conformité validée » hors de la section §8.
- [ ] Le catalogue n'apparaît pas avant la section 6.
- [ ] Aucun prix public.
- [ ] Le pilote payant est présenté avec ses trois critères de succès.
- [ ] `bash dev-hub/tools/check-public-promises.sh` passe.
- [ ] Les chiffres cités (9 bulletins, 842 059,10 DZD, 879 730 octets) sont datés et traçables.
