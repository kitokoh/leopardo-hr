# Décision — Deux dashboards parallèles (Next.js `(dashboard)` vs admin-dashboard Vue)

> Statut : **acté** (2026-09-01) — issue #6598 (audit Vague 2). Cette décision
> est la réponse produit/architecture au constat de double maintenance.

## Constat

- `front/web/src/app/(dashboard)/` (Next.js) : **36 pages** (accounting ×11,
  crm ×5, marketing ×2, payroll, employees, training…).
- `front/admin-dashboard/` (Vue 3 + Vite) : mêmes domaines en `views/`
  (accounting, crm, marketing, training, users, companies…).

## Décision : cloisonnement par audience, pas par domaine

**`front/admin-dashboard/` (Vue) = cockpit SUPER-ADMIN (plateforme SaaS)**
- Gestion des entreprises/clients, abonnements, plans, santé tenant,
  demandes entrantes, système, intégrations, risques.
- Accès : `super_admin` uniquement (`/platform/login` + SSO plateforme).
- Référence : `AGENTS.md` (« cockpit platform admin », tokens `glass-*`).

**`front/web/src/app/(dashboard)/` (Next.js) = portail CLIENT tenant**
- Modules métier d'un tenant (accounting, crm, marketing, payroll,
  employees, training, restaurant, travel…), RBAC par rôle employé
  (principal/rh/comptable/superviseur/employé).
- Accès : employés du tenant (portail web client).

## Règle pour les contributeurs

1. **Un domaine n'a qu'UN dashboard référent.** Un écran métier pour les
   utilisateurs d'un tenant → `front/web/src/app/(dashboard)/`.
   Un écran d'administration de la PLATEFORME → `front/admin-dashboard/`.
2. **Ne pas dupliquer** un écran existant de l'autre surface sans décision
   du chef de projet (issue dédiée + référence à cette page).
3. Le périmètre de chaque surface est documenté dans les README respectifs
   (`front/web/README.md`, `front/admin-dashboard/README.md`).

## Prochaine action (suivi)

- Audit de recouvrement : lister les écrans réellement dupliqués entre les
  deux surfaces et trancher au cas par cas (migration ou suppression).
  Aucune fusion de frameworks n'est envisagée (coûts/risques disproportionnés).
