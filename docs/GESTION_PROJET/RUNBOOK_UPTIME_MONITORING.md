# RUNBOOK - UPTIME MONITORING

Version 4.16.73 | 2026-05-18

## 1. Objectif

Surveiller la disponibilite et la performance des services Leopardo RH avec des outils de monitoring externe. Ce runbook couvre la configuration UptimeRobot/BetterUptime et l'integration avec le health check existant.

## 2. Health Check Endpoint

Le backend expose un health check enrichi :

```
GET /api/v1/health
```

Reponse :
```json
{
  "status": "healthy",
  "db": "ok",
  "redis": "ok",
  "queue": "ok",
  "disk": "ok",
  "uptime": 12345
}
```

Cet endpoint est public (pas d'authentification) et doit retourner `200 OK` quand tous les services fonctionnent.

## 3. Configuration UptimeRobot

### 3.1 Monitors a creer

| Monitor | URL | Intervalle | Type |
|---------|-----|-----------|------|
| API Health | `https://<domain>/api/v1/health` | 5 min | HTTP(s) - Keyword `healthy` |
| API Auth | `https://<domain>/api/v1/auth/login` | 5 min | HTTP(s) - Status 422 (POST sans body) |
| Admin Dashboard | `https://<admin-domain>/` | 5 min | HTTP(s) - Status 200 |
| Vitrine Web | `https://<web-domain>/` | 5 min | HTTP(s) - Status 200 |

### 3.2 Alertes

- Configurer les alertes email vers l'equipe ops
- Configurer le webhook Slack vers `#alerts-infra`
- Seuil : alerte apres 2 echecs consecutifs (10 min de downtime)

### 3.3 Status Page

UptimeRobot peut generer une status page publique :
- URL recommandee : `https://status.<domain>/`
- Afficher : API, Dashboard Admin, Vitrine, Mobile API

## 4. Alternative : BetterUptime

Si BetterUptime est prefere :
1. Creer un monitor HTTP pour chaque endpoint ci-dessus
2. Configurer les incidents automatiques
3. Activer la status page integree
4. Integrer avec Slack/email pour les on-call

## 5. Integration Slack

Le `SlackAlertNotification` existant envoie les alertes applicatives. Pour les alertes uptime :

1. Creer un webhook Slack dedie dans `#alerts-uptime`
2. Configurer UptimeRobot/BetterUptime pour poster sur ce webhook
3. Format recommande : nom du monitor + duree du downtime + lien status page

## 6. Metriques a surveiller

| Metrique | Seuil alerte | Source |
|----------|-------------|--------|
| Temps reponse API | > 2s | UptimeRobot |
| Disponibilite | < 99.9% | UptimeRobot |
| Erreurs 5xx | > 5/heure | Sentry |
| Queue jobs en attente | > 100 | Health check |
| Espace disque | < 20% | Health check |

## 7. Verification

```bash
# Tester le health check manuellement
curl -s https://<domain>/api/v1/health | jq .

# Verifier le status UptimeRobot
# -> Dashboard UptimeRobot ou API UptimeRobot
```
