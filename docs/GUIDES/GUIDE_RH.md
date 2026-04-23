# Guide RH — Leopardo RH

Le sous-role **manager_role=rh** est un manager avec des droits focalises sur
le cycle de vie des employes : creation, invitation, relance, archivage.

Ce guide complete [`GUIDE_MANAGER.md`](GUIDE_MANAGER.md) — ne revient pas sur
les elements communs, focus sur ce qui est specifique RH.

## Ce que vous pouvez faire

- Creer un employe (`/employees/create`).
- Envoyer et relancer des invitations (`/hr/invitations`).
- Modifier un employe existant (matricule, role, sous-role, contact).
- Archiver un employe (soft delete : il ne compte plus dans les effectifs,
  ses donnees de paie restent pour la comptabilite).
- Consulter la fiche individuelle d un employe (pointages du mois, heures
  sup, estimation paie).

## Ce que vous ne pouvez pas faire

- Creer un autre manager (reserve au sous-role `principal`).
- Supprimer definitivement un employe (RGPD : on archive, on ne supprime
  pas, sauf demande explicite de la personne concernee).
- Approuver la biometrie ou declarer un kiosque (reserve a `principal` +
  `superviseur`).
- Changer le plan ou les modules actifs de la societe (reserve au super-admin
  plateforme).

## Cycle de vie d une invitation

```
[Creation employe] ----> [Invitation 'pending'] ----> [Activation]
                              |                            |
                              v                            v
                       [Renvoyer]                   [Invitation 'accepted']
                              |                     (token supprime)
                              v
                     [Expiration > 7j]
                              |
                              v
                  [Regenerer = nouveau token,
                   ancien invalide automatiquement]
```

Cote mobile, l onglet **Equipe > Invitations** permet :

- Lister les invitations en cours (`pending`).
- Cliquer **Renvoyer** -> SnackBar "Invitation renvoyee" + email expedie.
- Les UUID d invitation sont manipules cote client comme des `String`
  (regression couverte par `test/features/invitations/invitation_model_test.dart`).

## Archivage vs suppression

L archivage d un employe :

- Desactive son compte (il ne peut plus se connecter).
- Conserve ses pointages et son historique (necessaire pour la paie du mois
  en cours et la conformite).
- Retire sa ligne des listes / exports par defaut.

Pour une suppression RGPD (droit a l oubli), ouvrir un ticket avec le
super-admin : il y a des donnees croisees a purger proprement.

## Checklist onboarding employe

1. Verifier l email (pas de faute de frappe — l employe ne recoit rien sinon).
2. Fixer le role (`employee` simple) et, si pertinent, le matricule.
3. Cliquer **Creer**. L employe recoit son email.
4. Si l email n arrive pas dans la journee : verifier le dossier spam avec lui,
   sinon **Renvoyer**.
5. A l activation, l employe est redirige vers l app mobile
   (`/mobile` cote web, redirection `Employee::homeRoute()`).
