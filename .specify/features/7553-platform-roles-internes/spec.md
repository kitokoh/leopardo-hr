# Spec — #7553 / #7557 / #7554 : rôles internes de la plateforme, écran Équipe et cohérence du menu admin

Lot : **BC-01 PLATFORM** (`bc/bc01-roles-plateforme`) — une branche, un commit par issue,
une PR pour le lot (`docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md`).

## Contexte

L'espace plateforme est binaire : `public.super_admins` ne porte aucune colonne de rôle
(seule `status` a été ajoutée par `2026_08_15_000002`), et les groupes de routes
`/api/v1/platform/*` (`api/routes/api.php:383`) et `/api/v1/admin/*` (`:474`) partagent le
seul middleware `auth:super_admin_api`. `PlatformAuthController` code `'role' => 'super_admin'`
en dur à trois endroits. Côté console, `front/admin-dashboard` détruit toute session dont
`role !== 'super_admin'` (`src/stores/auth.js`, login et `checkAuth`) : même avec l'API prête,
aucun collaborateur interne ne pourrait se connecter, et aucune entrée de menu ne sait
exprimer « écran réservé à telle délégation ».

Trois demandes du propriétaire, un seul modèle :

1. **#7553 (API)** — le super admin délègue une partie de l'administration.
2. **#7557 (console)** — écran « Équipe plateforme » + menus filtrés par permissions.
3. **#7554 (console)** — cohérence du menu latéral, de la palette et de la recherche
   (entrée `showcase` jamais surlignée, condition comptabilité morte, trois listes de
   navigation désynchronisées, pages orphelines, bloc « Système » non i18n).

## Comportement cible

1. `super_admins.platform_role` (défaut `super_admin`) porte le rôle interne ; un compte
   sans rôle renseigné reste super admin (rétrocompatibilité stricte).
2. Les permissions sont dérivées du rôle (matrice en code) et vérifiées à l'exécution par
   `platform.permission:<perm>` sur les familles sensibles — dont les 5 exigées par
   l'issue : provisioning entreprise, impersonation, kill switches, offres, équipe.
3. Le propriétaire (rôle `super_admin`, seul porteur de `team.manage`) crée, rétrograde,
   active et désactive les comptes internes depuis `/platform/team` ; les garde-fous
   interdisent l'auto-verrouillage (dernier super admin actif, auto-modification).
4. `GET /platform/auth/me` expose `platform_role` + `permissions` **en plus** de
   `role: "super_admin"`, conservé pour ne pas casser le client déployé.
5. La console accepte tout compte plateforme actif, filtre ses menus sur les permissions
   effectives, et offre un écran « Équipe plateforme » (`/team`) visible avec `team.manage`.
6. La navigation de la console a **une** source de vérité consommée par la sidebar, la
   palette et la recherche ; les pages routées orphelines sont atteignables ou retirées
   (décision motivée) ; les libellés sont i18n dans les 4 locales.

## Hors périmètre

- Généralisation de `platform.permission` à **tout** le cockpit `/admin/*` (seuls
  l'impersonation et le tableau de bord y sont couverts ici) — dette tracée, à trancher
  séparément.
- Modèle de permissions fines par capacité côté tenant (le RBAC `role`/`manager_role`
  reste la source de vérité — cf. #7555).
- Facturation/quota de sièges plateforme : aucun plafond n'est appliqué à la création de
  comptes internes (l'équipe reste petite et nominative).

## Critères d'acceptation

- [x] Migration publique idempotente + CHECK PostgreSQL ; miroirs de test à jour
      (`tests/Support/CreatesMvpSchema.php`, `tests/Support/sql/mvp_schema.pgsql.sql`).
- [x] Enum `PlatformRole` + `PlatformPermission` + matrice documentée
      (`docs/security/PLATFORM_INTERNAL_ROLES.md`), `RBAC_ROUTE_MATRIX.md` étendu.
- [x] 49 routes sensibles protégées + groupe `/team` ; 403 explicite
      (`PLATFORM_PERMISSION_REQUIRED` + permissions requises + rôle courant).
- [x] Garde-fous testés : auto-rôle, dernier super admin actif, révocation des tokens,
      login refusé sur compte désactivé.
- [x] `platform_role` hors `$fillable` (garde `SensitiveFillableGuardTest` étendue).
- [x] OpenAPI : 5 paths + 4 schémas, SDK/miroir régénérés, couverture des routes stricte.
- [x] Console : garde assouplie, `hasPermission()`, écran `/team`, menus filtrés.
- [x] Navigation : source unique, entrée `showcase-editor` corrigée, comptabilité par
      permission, orphelines atteignables, bloc « Système » i18n, palette cohérente.
- [x] Recette : PHPUnit (nouvelle suite + non-régression Platform), PHPStan strict,
      ESLint + build + Playwright (console), gardes i18n/OpenAPI.

## Risques traités

| Risque | Traitement |
|---|---|
| Casser les comptes plateforme existants | défaut `super_admin` en base **et** au modèle (`platformRole()` retombe sur `SuperAdmin` si la valeur est absente ou inconnue) |
| Casser le SPA déployé | `role: "super_admin"` inchangé dans les payloads `auth/*` |
| Se verrouiller dehors | garde « dernier super admin actif » + interdiction d'auto-modification, testées |
| Rôle injecté par mass-assignment | hors `$fillable`, assignation explicite, garde unitaire |
| Contrainte absente en base | CHECK PostgreSQL en plus de la validation HTTP |
