# Mini-spécification — Issue #2639

## Objectif

Supprimer deux fallbacks i18n dans l’admin dashboard et rendre les titres d’onglet localisés pour les routes dont `meta.title` contient directement une clé de catalogue.

## Constat

Les composants utilisateurs appelaient `users.errors.password_min` et `users.toast.bulkDone`, mais ces clés n’existaient dans aucun des quatre catalogues FR/EN/TR/AR. En outre, la garde du routeur écrivait `to.meta.title` tel quel dans `document.title`, ce qui affichait `marketing.oauth.nav_title - Leopardo RH Admin` et `holidays.nav.title - Leopardo RH Admin` au lieu de leurs traductions.

## Périmètre

Le correctif ajoute les deux clés dans les quatre catalogues et traduit le titre dans `router.beforeEach` avec la locale active et le fallback existant. Aucun changement de route, d’API ou de logique métier n’est introduit.

## Critères d’acceptation

1. `users.errors.password_min` existe dans FR, EN, TR et AR.
2. `users.toast.bulkDone` existe dans FR, EN, TR et AR.
3. La navigation vers `/marketing/oauth` affiche le titre traduit correspondant à `marketing.oauth.nav_title`.
4. La navigation vers `/settings/payroll/holidays` affiche le titre traduit correspondant à `holidays.nav.title`.
5. Les routes dont `meta.title` est déjà un texte restent fonctionnelles grâce au fallback.
6. Le lint et le build du dashboard admin passent.

## Fichiers concernés

- `front/admin-dashboard/src/i18n/locales/{fr,en,tr,ar}.json`
- `front/admin-dashboard/src/router/index.js`
- `docs/specifications/ISSUE_2639_ADMIN_I18N_DOCUMENT_TITLE.md`
- `CHANGELOG.md`

## Plan de retour arrière

Réversion du commit de l’issue #2639. Les catalogues sont des fichiers statiques et aucun stockage ou schéma serveur n’est modifié.

## Trace Spec Kit

Issue : #2639  
Branche : `fix/2639-admin-i18n-document-title`  
Date : 2026-08-15
