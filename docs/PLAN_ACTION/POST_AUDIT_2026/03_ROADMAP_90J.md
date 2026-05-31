# ROADMAP 90 JOURS — Croissance & Premiers Revenus
## Leopardo HR — Post-Audit 2026

**Objectif :** Passer de 1 à 10 clients actifs, activer la monétisation, publier sur les stores.
**Période :** J61 à J90
**Prérequis :** Roadmap 60J complétée (1 client pilote, PDF paie, queues stables)

---

## ROADMAP 90 JOURS (Croissance)

| # | Tâche | Priorité | Effort | Impact | Dépendances |
|---|---|---|---|---|---|
| 1 | **Publication Google Play Store** — App Employee + App Manager (compte développeur, politique confidentialité, screenshots) | 🔴 CRITIQUE | 5j | Distribution grand public | APK stable, RGPD |
| 2 | **Publication Apple App Store** — TestFlight → Production pour Employee + Manager | 🔴 CRITIQUE | 7j | Part de marché iOS | Compte Apple Dev, APK stable |
| 3 | **Activation du billing Stripe/CIB** — PlanController + InvoiceController + webhook paiement | 🔴 CRITIQUE | 5j | Premiers revenus | BillingController |
| 4 | **Pipeline commercial** — CRM léger, suivi des leads, séquences email onboarding | 🟠 MAJEUR | 3j | Acquisition clients | Roadmap GTM |
| 5 | **Feature flags par plan tarifaire** — Modules activés selon plan Starter/Pro/Enterprise | 🟠 MAJEUR | 3j | Monétisation modulaire | FeaturePlanMatrix |
| 6 | **Intégration ZKTeco en production** — ZktecoIntegrationService + sync biométrie réelle | 🟠 MAJEUR | 7j | Différenciation vs concurrents | ZktecoController |
| 7 | **Tests E2E Playwright sur flux critiques** — Login, pointage, avance, paie pour les 3 apps | 🟠 MAJEUR | 5j | Régression zéro | e2e-staging.yml |
| 8 | **Rapport RH mensuel automatisé** — Email récap automatique envoyé au manager en fin de mois | 🟠 MAJEUR | 3j | Valeur perçue manager | HrReportController |
| 9 | **Déclarations sociales automatisées (DZ/MA)** — SocialDeclarationGenerator opérationnel | 🟠 MAJEUR | 5j | Conformité légale client | PayrollService |
| 10 | **Programme de référence** — Invitation manager → nouvel employeur | 🟡 MOYEN | 2j | Croissance organique | InvitationController |
| 11 | **Intégration calendrier (Google/Outlook)** — CalendarSyncController opérationnel | 🟡 MOYEN | 3j | UX manager améliorée | CalendarSyncService |
| 12 | **Cache Redis avancé** — TenantCacheService sur les endpoints liste (employees, absences, attendance) | 🟡 MOYEN | 3j | Performance à l'échelle | TenantCacheService |
| 13 | **Placard numérique kiosque** — KioskAnnouncement broadcast vers les kiosques ZKTeco | 🟡 MOYEN | 3j | Différenciation terrain | KioskController |
| 14 | **Cas d'études clients documentés** — 2-3 témoignages avec métriques (temps économisé, erreurs évitées) | 🟡 MOYEN | 3j | Crédibilité GTM | Client pilote |
| 15 | **Optimisation SEO vitrine** — Blog articles, landing pages pays, schema.org | 🟡 MOYEN | 3j | Acquisition organique | front/web |

---

## Critères de sortie (J90)

- [ ] Apps disponibles sur Google Play Store (Employee + Manager)
- [ ] 5+ clients actifs payants (MRR > 0)
- [ ] Billing Stripe opérationnel avec 1ère facture émise
- [ ] Tests E2E passant sur flux login→pointage→paie
- [ ] ZKTeco intégré chez au moins 1 client
- [ ] P99 < 300ms sur 500 utilisateurs simultanés (k6)
- [ ] Cas d'étude client publié sur la vitrine
