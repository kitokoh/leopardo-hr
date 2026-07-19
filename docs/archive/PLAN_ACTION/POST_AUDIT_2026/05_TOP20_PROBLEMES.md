# TOP 20 PROBLÈMES À RÉSOUDRE
## Leopardo HR — Audit Enterprise 2026

Classés par criticité décroissante. Les 7 premiers sont des bloquants production.

---

| # | Problème | Criticité | Impact | Plan d'action |
|---|---|---|---|---|
| 1 | **QUEUE_CONNECTION=sync par défaut** — En production, tous les jobs (PDF paie, FCM push, paiements batch) s'exécutent de manière synchrone si la variable n'est pas forcée à `redis`. Risque de timeout HTTP 30s+ et perte silencieuse de jobs | 🔴 CRITIQUE | Tous les traitements async bloqués | Configurer `QUEUE_CONNECTION=redis` sur Render + lancer Horizon worker |
| 2 | **REDIS_CLIENT=phpredis incompatible Upstash** — Upstash Redis Serverless nécessite le client `predis` (pas phpredis) pour la connexion TLS. L'absence de cette config entraîne l'échec silencieux de toutes les opérations cache et queues | 🔴 CRITIQUE | Cache et queues inopérants | Configurer `REDIS_CLIENT=predis` + `predis/predis` dans composer.json |
| 3 | **Absence de workers Horizon dédiés sur Render** — Sans un Background Worker Render configuré, les queues Redis ne sont jamais consommées même si elles sont correctement configurées | 🔴 CRITIQUE | Notifications, PDF, paiements jamais traités | Ajouter un Background Worker `php artisan queue:work --queue=notifications,pdf,payroll` |
| 4 | **Pointages jamais clôturés automatiquement** — Aucun cron de clôture automatique des pointages ouverts à minuit. Un employé oubli de pointer la sortie = journée entière comptée comme en cours | 🔴 CRITIQUE | Données paie incorrectes | Implémenter Plan 64 : `CloseOpenAttendanceCommand` en cron |
| 5 | **Timezone des pointages non normalisée** — Les pointages sont apparemment stockés sans timezone explicite. Pour un client multi-sites (DZ/MA/FR), les calculs de durée sont faux | 🔴 CRITIQUE | Paie incorrecte, conformité légale | Stocker en UTC, afficher en timezone du Site |
| 6 | **APP_DEBUG=true potentiellement en production** — Si non explicitement forcé à `false`, Laravel peut exposer des stack traces avec données sensibles (credentials, structure DB) | 🔴 CRITIQUE | Fuite de données secrets | Forcer `APP_DEBUG=false` + `APP_ENV=production` sur Render |
| 7 | **Double validation avances absente (Plan 60)** — Le workflow avance salaire n'a pas de confirmation employé après décision manager. Un manager peut approuver une avance sans que l'employé n'ait confirmé le montant et les modalités de remboursement | 🔴 CRITIQUE | Risque financier + litiges | Implémenter Plan 60 complet |
| 8 | **Aucune distribution mobile réelle** — Les liens App Store et Google Play sont tous des placeholders (`#android-employee`, `#ios-employee`). Les testeurs n'ont accès aux apps que via Firebase App Distribution | 🟠 MAJEUR | Blocage acquisition utilisateurs | Publier sur les stores ou rendre les liens Firebase publics |
| 9 | **Coverage tests non mesurée / insuffisante** — Codecov badge présent mais aucun seuil minimum défini dans la CI. Des modules entiers (paiements, paie) peuvent être sans tests | 🟠 MAJEUR | Régressions non détectées | Définir minimum 60% coverage + bloquer la CI en dessous |
| 10 | **Isolation multi-tenant non testée end-to-end** — Le TenantMiddleware existe mais aucun test E2E documenté prouvant qu'un utilisateur de tenant A ne peut pas accéder aux données de tenant B | 🟠 MAJEUR | Fuite de données clients — risque légal | Ajouter suite de tests cross-tenant dans les feature tests |
| 11 | **FCM token non invalidé à la déconnexion** — Risque que des push notifications arrivent sur des sessions déconnectées (CHANGELOG v4.16.184 mentionne le fix, mais à valider en prod) | 🟠 MAJEUR | Notifications hors session, sécurité | Valider `DELETE /api/v1/device-tokens` fonctionnel en production |
| 12 | **Plans 61-62 non implémentés** — Solde employé en temps réel et PDF bulletins de paie async sont documentés mais le code de production est absent ou partiel | 🟠 MAJEUR | Promesse produit non tenue | Implémenter Plans 61 et 62 avant tout lancement public |
| 13 | **Aucun monitoring SLA Redis** — L'absence d'alertes sur la latence ou les erreurs de connexion Redis signifie qu'une panne Upstash peut passer inaperçue pendant des heures | 🟠 MAJEUR | Dégradation silencieuse | Configurer alertes sur health check Redis + Sentry |
| 14 | **Zéro client de production documenté** — Le README liste des URLs de production mais aucune donnée réelle de tenant actif n'est référencée. Impossible de valider le comportement under load | 🟠 MAJEUR | Pas de validation terrain | Intégrer 1 PME pilote réelle dans les 30 jours |
| 15 | **Swagger/OpenAPI incomplet** — L'openapi-ci.yml existe mais la couverture réelle des endpoints n'est pas mesurée. Des partenaires/intégrateurs ne peuvent pas s'appuyer sur la doc API | 🟡 MOYEN | Blocage intégrations | Audit des endpoints non documentés + complétion |
| 16 | **RBAC insuffisamment granulaire sur la paie** — PayrollPolicy présente, mais les permissions de consultation vs modification des bulletins par les RH vs managers ne sont pas documentées clairement | 🟡 MOYEN | Accès non autorisé données salariales | Documenter la matrice RBAC complète + tests |
| 17 | **Batchage des opérations SMS/WhatsApp absent** — CommunicationService est présent mais sans provider SMS/WhatsApp réel configuré. Les notifications hors-app ne fonctionnent pas | 🟡 MOYEN | Engagement utilisateur limité | Intégrer Twilio ou Africa's Talking pour SMS |
| 18 | **Migrations DB sans index sur les FKs critiques** — À vérifier : les tables `attendance_logs`, `salary_advances`, `absences` doivent avoir des index sur `employee_id`, `company_id`, `created_at` | 🟡 MOYEN | Requêtes lentes à l'échelle | Audit des migrations + ajout des index manquants |
| 19 | **Logs non structurés ou incomplets** — StructuredLoggingMiddleware présent mais sans corrélation `request_id` systématique sur tous les jobs et services | 🟡 MOYEN | Debug difficile en production | Standardiser les logs avec `request_id` + `tenant_id` |
| 20 | **Gestion des clés Firebase/FCM en variables** — Si `FIREBASE_CREDENTIALS` n'est pas configuré sur Render, tous les push notifications échouent silencieusement | 🟡 MOYEN | Notifications mobiles mortes | Valider la configuration Firebase sur Render + health check |

---

## Résumé par criticité

| Criticité | Nombre | Action |
|---|---|---|
| 🔴 CRITIQUE (bloquant prod) | 7 | À corriger avant tout lancement |
| 🟠 MAJEUR (bloquant bêta) | 7 | À corriger dans les 30 jours |
| 🟡 MOYEN (amélioration) | 6 | À corriger dans les 60 jours |
