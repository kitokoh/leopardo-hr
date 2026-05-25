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

## Lot 25.3 - Workflows employe reelement livrables

- Absence : demande, historique, statut, annulation si autorisee.
- Avance : demande, statut, plan de remboursement, message RH clair.
- Pointage : arrivee, depart, correction employee-side et modification RH/manager.
- Documents/paie : consultation mobile lisible, erreurs API explicites.

## Lot 25.4 - Workflows manager/RH mobile

- Equipe : liste, ajout, archive, invitations, refresh garanti.
- Presence equipe : resume du jour, retards, absences, corrections.
- Validation : absences, avances et autres demandes avec decision claire.
- Filtrage par role/capabilities afin qu'un utilisateur ne voie que ses ressources.

## Lot 25.5 - Qualite marketing et lancement

- Captures ou parcours demo mobiles stables pour landing page, videos et commerciaux.
- E2E/smoke mobile couvrant login demo, pointage, demande absence, demande avance, equipe manager.
- Guide QA mobile avec comptes demo, URL API, scenarios et resultats attendus.
- Observabilite UX : evenements de succes/echec sur pointage, demande RH et navigation modules.

## Definition of done

- Aucun bouton critique ne tourne indefiniment.
- Chaque action visible appelle une vraie API ou affiche clairement pourquoi elle est indisponible.
- Les ecrans principaux sont lisibles en petit mobile, grands telephones et RTL.
- Les tests Flutter Analyze/Test/APK restent verts en CI.
- Les contrats API/OpenAPI/docs sont alignes a chaque nouvelle route ou payload mobile.
