# Guide manager — Leopardo RH

Le **manager principal** est le premier utilisateur d une societe : il recoit
l email d invitation du super-admin, active son compte, et devient
administrateur de la societe (creation d employes, gestion des invitations,
approbation biometrie, parametrage).

Les sous-roles managers partagent la meme interface avec des permissions
reduites :

| Sous-role | Peut | Ne peut pas |
| --- | --- | --- |
| `principal` | Tout, y compris creer d autres managers | - |
| `rh` | Gerer employes + invitations + archivage | Creer un autre manager |
| `superviseur` | Voir equipe, approuver biometrie, kiosques | Modifier employes |
| `comptable` | Exporter paie, voir rapports | Gerer invitations |
| `dept` | Voir son equipe uniquement | Hors-perimetre |

## Activation de son compte (premiere connexion)

1. Dans l email recu de la part du super-admin, cliquer le lien
   `https://<domaine>/activate/<token>`.
2. Definir un mot de passe (min 8 caracteres).
3. Connexion automatique, redirection vers `/dashboard`.

## Dashboard manager

Sur `/dashboard` (web) :

- **Resume du jour** : employes en service, en pause, absents.
- **Alertes** : invitations expirees, kiosques hors-ligne, demandes
  biometrie en attente.
- **Acces rapide** : creer un employe, inviter, voir l equipe.

## Inviter un employe

1. `/employees/create` ou bouton **Nouvel employe** sur le dashboard.
2. Remplir prenom, nom, email, sous-role (employee / manager + sous-role).
3. Enregistrer -> un email est envoye automatiquement avec un lien
   d activation.

Les invitations restent visibles sur `/hr/invitations` :

- **En attente** : le destinataire n a pas encore active.
- **Expiree** : au-dela de 7 jours sans activation. Bouton **Renvoyer** pour
  regenerer un lien (l ancien est invalide).
- **Acceptee** : l employe a un compte, le lien est archive.

## Gestion de l equipe

Depuis le mobile (onglet **Equipe**, visible uniquement si `canManageTeam` =
`true` dans `/auth/me`) :

- Onglet **Employes** : liste des membres, CRUD complet (ajouter, editer,
  archiver).
- Onglet **Invitations** : invitations en cours, bouton **Renvoyer** par
  ligne.

## Biometrie et kiosques

Reserve aux sous-roles `principal` et `superviseur` :

- `/biometrics` : approuver / rejeter les demandes d enrollement facial
  ou empreinte soumises par les employes.
- `/biometrics/kiosks` : declarer un nouveau kiosque ZKTeco (device code,
  localisation, modele). Le kiosque recoit un `X-Kiosk-Token` a configurer
  dans sa borne.

## Rapports et paie

- **Quick Estimate** : estimation paie en temps reel pour un employe
  (heures normales, heures sup, deductions, net).
- **Recu mensuel** : PDF imprimable par employe.
- **Export comptable** : reserve au sous-role `comptable` (Phase 2 si
  module `muhasebe` ou `finance` est active).

## Ce que vous ne voyez pas

- Les donnees des autres societes : isolation totale par `company_id`
  (verifie par `MultiTenantSharedIsolationTest`).
- Les flags `features` de votre societe : seul le super-admin peut activer
  / desactiver des modules. Si vous voulez activer Finance ou Cameras,
  contactez-le.
