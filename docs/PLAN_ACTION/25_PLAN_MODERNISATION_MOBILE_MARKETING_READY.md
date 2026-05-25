# Plan 25 - Modernisation mobile marketing-ready

Date : 2026-05-25
Statut : en execution iterative

## Objectif

Rendre l'application mobile Leopardo RH moderne, lisible, fiable et demonstrable devant des prospects. Le mobile doit etre agreable au quotidien pour les employes, managers et RH, tout en consommant les vraies API Render sans etats bloquants ni boutons decoratifs.

## Diagnostic actuel

- Pointage : le bouton peut donner une impression de chargement infini si des etats secondaires restent actifs ou si l'historique est recharge trop souvent.
- Navigation : les modules existent, mais l'experience doit mieux separer les actions quotidiennes, les demandes RH et les espaces manager/RH.
- Design : la base sombre est coherente, mais il faut renforcer la lisibilite, les etats vides utiles, les feedbacks courts et les surfaces communes.
- API : les routes principales existent ; le mobile doit normaliser les payloads, borner les delais et afficher des messages clairs quand Render ou le reseau ralentit.
- Marketing : les ecrans demo doivent raconter immediatement la valeur produit : pointer, demander une absence, demander une avance, consulter son equipe, lire ses notifications.

## Lot 25.1 - Pointage fiable et non bloquant

- Stabiliser la cle de chargement historique pour ne pas relancer `GET /attendance` a chaque tick de l'horloge.
- Empecher les doubles taps pendant un pointage.
- Ajouter un garde timeout cote provider pour que `isPunching` retombe toujours.
- Conserver un feedback immediat : haptique, SnackBar de confirmation, carte du jour mise a jour.
- Garder les chargements historiques non bloquants pour que le pointage reste utilisable.

## Lot 25.2 - Design system mobile premium

- Centraliser les surfaces : fond, cartes, boutons, chips, messages, sections.
- Harmoniser les ecrans critiques : Accueil, Pointage, Absences, Avances, Equipe, Compte.
- Revoir les contrastes, les tailles tactiles, les espacements, les etats vides et erreurs.
- Limiter la surcharge visuelle sur l'accueil : 3 actions prioritaires, modules secondaires plus discrets.

Etat v4.16.139 :

- Livré : composants `MobileEmptyLoading`, `MobileErrorPanel`, `MobileListCard` et `MobileMetricTile`.
- Livré : accueil allege a trois actions prioritaires et quatre modules actifs pour une premiere page moins surchargee.
- Livré : ecrans Absences, Avances et Equipe alignes sur les surfaces mobiles sombres, avec erreurs/retry lisibles.
- Livré : demande d absence mobile branchee sur les soldes/types existants puis `POST /absences`.
- Livré : demande d avance mobile conservee sur `POST /salary-advances`, avec UI coherente.
- Livré : equipe manager/RH modernisee, ajout employe garde date d embauche, role, salaire et invitation API.

## Lot 25.3 - Workflows employe reelement livrables

- Absence : demande, historique, statut, annulation si autorisee.
- Avance : demande, statut, plan de remboursement, message RH clair.
- Pointage : arrivee, depart, correction employee-side et modification RH/manager.
- Documents/paie : consultation mobile lisible, erreurs API explicites.

Etat v4.16.140 :

- Livré : annulation employee-side des absences en attente via `DELETE /absences/{id}`.
- Livré : annulation employee-side des avances en attente via `DELETE /salary-advances/{id}`.
- Livré : confirmations utilisateur, feedback SnackBar et invalidation des providers apres annulation.
- Livré : contrats repository mobiles couvrant les routes d'annulation self-service.

## Lot 25.4 - Workflows manager/RH mobile

- Equipe : liste, ajout, archive, invitations, refresh garanti.
- Presence equipe : resume du jour, retards, absences, corrections.
- Validation : absences, avances et autres demandes avec decision claire.
- Filtrage par role/capabilities afin qu'un utilisateur ne voie que ses ressources.

Etat v4.16.141 :

- Livré : decisions manager/RH absences en attente via `PUT /absences/{id}/approve` et `PUT /absences/{id}/reject`.
- Livré : decisions manager/RH avances en attente via `PUT /salary-advances/{id}/approve` et `PUT /salary-advances/{id}/reject`.
- Livré : actions visibles seulement pour les profils `principal`, `rh` ou capabilities explicites `*.manage` / `*.approve`, hors demandes personnelles qui restent annulables en self-service.
- Livré : refus avec commentaire obligatoire, confirmation avant approbation, refresh provider et feedback SnackBar.
- Livré : composant mobile partage pour actions de decision et tests repository sur les routes de decision.

## Lot 25.5 - Qualite marketing et lancement

- Captures ou parcours demo mobiles stables pour landing page, videos et commerciaux.
- E2E/smoke mobile couvrant login demo, pointage, demande absence, demande avance, equipe manager.
- Guide QA mobile avec comptes demo, URL API, scenarios et resultats attendus.
- Observabilite UX : evenements de succes/echec sur pointage, demande RH et navigation modules.

Etat v4.16.142 :

- Livré : guide QA mobile marketing-ready dans `docs/validation/MOBILE_MARKETING_READINESS.md`.
- Livré : smoke Flutter `mobile_marketing_readiness_test.dart` couvrant les decisions manager/RH et l'annulation self-service employe sur absences/avances.
- Livré : matrice frontend/API enrichie avec les contrats mobiles d'approbation/refus absences et avances.
- Livré : criteres no-go explicites pour demo commerciale mobile : spinner pointage, route API decoratrice, auto-approbation manager/RH et donnees demo manquantes.

## Definition of done

- Aucun bouton critique ne tourne indefiniment.
- Chaque action visible appelle une vraie API ou affiche clairement pourquoi elle est indisponible.
- Les ecrans principaux sont lisibles en petit mobile, grands telephones et RTL.
- Les tests Flutter Analyze/Test/APK restent verts en CI.
- Les contrats API/OpenAPI/docs sont alignes a chaque nouvelle route ou payload mobile.
