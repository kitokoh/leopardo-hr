# Architecture et Stratégie Go-To-Market (GTM)

Ce document décrit comment Leopardo RH acquiert, convertit et fidélise ses clients (tenants). Il sert de référence pour toutes les évolutions techniques liées à la commercialisation.

## 1. Moteur d'Acquisition (Comment on trouve des clients)

### A. Inbound (SEO & Contenu)
**Outil :** Vitrine Web (Next.js - `front/web`)
**Objectif :** Attirer les clients qui cherchent une solution RH sur Google.
**Implémentation Technique :**
- Les pages doivent être statiques (SSG) ou Server-Side Rendered (SSR) pour un SEO parfait.
- Le blog (`/blog`) et les guides (`/guides`) sont les portes d'entrée organiques.
- Le portail carrières public (`[companySlug]/careers`) sert aussi de vecteur de visibilité : chaque candidat qui postule chez un client Leopardo découvre la marque Leopardo RH.

### B. Outbound (Réseaux Sociaux)
**Outil :** Application Mobile `leopardo_marketing`
**Objectif :** Faire connaître la marque activement sans effort manuel.
**Implémentation Technique :**
- L'app permet de programmer des publications (LinkedIn, X/Twitter, Facebook).
- Un CRON job (via les Jobs Laravel) exécute la publication à l'heure prévue.
- Cible : Dirigeants de PME, DRH, et Managers.

## 2. Moteur de Conversion (Comment on signe les clients)

### A. L'Essai Guidé (Sandbox)
**Outil :** Route API `/api/forms/signup` + Vitrine Web
**Processus :**
1. Le client entre son email sur la vitrine.
2. Le système provisionne automatiquement un **Tenant de Démonstration** (schéma PostgreSQL isolé) avec de fausses données (Employés fictifs, historiques de congés).
3. Le client reçoit un lien magique pour accéder immédiatement au `admin-dashboard` et tester l'outil.
4. **Pas de carte bancaire** demandée à l'inscription pour réduire la friction.

### B. Le Kiosk Physique comme Cheval de Troie
**Outil :** Application `leopardo_kiosk`
**Processus :** Le fait de fournir (ou de permettre de configurer) une tablette/pointeuse physique très visuelle à l'entrée des bureaux du client est un puissant levier de rétention. Le hardware rend le désabonnement (churn) beaucoup plus difficile psychologiquement.

## 3. Méthodologie Design-First (UI/UX)

Pour s'assurer que la vitrine et les applications convertissent bien, l'esthétique doit être irréprochable.
- **Règle :** Aucune nouvelle interface majeure ne doit être codée sans une maquette préalable.
- **Outil :** Utilisation des agents IA (Antigravity `generate_image`) pour générer des mockups visuels (Glassmorphism, animations) avant l'implémentation Tailwind/Flutter.
- **Cohérence :** Les tokens de design (`glass-*`, `premium-text`) doivent être appliqués partout.
