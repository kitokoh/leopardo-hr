# Leopardo RH — Release v0.1.0

## Vue d'ensemble

Premiere release publique de **Leopardo RH**, plateforme SaaS de gestion des ressources humaines multi-tenant.

## Fonctionnalites principales

### Backend API (Laravel 11 + PHP 8.4 + PostgreSQL 16)

- **Gestion employes** : CRUD complet, import CSV, profils avec champs chiffres (IBAN, NIR)
- **Pointage & Presence** : anomalies, rapport mensuel, geofence, biometrie
- **Paie** : structures salariales, runs calcul, bulletins PDF, cotisations sociales (FR, DZ, MA, TN, SN, CI)
- **Conges** : politiques, soldes, workflows d'approbation, carry-forward
- **Contrats** : CDI/CDD/Stage, alertes expiration, avenants
- **Formation** : catalogue, sessions, inscriptions, suivi progression
- **Recrutement** : offres emploi, pipeline candidatures Kanban
- **Notes de frais** : soumission, approbation, remboursement
- **Prets** : demande, approbation, echeancier remboursement
- **Organigramme** : chaine hierarchique, vue arborescente
- **Audit** : journal d'audit complet, export CSV
- **IA** : workflows paie/rapports, simulation cotisations, agents configures
- **Declarations sociales** : CNAS (Algerie), CNSS (Maroc)
- **API versionnee** : middleware ApiVersion, rate limiting par plan, compression gzip

### Admin Dashboard (Vue 3 + Vite + Tailwind CSS)

- **Dashboard** : KPIs effectif, pointage, paie, conges
- **Modules** : Paie, Conges, Contrats, Formation, Recrutement (Kanban), Rapports RH, Audit, Webhooks, Exports
- **Plateforme** : gestion multi-tenant, portefeuille clients, health score, abonnements
- **Composants** : DataTable, StatsCard, StatusBadge, KanbanBoard, panneau detail slide-over

### Site Vitrine (Next.js 14 + TypeScript + Tailwind CSS)

- **Pages** : Accueil, Fonctionnalites, Tarifs, Blog (MDX), Changelog, Contact
- **SEO** : sitemap dynamique, robots.txt, schema.org JSON-LD
- **Newsletter** : formulaire d'inscription dans le footer
- **i18n** : francais, anglais, arabe

### Mobile (Flutter)

- **Modules** : Pointage, Bulletins paie, Conges, Profil
- **Biometrie** : empreinte, reconnaissance faciale
- **Notifications** : push Firebase (structure)

### Securite & Conformite

- **Multi-tenant** : isolation par schema PostgreSQL (company_id, search_path)
- **RGPD** : endpoints self-service (export, suppression, consentement biometrique)
- **Chiffrement** : AES-256 sur champs sensibles (casts `encrypted`)
- **Matrice conformite** : RGPD (UE), loi 18-07 (Algerie), loi 09-08 (Maroc)

### CI/CD & Qualite

- **GitHub Actions** : 10+ workflows (backend, frontend, mobile, security, governance)
- **Coverage** : 56%+ backend, 21%+ mobile
- **PHPStan** : niveau 6, diff-gate sur fichiers modifies
- **Pint** : formatage automatique
- **Playwright** : tests E2E admin dashboard

## Commencer

```bash
git clone https://github.com/kitokoh/leopardo-hr.git
cd leopardo-hr
# Voir DEVELOPMENT.md pour le guide de setup complet
```

## Licence

Voir le fichier `LICENSE` a la racine du depot.
