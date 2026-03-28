# USER FLOWS – LEOPARDO RH

## 1. Onboarding Entreprise
- Création compte entreprise
- Vérification email
- Création profil entreprise
- Choix abonnement
- Paiement
- Accès dashboard

## 2. Ajout Employé
- Admin clique "Ajouter employé"
- Remplit infos (nom, poste, salaire, horaires)
- Attribution rôle
- Génération identifiants
- Notification envoyée à l’employé

## 3. Pointage Employé
- Employé ouvre app mobile
- Clique "Pointer"
- Capture :
  - heure
  - GPS (optionnel)
  - photo (optionnel)
- Enregistrement en base
- Vérification automatique :
  - retard ?
  - absence ?
- Mise à jour tableau présence

## 4. Gestion Retard / Absence
- Système détecte anomalie
- Notification manager
- Manager valide ou rejette
- Mise à jour statut :
  - justifié
  - non justifié

## 5. Génération Paie
- Admin lance calcul
- Système agrège :
  - heures travaillées
  - absences
  - retards
- Calcul automatique salaire
- Génération fiche PDF

## 6. Paiement Abonnement SaaS
- Admin accède billing
- Choix plan
- Paiement (Stripe)
- Activation plan
- Facture générée

## 7. Gestion rôles
- Admin modifie permissions
- Application immédiate