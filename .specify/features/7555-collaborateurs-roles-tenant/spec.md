# Spec — #7555 / #7556 : rôles des collaborateurs côté client et ergonomie du menu mobile

Lot : **BC-02 TENANT** (`bc/bc02-collaborateurs-roles`) — une branche, un commit par issue,
une PR pour le lot.

## Contexte

Un tenant qui crée son compte depuis le web client peut ajouter des collaborateurs, mais
**pas leur donner de rôle** : `front/web/src/app/(dashboard)/employees/page.tsx` envoie
`role: 'employee'` en dur et son formulaire n'expose aucun champ de rôle, alors que l'API
accepte `role in:employee,manager` + `manager_role in:principal,rh,dept,comptable,superviseur,marketing`
(`StoreEmployeeRequest.php:54-55`) et que `PATCH /employees/{id}` sait changer un rôle.
Les invitations (`GET /invitations`, `POST /invitations/{id}/resend`) existent côté API et
ne sont consommées par **aucune** page : impossible de savoir qui a accepté son invitation
ni de la relancer.

Sur mobile (< 768 px), l'en-tête du dashboard client présente deux boutons hamburger
**identiques** (rail métier `dashboard-nav-toggle` et modules `dashboard-modules-nav-toggle`),
des panneaux déroulants (notifications, modules, utilisateur, sous-menu RH) qui ne se
ferment qu'en recliquant leur bouton — sans voile, sans verrouillage du défilement, sans
hauteur bornée — et aucun accès direct aux réglages hors du petit avatar.

## Comportement cible (#7555)

1. Page `/settings/team` « Collaborateurs et rôles », réservée aux managers (état « accès
   réservé » propre, sans appel API) :
   - liste des collaborateurs avec **rôle lisible** et **statut d'invitation** (fusion
     `GET /employees` + `GET /invitations` par `employee_id`), recherche et pagination ;
   - invitation avec **choix du rôle** (`send_invitation: true`, `manager_role` si manager) ;
   - changement de rôle par ligne, renvoi d'invitation, archivage avec confirmation in-app ;
   - erreurs API traduites (`EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY`, `INVITATION_ALREADY_ACCEPTED`…).
2. Garde-fous API **reflétés** : `principal` jamais proposé (création comme modification),
   ligne du compte courant non modifiable.
3. `/employees` cesse d'envoyer `role: 'employee'` en dur et affiche le rôle réel.
4. Entrée de navigation vers la page (menu de compte).
5. i18n : namespace `teamRoles.*` dans les 4 locales, aucun littéral visible en dur.

## Comportement cible (#7556)

1. **Un seul point d'entrée** de navigation sous `md` : un tiroir unique = rail métier +
   modules entreprise + section Compte (Mon compte, Collaborateurs et rôles, mot de passe,
   2FA, notifications, déconnexion).
2. Voile, verrouillage du défilement (compté), fermeture par Échap, au clic extérieur et à
   chaque navigation, pour tous les panneaux ; un seul panneau ouvert à la fois.
3. Hauteur bornée (`max-h-[70vh]`) avec défilement interne.
4. Le dropdown de modules reste disponible en `md → lg` (la nav horizontale est `lg:flex`).
5. Contrats e2e préservés : `dashboard-nav-toggle` (+`aria-expanded`, `aria-controls`),
   `dashboard-nav-backdrop`, `business-rail`, verrou `document.body.style.overflow`,
   fermeture par Échap, aucun débordement horizontal ; desktop inchangé.

## Hors périmètre

- Permissions fines par capacité côté tenant : le modèle `role`/`manager_role` reste la
  source de vérité (pas de table de permissions).
- Suppression d'un collaborateur (l'API n'expose qu'un archivage).
- Refonte desktop de l'en-tête (seul le comportement `< lg` change).

## Critères d'acceptation

- [x] Page `/settings/team` complète (liste + invitation + rôle + renvoi + archivage).
- [x] `principal` absent des options ; auto-modification impossible.
- [x] `/employees` avec sélecteur de rôle.
- [x] Tiroir mobile unique + panneaux fermables et bornés.
- [x] Tests Jest (17 cas au total sur le parcours rôles) + specs Playwright préservées.
- [x] i18n `teamRoles.*` ×4, garde i18n verte, catalogues régénérés.
- [x] CHANGELOG.

## Risques traités

| Risque | Traitement |
|---|---|
| Contreditre un garde-fou API | `principal` non proposé, auto-modification neutralisée, erreurs 422 affichées telles quelles |
| Casser le contrat e2e du menu mobile | les 4 cas de `dashboard-mobile-nav.spec.ts` sont exécutés à chaque itération ; nouveau spec dédié pour les nouveaux contrats |
| Deux overlays se restituent leur `overflow` | verrou de défilement **compté** (hook partagé) |
| Voile masquant la barre (contexte d'empilement) | voile rendu dans la colonne `relative z-10`, vérifié par `elementFromPoint` |
| Appels API sur un compte non manager | état « accès réservé » décidé avant tout appel |
