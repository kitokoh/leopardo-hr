# Guide super-admin — Leopardo RH

Le **super-admin** gere la plateforme elle-meme : les societes clientes, leurs
modules actifs, leur statut d abonnement. Il n a acces a aucune donnee RH
interne d une societe (c est le role du manager principal).

## Connexion

- URL : `https://<votre-domaine>/platform/login`
- Credentials : seeded via `SUPER_ADMIN_EMAIL` + `SUPER_ADMIN_PASSWORD`
  (variables d environnement cote serveur, voir [`api/.env.example`](../../api/.env.example))
- Mot de passe perdu : commande console `php artisan super-admin:reset-password`

> L espace super-admin est distinct de l espace manager d une societe
> (guard Laravel + table distincts). Un meme email ne peut pas etre a la fois
> super-admin et manager (verification au provisioning).

## Onboarder une nouvelle societe (3 clics)

1. Sur `/platform/companies`, cliquer **Nouvelle societe**.
2. Remplir le formulaire (nom, pays, ville, email societe, plan, manager
   principal : prenom + nom + email).
3. Cliquer **Creer la societe**.

Ce qu il se passe en arriere-plan :

- Une ligne est creee dans `public.companies` (UUID, `tenancy_type='shared'`,
  `schema_name='shared_tenants'`).
- Un manager principal (role `manager`, manager_role `principal`) est cree
  dans `shared_tenants.employees` avec `company_id` lie.
- Une invitation email est envoyee automatiquement avec un lien d activation
  `https://<domaine>/activate/<token>` valide 7 jours.
- L enregistrement apparait dans la liste, statut `active`, module **RH**
  actif par defaut.

## Editer une societe (toggler modules, statut, notes)

Depuis `/platform/companies` -> bouton **Editer** sur la ligne.

### Modules actifs

Un client demande le module **Securite / Cameras** ou **Finance** ? Cochez la
case correspondante et enregistrez. Effets immediats :

- La carte `features` dans `/auth/me` et `/auth/login` reflete le nouveau
  statut (le mobile affiche / masque les icones en consequence).
- Les routes backend du module sont protegees par `FeatureFlag::enabled` :
  un employe d une societe sans `finance=true` recoit 403 sur
  `/api/v1/finance/*`.
- Le module **RH** est toujours actif (socle de l app, APV L.08).

### Statut

- `active` : nominal.
- `suspended` : l app mobile + web renvoie 401 sur toute requete API
  (tokens Sanctum revoques automatiquement lors du basculement).
- `expired` : idem, utilise en fin de contrat non renouvele.

### Notes internes

Champ libre visible uniquement par les super-admins (historique contrat,
debrief deploiement, remarque client). Non expose aux managers.

## Renvoyer l invitation manager

Si le manager n a jamais recu son email d activation (spam, adresse erronee
corrigee depuis) :

1. Ouvrir la societe en edition.
2. Cliquer **Renvoyer l invitation manager**.
3. Un nouveau lien est genere (l ancien est invalide), un email est expedie.

## Ce que vous ne pouvez pas faire (par design)

- Voir les employes / pointages / paie d une societe : role reserve au
  manager principal (confidentialite). Pour diagnostiquer un probleme, passer
  par les logs serveur ou demander au manager un extract.
- Changer le `schema_name` ou `tenancy_type` d une societe : fige apres
  provisioning (migration tenant -> dedicated = script ops manuel).
- Re-utiliser l email d un ancien manager sur une nouvelle societe : le
  systeme verifie `public.user_lookups` (un email = un tenant).

## Actions ops associees

- Rollback : [`docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md`](../GESTION_PROJET/RUNBOOK_ROLLBACK.md)
- Backup / restore : [`docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`](../GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md)
- Observability (Sentry) : [`docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md`](../GESTION_PROJET/RUNBOOK_OBSERVABILITY.md)
