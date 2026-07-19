# ROADMAP 60 JOURS — Consolidation Fonctionnelle
## Leopardo HR — Post-Audit 2026

**Objectif :** Consolider les modules financiers, améliorer l'UX mobile et préparer le premier lancement client réel.
**Période :** J31 à J60
**Prérequis :** Roadmap 30J complétée (queues actives, RBAC validé, APK distribués)

---

## ROADMAP 60 JOURS (Consolidation)

| # | Tâche | Priorité | Effort | Impact | Dépendances |
|---|---|---|---|---|---|
| 1 | **Implémenter solde employé en temps réel (Plan 61)** — Endpoint `/api/v1/me/balance` avec avances déduites, cycle paie visible | 🔴 CRITIQUE | 4j | Confiance employé, rétention | PayrollCycleService |
| 2 | **PDF bulletins de paie async (Plan 62)** — GeneratePaySlipPdfJob dispatché sur queue `pdf`, lien de téléchargement différé | 🔴 CRITIQUE | 3j | Fonctionnalité paie complète | Horizon workers |
| 3 | **Paiements en masse avec confirmation employé (Plan 65)** — BulkPaymentJob + signature numérique simple | 🔴 CRITIQUE | 5j | Légitimité financière | SalaryAdvanceService |
| 4 | **Intégration GPS geofence douce (Plan 64)** — Warning si pointage hors zone, pas blocage strict | 🟠 MAJEUR | 3j | Confiance terrain | Site + Schedule models |
| 5 | **Onboarding QR Code fiabilisé (Plan 54)** — LeopardoQrCard fonctionnel en production sur les 3 apps | 🟠 MAJEUR | 2j | Acquisition employés rapide | OnboardingQrService |
| 6 | **Système de notifications push fiabilisé** — SendPushNotificationJob + retry + dead-letter queue | 🟠 MAJEUR | 2j | Engagement utilisateur | FCM HTTP v1 |
| 7 | **Tests de charge k6 baseline** — Scénario 100 utilisateurs simultanés, identifier les P99 > 500ms | 🟠 MAJEUR | 3j | Confiance avant lancement | k6-load-smoke.yml |
| 8 | **Audit trail complet (AuditLog)** — Toutes les actions sur données RH/paie/avances loggées avec user_id + tenant_id | 🟠 MAJEUR | 3j | RGPD + sécurité | AuditLog model |
| 9 | **Branding tenant premium (Plan 58)** — Logo, couleurs, nom de domaine personnalisé par tenant | 🟡 MOYEN | 4j | Valeur perçue client | CompanySetting |
| 10 | **Dashboard manager opérationnel** — Taux de présence temps réel, absences du jour, avances en attente | 🟡 MOYEN | 3j | Adoption manager | ManagerDashboard |
| 11 | **Swagger/OpenAPI complet et déployé** — Tous les endpoints V1 documentés, accessible sur /api/docs | 🟡 MOYEN | 3j | Intégration client + partenaires | openapi-ci.yml |
| 12 | **Premier client pilote intégré** — Onboarding d'une PME réelle avec 5-50 employés | 🟡 MOYEN | 5j | Validation marché | Roadmap 30J |
| 13 | **Support multilingue validé en production** — FR + AR RTL validés sur les 3 apps mobiles | 🟡 MOYEN | 2j | Marchés Maghreb | i18n-enterprise.yml |
| 14 | **Monitoring SLA Upstash Redis** — Alertes sur latence > 50ms, erreurs connexion | 🟡 MOYEN | 1j | Fiabilité infrastructure | Sentry / UptimeRobot |
| 15 | **Documentation déploiement mise à jour** — Guide Render complet avec toutes les variables d'env | 🟡 MOYEN | 1j | Autonomie équipe | DEPLOYMENT_GUIDE.md |

---

## Critères de sortie (J60)

- [ ] 1 client pilote réel avec 10+ employés actifs
- [ ] PDF bulletin de paie généré et téléchargeable en < 60s
- [ ] Solde employé visible dans l'app avec cycle de paie correct
- [ ] k6 : P99 < 500ms sur 100 utilisateurs simultanés
- [ ] Audit trail : toutes les actions paie/avances loggées
- [ ] Swagger accessible et complet sur gestionemployerbackend.onrender.com/api/docs
- [ ] Push notifications fonctionnelles sur Android ET iOS (TestFlight)
