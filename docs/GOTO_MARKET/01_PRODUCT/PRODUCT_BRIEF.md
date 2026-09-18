# Leopardo — Product Brief de référencement

> **Référence** : catégorie, pitch et promesses dans `docs/REFERENTIEL_PRODUIT/MESSAGE.md`,
> chiffres datés dans `METRIQUES_VITRINE.md`. Catégorie : **suite métier / business suite** (#7428).

## Identité

**Nom produit :** Leopardo (identifiants techniques historiques : dépôt `leopardo-hr`, domaine `leopardo-rh.com` — figés, non présentables)  
**Catégorie :** suite métier (business suite) — contenus : RH & paie, présences, préparation de paie, workforce operations, CRM, comptabilité  
**Statut :** logiciel open source, auto-hébergeable ou exploitable en SaaS  
**Licence :** MIT  
**Dépôt :** https://github.com/kitokoh/leopardo-hr  
**Accès public actuellement documenté :** https://gestionemployerbackend.onrender.com  
**Documentation :** https://github.com/kitokoh/leopardo-hr/tree/main/docs  

> **Promesse courte :** Leopardo aide les PME et entreprises de terrain à suivre les présences et préparer la paie sans Excel, sans processus dispersés et avec une meilleure visibilité opérationnelle.

## Description courte en français

Leopardo est la suite métier open source, mobile-first et multi-tenant des entreprises de terrain — RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes. Elle réunit dossiers employés, présences, absences, horaires, tâches, documents, préparation de paie, alertes et analyses.

## Short description in English

Leopardo is the open-source, mobile-first, multi-tenant business suite for field-based companies — HR & payroll, attendance, leave, CRM, accounting and operations, on web, mobile and kiosks. It combines employee records, attendance, leave, schedules, documents, payroll preparation, alerts and workforce analytics.

## Problème résolu

Les PME terrain gèrent souvent les présences, les absences et les éléments de paie avec des fichiers Excel, des messages et des processus difficiles à contrôler. Leopardo centralise ces opérations afin de réduire les ressaisies, améliorer la visibilité des managers et structurer la préparation de la paie.

## Public prioritaire

Le produit s’adresse en priorité aux PME et entreprises multi-sites du Maghreb et des marchés émergents, aux équipes RH et managers qui pilotent des collaborateurs terrain, aux cabinets comptables partenaires et aux équipes techniques recherchant une suite métier open source et extensible.

## Fonctionnalités principales

| Domaine | Fonctionnalités à mettre en avant |
|---|---|
| RH centralisées | Dossiers employés, contrats, documents, onboarding et organigramme |
| Temps et présence | Pointage mobile, géolocalisation selon configuration, kiosque et intégration ZKTeco |
| Management | Horaires, sites, tâches, validations, notifications et suivi d’équipe |
| Absences et avantages | Demandes d’absence, avances, corrections et workflows d’approbation |
| Paie | Moteur de préparation de paie multi-pays, contrôles, exports et génération de documents |
| Analytique | Détection d’anomalies, indicateurs workforce et assistants d’analyse orientés RH |
| Plateforme | Multi-tenancy, RBAC, SSO SAML/OIDC, API OpenAPI, webhooks et applications Flutter |
| Déploiement | Auto-hébergement ou usage SaaS, avec PostgreSQL, Redis et stockage compatible S3 |

## Captures d'écran et visuels

Les fichiers d'images du dépôt sont stockés via Git LFS et ne sont pas directement accessibles dans cet export. Pour chaque plateforme, il est impératif de réaliser des captures d'écran fraîches (format 1270x760 pour Product Hunt, carré pour les miniatures) illustrant :
1. Le tableau de bord principal (vue d'ensemble).
2. Le module de pointage ou de présence (valeur métier).
3. L'interface mobile employé (usage terrain).

## Preuves techniques à employer avec prudence

Le dépôt documente, mesures du 2026-09-18 (cf. `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md`) : 27 modules métier DDD, 798 endpoints couverts par la spécification OpenAPI, 7 applications mobiles Flutter + un package cœur partagé (`leopardo_core`), 1045 fichiers de tests backend, 58 fichiers de tests Dart, 69 workflows CI, une architecture Laravel/PostgreSQL/Redis et une licence MIT. Ces éléments décrivent l’état du dépôt à la date de mesure indiquée et ne doivent pas être transformés en promesses commerciales non vérifiées.

## Régions et conformité

Le README mentionne un catalogue de paie couvrant notamment l’Algérie, le Maroc, la Tunisie, la France, la Turquie, le Cameroun, le Gabon, la République du Congo, la Côte d’Ivoire, le Sénégal, le Burkina Faso et le Mali. La formulation externe recommandée est « couverture et catalogue en cours de validation selon le pays » plutôt qu’une garantie de conformité juridique. Le projet documente une posture GDPR / loi algérienne 18-07 et une architecture orientée sécurité ; il faut éviter d’affirmer une certification ISO 27001.

## Positionnement selon la plateforme

| Plateforme | Angle recommandé | Action préparée | Niveau de priorité |
|---|---|---|---|
| AlternativeTo | Alternative open source aux suites métier fermées et outils séparés | Créer une fiche factuelle avec tags, plateformes et licence | Élevé |
| Product Hunt | Lancement d’un produit open source AI-native et mobile-first | Préparer un post court, visuels, galerie et premier commentaire | Élevé, après stabilisation d’une URL produit |
| SaaSHub | Fiche de découverte SaaS / suite métier et comparaison | Préparer une fiche structurée ; vérifier le coût avant soumission | Moyen |
| SourceForge | Distribution et découverte d’un projet open source auto-hébergeable | Créer un projet miroir uniquement si l’équipe veut gérer une seconde présence | Moyen |
| GitHub | Conversion du dépôt en page de découverte développeur | Optimiser description, topics, release, images et liens | Très élevé |

## Réserves avant publication

Le dépôt signale que le domaine `leopardo-rh.com` est actuellement indisponible et que les déploiements publics peuvent être en retard sur la branche `main`. Aucune fiche ne doit présenter ces endpoints comme une offre de production contractuelle. Avant un lancement public, il faut désigner une URL canonique stable, vérifier les comptes de démonstration, confirmer les pays réellement supportés et remplacer les formulations de conformité par des preuves publiées.

## Références

[1]: https://github.com/kitokoh/leopardo-hr "Dépôt GitHub Leopardo"
[2]: https://github.com/kitokoh/leopardo-hr/blob/main/README.md "README et état documenté du projet"
[3]: https://alternativeto.net/faq/ "AlternativeTo FAQ — ajout d’une application"
[4]: https://help.producthunt.com/en/articles/479557-how-to-post-a-product "Product Hunt — champs requis pour publier un produit"
[5]: https://sourceforge.net/p/add_project "SourceForge — création d’un projet open source"
[6]: https://thesaashub.com/submit "TheSaaSHub — page de soumission"
