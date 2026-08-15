## Plan technique
1. Router (`src/router/index.js`) : ajouter une meta `requiresTenant: true` aux routes tenant.
2. Sidebar/layout : filtrer les entrées tenant pour un super-admin ; garder les vraies surfaces plateforme (analytics, users, companies, subscriptions, support, CRM, growth, system, edge, settings, marketing).
3. Guard de navigation : si `requiresTenant` et l'utilisateur courant est super-admin (pas de tenant), rediriger vers `/dashboard` avec message (toast) — ou afficher une page « Fonctionnalité tenant — réservée aux espaces entreprise ».
4. Vérifier que les routes supprimées de la nav restent accessibles ailleurs si besoin (ex. plateforme mobile). Lint + build. CHANGELOG.

## Décision
Ne PAS implémenter d'impersonation tenant dans cette spec (hors périmètre). L'objectif : aucune impasse dans la console super-admin.
