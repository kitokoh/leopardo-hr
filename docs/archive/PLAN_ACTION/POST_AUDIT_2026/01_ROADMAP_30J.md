# ROADMAP 30 JOURS — Stabilisation Production
## Leopardo HR — Post-Audit 2026

**Objectif :** Rendre l'API et les apps mobiles stables pour une bêta fermée avec 1-5 clients pilotes.
**Période :** J1 à J30
**Responsable recommandé :** CTO + Lead Backend + Lead Mobile

---

## ROADMAP 30 JOURS (Stabilisation)

| # | Tâche | Priorité | Effort | Impact | Dépendances |
|---|---|---|---|---|---|
| 1 | **Configurer QUEUE_CONNECTION=redis sur Render** — Forcer les variables d'env Redis/Upstash (voir section 17 du rapport) | 🔴 CRITIQUE | 0.5j | Queues actives en prod | Variables Render |
| 2 | **Configurer REDIS_CLIENT=predis sur Render** — Upstash nécessite predis, pas phpredis | 🔴 CRITIQUE | 0.5j | Cache + queues fonctionnels | Variables Render |
| 3 | **Lancer les workers Horizon sur Render** — Ajouter un worker process (Background Worker) dédié aux queues | 🔴 CRITIQUE | 1j | PDF, notifications, paiements async | QUEUE_CONNECTION=redis |
| 4 | **Implémenter clôture automatique pointage (Plan 64)** — Cron journalier pour fermer les pointages ouverts à minuit | 🔴 CRITIQUE | 2j | Données pointage fiables | AttendanceService |
| 5 | **Corriger timezone multi-pays (Plan 64)** — Stocker les pointages en UTC, afficher en timezone du site | 🔴 CRITIQUE | 2j | Conformité légale, paie correcte | Site model + schedule |
| 6 | **Activer la double validation avances salaires (Plan 60)** — Workflow manager → confirmation employé | 🔴 CRITIQUE | 3j | Risque financier éliminé | SalaryAdvanceService |
| 7 | **Configurer SENTRY_DSN sur Render** — Monitoring erreurs production actif | 🟠 MAJEUR | 0.5j | Observabilité immédiate | Compte Sentry |
| 8 | **Configurer APP_ENV=production + APP_DEBUG=false** — Empêcher la fuite de traces en prod | 🟠 MAJEUR | 0.1j | Sécurité critique | Variables Render |
| 9 | **Activer les seed demo tenant** — Créer un tenant demo stable pour les testeurs | 🟠 MAJEUR | 1j | Démonstration client | DB production |
| 10 | **Tests smoke post-déploiement** — Script bash validant les 10 endpoints critiques après chaque deploy | 🟠 MAJEUR | 1j | Détection régressions | CI workflow |
| 11 | **Documenter les 5 workflows critiques employé** — Pointage, absence, avance, fiche paie, notifications | 🟠 MAJEUR | 2j | Onboarding testeurs | - |
| 12 | **Distribuer les APK via Firebase App Distribution** — Remplacer les placeholders par des liens réels | 🟠 MAJEUR | 1j | Accès testeurs mobile | Build CI |
| 13 | **Valider RBAC multi-tenant** — Tests croisés : un employé de tenant A ne peut pas voir les données de tenant B | 🟠 MAJEUR | 2j | Isolation tenant critique | TenantMiddleware |
| 14 | **Activer rate limiting sur tous les endpoints auth** — Vérifier throttle:auth-sensitive en prod | 🟡 MOYEN | 0.5j | Prévention brute force | Render config |
| 15 | **Vérifier la rotation des tokens Sanctum** — TokenAutoRefreshMiddleware actif et testé | 🟡 MOYEN | 1j | Sécurité session mobile | - |

---

## Critères de sortie (J30)

- [ ] `GET /api/v1/health` retourne 200 avec Redis + DB + Storage verts
- [ ] Un job PDF est dispatché et traité en < 30s
- [ ] Un push FCM est reçu sur un device test
- [ ] Le tenant demo fonctionne avec les 3 apps mobiles
- [ ] Les APK employee, manager, platform_admin sont distribués via Firebase
- [ ] Zéro trace/exception exposée en réponse API publique
- [ ] RBAC cross-tenant validé : 0 fuite de données inter-tenant

---

## Variables d'environnement critiques à configurer MAINTENANT

```
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=REDACTED.upstash.io
REDIS_PORT=6379
REDIS_PASSWORD=<upstash_password>
REDIS_CACHE_DB=1
APP_ENV=production
APP_DEBUG=false
SENTRY_DSN=<sentry_dsn>
```
