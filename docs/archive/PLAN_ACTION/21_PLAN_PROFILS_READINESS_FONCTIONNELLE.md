# PLAN 21 - READINESS FONCTIONNELLE PAR PROFIL

**Date :** 2026-05-22  
**Objectif :** garantir que Leopardo RH peut etre teste, demo et lance avec des profils coherents sur API, web client, admin, mobile et kiosk.

---

## 1. Observation terrain

Les plans 18, 19 et 20 ont solidifie la connexion client, la communication interne et le go-live. Le risque suivant avant marketing est plus operationnel : un prospect ou un testeur peut se connecter avec un profil donne, mais tomber sur un espace vide, un acces incoherent ou une demo qui ne couvre pas son metier.

Le socle API existe deja. Il faut maintenant verrouiller la lisibilite par profil :

- super-admin plateforme ;
- manager principal ;
- RH ;
- manager departement ;
- comptable ;
- superviseur ;
- employe ;
- kiosk / device terrain.

---

## 2. Objectifs Plan21

1. Stabiliser une matrice de profils demo exploitable par l'equipe, les tests et les interfaces.
2. Enrichir les seeders pour couvrir communication, preferences, device tokens, evenements client, kiosk et biometrie.
3. Ajouter des tests fonctionnels transverses sur les droits critiques par profil.
4. Rendre `/api/v1/demo-users` suffisamment explicite pour guider les QA, commerciaux et agents IA.
5. Documenter les observations et garder une base saine pour les prochains lots.

---

## 3. Matrice cible

| Profil | Surface principale | Acces attendu | Donnees seedees |
|---|---|---|---|
| Super admin | Admin plateforme | tenants, plans, support, monitoring | compte plateforme |
| Principal | Web client + mobile manager | dashboard, readiness, paie, exports, equipe | preferences, events, notifications |
| RH | Web client + mobile manager | absences, employes, communication analytics | preferences, communications |
| Manager departement | Web client | equipe, projets, absences equipe | events, notifications |
| Comptable | Web client | paie, exports, suivi financier | events, notifications |
| Superviseur | Web + kiosk terrain | pointage, biometrie, equipe site | kiosk, biometric requests |
| Employe | Mobile + web self-service | me, pointage, absences, bulletins, notifications | token device, preferences |
| Kiosk | Site terrain | roster, punch, sync, QR | device code, statut active |

---

## 4. Lots d'execution

### Lot 21.1 - Contrat profils demo

- enrichir l'endpoint `/api/v1/demo-users` avec les personas operationnels ;
- exposer pour chaque persona : role, manager_role, surface cible, route conseillee, usages de test ;
- garder le mode demo bloque en production sauf `APP_DEMO_MODE_ENABLED=true`.

### Lot 21.2 - Seeders readiness

- creer les preferences de notification pour tous les profils demo ;
- creer des evenements de communication pour email, app, push, SMS et WhatsApp audit-only ;
- creer des evenements client pour dashboard, mobile, readiness, notifications ;
- creer des device tokens de demo pour mobile/web ;
- creer au moins un kiosk actif et des demandes biometrie selon les tables disponibles.

### Lot 21.3 - Tests par profil

- tester les endpoints readiness et communication analytics sur les roles autorises/refuses ;
- tester les pages web sensibles manager/RH/superviseur/employe ;
- tester le contrat public demo-users pour eviter les regressions commerciales.

### Lot 21.4 - Documentation et passation

- mettre a jour le sommaire, le changelog et AGENTS.md ;
- lister les observations utiles pour le prochain plan ;
- verifier syntaxe et tests cibles.

---

## 5. Criteres de validation

- Un QA peut choisir un profil depuis `/api/v1/demo-users` sans deviner les emails.
- Le seeder demo produit une base credible pour vendre et tester.
- Les roles non autorises restent bloques sur les endpoints sensibles.
- Les tests ciblent de vrais parcours et pas seulement l'existence de routes.
- Les prochaines equipes savent quelles donnees demo sont canoniques.

---

## 6. Suite recommandee apres livraison

Le plan suivant devra passer de la readiness profil a la readiness parcours complet :

- scenario complet inscription prospect -> tenant -> principal -> RH -> employe ;
- fixtures de demo par secteur ;
- smoke E2E preview sur les personas principaux ;
- guide commercial avec captures et scripts de demonstration.
