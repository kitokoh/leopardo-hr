# RUNBOOK - ALERTING

Version 4.16.73 | 2026-05-18

## 1. Sources d'alertes

| Source | Canal | Type d'alerte |
|--------|-------|---------------|
| Sentry | Email + Slack `#alerts-errors` | Exceptions PHP, JS, Flutter |
| SlackAlertNotification | Slack `#alerts-app` | Alertes applicatives (paie, contrats, queue) |
| MonitorSlowQueries | Logs structured | Requetes SQL > seuil |
| UptimeRobot | Email + Slack `#alerts-uptime` | Downtime services |
| GitHub Actions CI | Email + GitHub | Echecs CI/CD |
| OWASP ZAP | GitHub artifacts | Vulnerabilites web |

## 2. Niveaux de severite

| Severite | Temps de reaction | Exemples |
|----------|-------------------|----------|
| P1 - Critique | < 15 min | Service indisponible, fuite de donnees, perte de donnees |
| P2 - Majeur | < 1h | Feature critique cassee, erreurs 5xx > 10/min |
| P3 - Mineur | < 4h | Lenteurs, feature secondaire cassee |
| P4 - Info | Prochain sprint | Warnings, deprecations |

## 3. Procedures par type

### 3.1 Downtime API (P1)
1. Verifier `GET /api/v1/health` — identifier le composant defaillant
2. Si DB : verifier PostgreSQL, connexions, espace disque
3. Si Redis : verifier le service Redis, memoire
4. Si Queue : redemarrer les workers `php artisan queue:restart`
5. Si applicatif : consulter Sentry pour la stack trace
6. Suivre RUNBOOK_INCIDENT_P1.md pour la communication

### 3.2 Erreurs 5xx en hausse (P2)
1. Consulter Sentry — grouper par type d'erreur
2. Identifier le commit/deploy recent
3. Si regression : rollback (voir RUNBOOK_ROLLBACK.md)
4. Si charge : verifier rate limiting et scaling

### 3.3 Slow queries (P3)
1. Consulter les logs structured pour les requetes lentes
2. Identifier la table/requete concernee
3. Verifier les index avec `EXPLAIN ANALYZE`
4. Ajouter l'index manquant via migration `CREATE INDEX CONCURRENTLY`

### 3.4 Queue bloquee (P2)
1. `php artisan queue:monitor` — verifier la taille des queues
2. Si jobs en echec : `php artisan queue:retry all`
3. Si worker mort : redemarrer via superviseur/Render
4. Verifier les logs pour les exceptions recurentes

## 4. Configuration Slack

### Webhooks requis

```env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
SLACK_ALERTS_CHANNEL=#alerts-app
```

### SlackAlertNotification
Le service `SlackAlertNotification` envoie des alertes formatees :
- Titre de l'alerte
- Severite (emoji : rouge/orange/jaune)
- Details contextuels (tenant, user, endpoint)
- Timestamp

## 5. Escalade

| Etape | Delai | Action |
|-------|-------|--------|
| 1 | Alerte recue | Dev on-call acknowledge |
| 2 | +15 min sans ack | Notifier lead tech |
| 3 | +30 min sans resolution | Notifier CTO |
| 4 | +1h P1 non resolue | Communiquer aux clients (status page) |

## 6. Post-mortem

Apres chaque incident P1/P2 :
1. Creer un document dans `docs/GESTION_PROJET/postmortems/`
2. Timeline des evenements
3. Cause racine
4. Actions correctives
5. Mise a jour des runbooks si necessaire
