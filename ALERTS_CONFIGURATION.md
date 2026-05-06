# Configuration des Alertes - Guide Complet

## Vue d'ensemble

Les alertes sont configurées pour notifier l'équipe des problèmes critiques, des dégradations de performance et des anomalies de conversion.

## Alertes Critiques

### 1. Taux d'erreur élevé

**Condition**: Taux d'erreur > 5% sur 5 minutes

**Seuil**: 
- Warning: 3%
- Critical: 5%

**Actions**:
- Email: team@leopardo.com
- Slack: #monitoring
- PagerDuty: Escalade après 15 minutes

**Exemple**:
```
🚨 CRITICAL: High Error Rate
Taux d'erreur: 7.2%
Durée: 5 minutes
Erreurs: 142
Utilisateurs affectés: 23

Actions:
1. Vérifier les logs Sentry
2. Vérifier les logs Vercel
3. Vérifier le statut du backend
```

### 2. Performance dégradée

**Condition**: LCP > 2.5s sur 10 minutes

**Seuil**:
- Warning: 2.0s
- Critical: 2.5s

**Actions**:
- Email: team@leopardo.com
- Slack: #monitoring

**Exemple**:
```
⚠️ WARNING: Performance Degradation
LCP: 2.3s (cible: 2.5s)
Durée: 10 minutes
Pages affectées: 3

Actions:
1. Vérifier les métriques Vercel
2. Vérifier le bundle size
3. Vérifier les images
```

### 3. Déploiement échoué

**Condition**: Build ou déploiement échoue

**Seuil**: Immédiat

**Actions**:
- Email: team@leopardo.com
- Slack: #deployments
- PagerDuty: Escalade après 30 minutes

**Exemple**:
```
🚨 CRITICAL: Deployment Failed
Branche: main
Erreur: Build failed - TypeScript error
Détails: Type 'string' is not assignable to type 'number'

Actions:
1. Vérifier les logs GitHub Actions
2. Fixer l'erreur
3. Redéployer
```

### 4. Uptime dégradé

**Condition**: Uptime < 99% sur 1 heure

**Seuil**:
- Warning: 99.5%
- Critical: 99%

**Actions**:
- Email: team@leopardo.com
- Slack: #monitoring
- PagerDuty: Escalade après 5 minutes

**Exemple**:
```
🚨 CRITICAL: Uptime Degraded
Uptime: 98.5%
Downtime: 27 secondes
Utilisateurs affectés: 150

Actions:
1. Vérifier le statut Vercel
2. Vérifier les logs
3. Contacter le support Vercel
```

## Alertes Importantes

### 5. Taux de conversion faible

**Condition**: Conversion rate < 5% sur 1 heure

**Seuil**:
- Warning: 6%
- Critical: 5%

**Actions**:
- Email: team@leopardo.com, marketing@leopardo.com
- Slack: #analytics

**Exemple**:
```
⚠️ WARNING: Low Conversion Rate
Taux: 4.2% (cible: 8%)
Durée: 1 heure
Conversions: 12
Visiteurs: 285

Actions:
1. Vérifier les formulaires
2. Vérifier les CTAs
3. Vérifier les erreurs JavaScript
```

### 6. Taux de rebond élevé

**Condition**: Bounce rate > 50% sur 1 heure

**Seuil**:
- Warning: 45%
- Critical: 50%

**Actions**:
- Email: team@leopardo.com, marketing@leopardo.com
- Slack: #analytics

**Exemple**:
```
⚠️ WARNING: High Bounce Rate
Taux: 52% (cible: < 40%)
Durée: 1 heure
Sessions: 145
Rebonds: 75

Pages affectées:
- /blog: 65%
- /pricing: 48%
- /about: 42%

Actions:
1. Analyser le contenu
2. Améliorer les CTAs
3. Vérifier les erreurs
```

### 7. Erreurs de formulaire

**Condition**: Taux d'erreur de formulaire > 10%

**Seuil**:
- Warning: 5%
- Critical: 10%

**Actions**:
- Email: team@leopardo.com
- Slack: #monitoring

**Exemple**:
```
⚠️ WARNING: Form Submission Errors
Taux d'erreur: 8.5%
Formulaires affectés: signup, demo_request
Erreurs: 17
Tentatives: 200

Erreurs les plus fréquentes:
1. Email already exists (45%)
2. Network error (30%)
3. Validation error (25%)

Actions:
1. Vérifier les logs backend
2. Vérifier la validation
3. Vérifier la connectivité réseau
```

### 8. Problèmes d'indexation SEO

**Condition**: Erreurs d'indexation détectées

**Seuil**: Immédiat

**Actions**:
- Email: team@leopardo.com, seo@leopardo.com
- Slack: #seo

**Exemple**:
```
⚠️ WARNING: Indexation Issues
Erreurs détectées: 5
Pages affectées: 3

Erreurs:
1. Soft 404 - /blog/old-post
2. Redirect error - /pricing
3. Crawl anomaly - /documents

Actions:
1. Vérifier Google Search Console
2. Fixer les redirects
3. Vérifier les robots.txt
```

## Alertes Informatives

### 9. Pics de trafic

**Condition**: Trafic > 200% de la moyenne

**Seuil**: Immédiat

**Actions**:
- Slack: #analytics

**Exemple**:
```
ℹ️ INFO: Traffic Spike
Trafic: 450% de la moyenne
Utilisateurs: 2,500 (moyenne: 500)
Durée: 30 minutes
Source: Hacker News

Actions:
1. Monitorer les performances
2. Vérifier les erreurs
3. Vérifier la conversion
```

### 10. Nouvelles erreurs

**Condition**: Nouvelle erreur détectée

**Seuil**: Immédiat

**Actions**:
- Slack: #monitoring

**Exemple**:
```
ℹ️ INFO: New Error Detected
Erreur: TypeError: Cannot read property 'map' of undefined
Occurrences: 5
Utilisateurs affectés: 3
Stack trace: [...]

Actions:
1. Vérifier le code
2. Créer une issue GitHub
3. Fixer si critique
```

## Configuration des Canaux

### Email

**Configuration**:
```
Service: SendGrid
Destinataires: team@leopardo.com
Fréquence: Immédiate pour critiques, quotidienne pour autres
Format: HTML avec détails et lien vers dashboard
```

**Template**:
```html
<h2>🚨 Alert: {{ alert.title }}</h2>
<p>{{ alert.message }}</p>
<p><strong>Sévérité:</strong> {{ alert.severity }}</p>
<p><strong>Timestamp:</strong> {{ alert.timestamp }}</p>
<p><strong>Détails:</strong></p>
<pre>{{ alert.metadata }}</pre>
<p><a href="{{ dashboard_url }}">Voir le dashboard</a></p>
```

### Slack

**Configuration**:
```
Workspace: Leopardo
Channels:
- #monitoring (alertes techniques)
- #analytics (alertes de conversion)
- #deployments (alertes de déploiement)
- #seo (alertes SEO)
```

**Template**:
```
:warning: Alert: {{ alert.title }}
{{ alert.message }}
Sévérité: {{ alert.severity }}
<{{ dashboard_url }}|Voir le dashboard>
```

### PagerDuty

**Configuration**:
```
Service: Leopardo Vitrine
Escalade: Après 15-30 minutes sans action
Oncall: DevOps team
```

**Règles d'escalade**:
```
Niveau 1: DevOps team (15 minutes)
Niveau 2: Engineering lead (30 minutes)
Niveau 3: CTO (60 minutes)
```

## Gestion des Alertes

### Créer une alerte

1. Aller au service (Sentry, Vercel, GA4, etc.)
2. Aller à **Alerts** ou **Settings**
3. Cliquer sur **Create Alert Rule**
4. Remplir les informations:
   - Nom: Descriptif et unique
   - Condition: Spécifique et mesurable
   - Seuil: Valeur numérique
   - Actions: Canaux de notification

### Modifier une alerte

1. Aller au service
2. Aller à **Alerts**
3. Sélectionner l'alerte
4. Cliquer sur **Edit**
5. Modifier les paramètres
6. Sauvegarder

### Désactiver une alerte

1. Aller au service
2. Aller à **Alerts**
3. Sélectionner l'alerte
4. Cliquer sur **Disable**

### Supprimer une alerte

1. Aller au service
2. Aller à **Alerts**
3. Sélectionner l'alerte
4. Cliquer sur **Delete**

## Répondre aux Alertes

### Processus de réponse

1. **Recevoir l'alerte**
   - Email, Slack, ou PagerDuty
   - Lire le message et les détails

2. **Évaluer la sévérité**
   - Critical: Action immédiate requise
   - Warning: Action requise dans l'heure
   - Info: Informationnel, pas d'action requise

3. **Investiguer**
   - Aller au dashboard du service
   - Vérifier les logs
   - Identifier la cause racine

4. **Agir**
   - Fixer le problème
   - Ou escalader si nécessaire

5. **Documenter**
   - Créer une issue GitHub
   - Documenter la cause et la solution
   - Mettre à jour la runbook

### Runbook pour alertes critiques

#### Alerte: High Error Rate

```
1. Vérifier les logs Sentry
   - Aller à https://sentry.io
   - Voir les erreurs les plus fréquentes
   - Identifier le pattern

2. Vérifier les logs Vercel
   - Aller à Vercel Dashboard
   - Voir les logs de runtime
   - Identifier les erreurs

3. Vérifier le backend
   - Vérifier le statut de l'API
   - Vérifier les logs du serveur
   - Vérifier la base de données

4. Agir
   - Si erreur JavaScript: Fixer le code et redéployer
   - Si erreur API: Vérifier le backend
   - Si erreur base de données: Vérifier la connexion

5. Escalader si nécessaire
   - Si pas de solution en 15 minutes
   - Contacter l'engineering lead
   - Créer une issue GitHub
```

#### Alerte: Performance Degradation

```
1. Vérifier les métriques Vercel
   - Aller à Vercel Analytics
   - Voir les Core Web Vitals
   - Identifier la page affectée

2. Vérifier le bundle size
   - Exécuter: npm run build
   - Vérifier la taille du bundle
   - Identifier les gros fichiers

3. Vérifier les images
   - Vérifier les images non optimisées
   - Vérifier les images non lazy-loaded
   - Vérifier les images trop grandes

4. Agir
   - Optimiser les images
   - Réduire le bundle size
   - Ajouter du caching

5. Redéployer
   - Committer les changements
   - Pousser vers main
   - Vérifier le déploiement
```

#### Alerte: Deployment Failed

```
1. Vérifier les logs GitHub Actions
   - Aller à GitHub Actions
   - Voir le workflow qui a échoué
   - Lire le message d'erreur

2. Identifier le problème
   - Erreur de build: TypeScript, ESLint, etc.
   - Erreur de test: Tests échoués
   - Erreur de déploiement: Vercel, variables d'env, etc.

3. Fixer le problème
   - Corriger le code
   - Committer les changements
   - Pousser vers la branche

4. Redéployer
   - Le workflow va se relancer automatiquement
   - Vérifier que le déploiement réussit

5. Vérifier le site
   - Aller à https://leopardo.com
   - Vérifier que le site fonctionne
   - Vérifier les logs
```

## Métriques d'Alerte

### Métriques de réponse

- **MTTD** (Mean Time To Detect): Temps moyen pour détecter une alerte
- **MTTR** (Mean Time To Resolve): Temps moyen pour résoudre une alerte
- **Alert Fatigue**: Nombre d'alertes par jour

### Objectifs

```
MTTD: < 5 minutes
MTTR: < 30 minutes (critical), < 2 heures (warning)
Alert Fatigue: < 10 alertes par jour
False Positive Rate: < 5%
```

## Amélioration Continue

### Révision mensuelle

1. Analyser les alertes du mois
2. Identifier les patterns
3. Ajuster les seuils si nécessaire
4. Ajouter de nouvelles alertes si nécessaire
5. Documenter les changements

### Révision trimestrielle

1. Analyser les tendances des alertes
2. Identifier les problèmes récurrents
3. Implémenter des solutions permanentes
4. Mettre à jour les runbooks
5. Former l'équipe

## Ressources

- [Sentry Alerts Documentation](https://docs.sentry.io/alerts/)
- [Vercel Alerts Documentation](https://vercel.com/docs/concepts/alerts)
- [Google Analytics Alerts](https://support.google.com/analytics/answer/1033116)
- [Slack Integrations](https://slack.com/apps)
- [PagerDuty Documentation](https://support.pagerduty.com/)

---

**Dernière mise à jour**: 2024
**Responsable**: DevOps Team
