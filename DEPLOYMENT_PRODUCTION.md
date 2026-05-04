# Déploiement Production - Guide Complet

## Vue d'ensemble

L'environnement production est l'environnement public où les utilisateurs finaux accèdent à la vitrine. Les déploiements en production doivent être soigneusement planifiés et testés.

**URL Production**: https://leopardo.com

## Configuration Vercel Production

### 1. Créer le projet Vercel Production

```bash
# Installer Vercel CLI
npm i -g vercel

# Se connecter à Vercel
vercel login

# Créer le projet production
cd web
vercel --prod --name leopardo
```

### 2. Configurer les variables d'environnement

Dans le dashboard Vercel (https://vercel.com/dashboard):

1. Aller à **Settings** → **Environment Variables**
2. Ajouter les variables suivantes:

```
NEXT_PUBLIC_API_URL=https://api.leopardo.com/api/v1
NEXT_PUBLIC_GA_ID=G-PRODUCTION123456
NEXT_PUBLIC_MIXPANEL_TOKEN=production_token_abc789
SENDGRID_API_KEY=<votre_clé_sendgrid_production>
NEXT_PUBLIC_SITE_URL=https://leopardo.com
NEXT_PUBLIC_SITE_NAME=Leopardo
NEXT_PUBLIC_ENVIRONMENT=production
NEXT_PUBLIC_SHOW_STAGING_BANNER=false
DATABASE_URL=<votre_url_db_production>
NEXTAUTH_SECRET=<votre_secret_nextauth_production>
NEXTAUTH_URL=https://leopardo.com
NEXT_PUBLIC_SENTRY_DSN=<votre_sentry_dsn_production>
NEXT_PUBLIC_ENABLE_ERROR_TRACKING=true
NEXT_PUBLIC_ENABLE_PERFORMANCE_MONITORING=true
```

### 3. Configurer le domaine personnalisé

#### Option A: Domaine principal (leopardo.com)

1. Dans Vercel Dashboard → **Domains**
2. Ajouter le domaine `leopardo.com`
3. Configurer les DNS records:

```
Type: A
Name: @
Value: 76.76.19.165

Type: AAAA
Name: @
Value: 2606:4700:4700::1111
```

Ou utiliser CNAME:

```
Type: CNAME
Name: @
Value: cname.vercel-dns.com
```

#### Option B: Sous-domaine (www.leopardo.com)

```
Type: CNAME
Name: www
Value: cname.vercel-dns.com
```

### 4. Configurer SSL/TLS

Vercel configure automatiquement SSL/TLS avec Let's Encrypt. Vérifier que:
- ✅ HTTPS est activé
- ✅ Certificat est valide
- ✅ Redirection HTTP → HTTPS est active
- ✅ HSTS header est configuré

### 5. Configurer les redirects

Dans `vercel.json`, ajouter les redirects:

```json
{
  "redirects": [
    {
      "source": "/old-page",
      "destination": "/new-page",
      "permanent": true
    },
    {
      "source": "/blog/old-slug",
      "destination": "/blog/new-slug",
      "permanent": true
    }
  ]
}
```

## Déploiement Automatique

### Via GitHub Actions

Les déploiements production sont automatiques lors d'un push sur la branche `main`:

```bash
# Créer une PR depuis staging vers main
git checkout main
git pull origin main

# Merger la PR
# (via GitHub interface)

# Le workflow GitHub Actions va:
# 1. ✅ Exécuter les tests
# 2. ✅ Vérifier le linting
# 3. ✅ Builder le projet
# 4. ✅ Déployer sur Vercel production
# 5. ✅ Notifier le déploiement
```

### Déploiement Manuel (Urgence)

```bash
# Depuis la branche main
vercel --prod --env-file=.env.production
```

## Checklist Pré-Déploiement Production

### 1. Code Review
- [ ] Tous les changements ont été reviewés
- [ ] Pas de console.log en production
- [ ] Pas de code commenté
- [ ] Pas de secrets en dur

### 2. Tests
- [ ] Tous les tests unitaires passent
- [ ] Tous les tests E2E passent
- [ ] Tous les tests d'accessibilité passent
- [ ] Lighthouse score > 90

### 3. Staging Validation
- [ ] Déploiement staging réussi
- [ ] Tous les tests staging passent
- [ ] Performance OK en staging
- [ ] Pas d'erreurs en staging

### 4. Fonctionnalités
- [ ] Toutes les pages chargent correctement
- [ ] Tous les formulaires fonctionnent
- [ ] Tous les liens internes fonctionnent
- [ ] Tous les liens externes fonctionnent
- [ ] Dark mode fonctionne
- [ ] Responsive design fonctionne

### 5. Performance
- [ ] Lighthouse score > 90
- [ ] Page load time < 2s
- [ ] Core Web Vitals pass
- [ ] Images optimisées
- [ ] Code splitting fonctionne

### 6. SEO
- [ ] Metadata présente sur toutes les pages
- [ ] Sitemap.xml valide
- [ ] Robots.txt correct
- [ ] Structured data valide
- [ ] Canonical URLs correctes

### 7. Sécurité
- [ ] HTTPS activé
- [ ] Headers de sécurité présents
- [ ] CSRF protection active
- [ ] Input sanitization fonctionne
- [ ] Rate limiting fonctionne
- [ ] Pas de vulnérabilités connues

### 8. Analytics
- [ ] Google Analytics configuré
- [ ] Mixpanel configuré
- [ ] Conversion tracking configuré
- [ ] Sentry configuré

### 9. Accessibilité
- [ ] WCAG 2.1 AA compliant
- [ ] Keyboard navigation fonctionne
- [ ] Screen reader compatible
- [ ] Color contrast OK
- [ ] Focus indicators visibles

### 10. Monitoring
- [ ] Alertes Vercel configurées
- [ ] Alertes Sentry configurées
- [ ] Alertes Google Analytics configurées
- [ ] Logs centralisés configurés

## Processus de Déploiement

### Étape 1: Préparation (1-2 jours avant)

```bash
# Créer une branche de release
git checkout -b release/v1.0.0

# Mettre à jour la version
npm version minor

# Mettre à jour le CHANGELOG
# Ajouter les changements depuis la dernière release

# Committer les changements
git add .
git commit -m "chore: release v1.0.0"

# Pousser la branche
git push origin release/v1.0.0
```

### Étape 2: Staging (1 jour avant)

```bash
# Créer une PR vers staging
# Vérifier que tous les tests passent
# Vérifier que le déploiement staging réussit

# Tester en staging:
# - Toutes les fonctionnalités
# - Performance
# - Sécurité
# - Analytics
```

### Étape 3: Production (Jour du déploiement)

```bash
# Créer une PR vers main
# Vérifier que tous les tests passent
# Vérifier que le déploiement production réussit

# Après le déploiement:
# - Vérifier que le site est accessible
# - Vérifier les logs
# - Vérifier les métriques
# - Vérifier les alertes
```

### Étape 4: Post-Déploiement (Après le déploiement)

```bash
# Vérifier les métriques:
# - Page load time
# - Core Web Vitals
# - Erreurs
# - Conversion rate

# Vérifier les logs:
# - Erreurs JavaScript
# - Erreurs API
# - Erreurs serveur

# Vérifier les alertes:
# - Sentry
# - Vercel
# - Google Analytics
```

## Monitoring Production

### Vercel Analytics

1. Aller à **Analytics** dans Vercel Dashboard
2. Vérifier:
   - Page load time
   - Core Web Vitals
   - Erreurs et exceptions
   - Uptime

### Google Analytics

1. Aller à https://analytics.google.com
2. Sélectionner la propriété production
3. Vérifier:
   - Page views
   - Conversion events
   - User behavior
   - Traffic sources

### Mixpanel

1. Aller à https://mixpanel.com
2. Sélectionner le projet production
3. Vérifier:
   - User events
   - Conversion funnel
   - User retention

### Sentry (Error Tracking)

1. Aller à https://sentry.io
2. Sélectionner le projet production
3. Vérifier:
   - Erreurs JavaScript
   - Erreurs API
   - Performance issues

### Uptime Monitoring

Configurer un service de monitoring d'uptime:

```bash
# Option 1: Vercel Monitoring
# Inclus dans Vercel Pro

# Option 2: UptimeRobot
# https://uptimerobot.com

# Option 3: Pingdom
# https://www.pingdom.com
```

## Alertes Production

### Configurer les alertes Vercel

1. Aller à **Settings** → **Alerts**
2. Configurer les alertes pour:
   - Build failures
   - Deployment failures
   - High error rate
   - Performance degradation

### Configurer les alertes Sentry

1. Aller à **Alerts** dans Sentry
2. Configurer les alertes pour:
   - New issues
   - Regression
   - High error rate

### Configurer les alertes Google Analytics

1. Aller à **Alerts** dans Google Analytics
2. Configurer les alertes pour:
   - Traffic spike/drop
   - Conversion rate change
   - Bounce rate change

## Rollback Production

Si un problème critique est détecté:

### Option 1: Redéployer la version précédente

```bash
# Voir l'historique des déploiements
vercel list

# Redéployer une version précédente
vercel rollback
```

### Option 2: Revert le commit

```bash
# Revert le dernier commit
git revert HEAD

# Pousser vers main
git push origin main

# Le workflow GitHub Actions va redéployer
```

### Option 3: Hotfix

```bash
# Créer une branche hotfix
git checkout -b hotfix/critical-bug

# Fixer le bug
# Committer les changements
git add .
git commit -m "fix: critical bug"

# Créer une PR vers main
# Merger la PR
# Le workflow GitHub Actions va redéployer
```

## Logs et Debugging

### Vercel Logs

```bash
# Voir les logs de build
vercel logs --follow

# Voir les logs de runtime
vercel logs --follow --tail

# Voir les logs d'une fonction spécifique
vercel logs --follow --function=api/forms
```

### Sentry Logs

1. Aller à https://sentry.io
2. Sélectionner le projet production
3. Voir les erreurs et stack traces

### Google Analytics

1. Aller à https://analytics.google.com
2. Vérifier les événements et comportements

### Logs Centralisés

Configurer un service de logs centralisés:

```bash
# Option 1: Vercel Logs
# Inclus dans Vercel

# Option 2: Datadog
# https://www.datadoghq.com

# Option 3: LogRocket
# https://logrocket.com
```

## Performance Optimization

### Caching Strategy

```
Static assets (images, CSS, JS):
- Cache-Control: public, max-age=31536000, immutable

HTML pages:
- Cache-Control: public, max-age=3600, s-maxage=86400

API responses:
- Cache-Control: no-cache, no-store, must-revalidate
```

### CDN Configuration

Vercel utilise automatiquement un CDN global. Vérifier que:
- ✅ Images sont servies depuis le CDN
- ✅ Assets statiques sont cachés
- ✅ Compression gzip/brotli est activée

### Image Optimization

```typescript
// Utiliser Next.js Image component
<Image
  src="/hero.jpg"
  alt="Hero"
  width={1200}
  height={600}
  priority={true}
  placeholder="blur"
/>
```

## Sécurité Production

### Headers de Sécurité

Vérifier que les headers suivants sont présents:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

### HTTPS/TLS

- ✅ HTTPS activé sur tous les endpoints
- ✅ Certificat SSL/TLS valide
- ✅ TLS 1.2+ obligatoire
- ✅ Redirection HTTP → HTTPS

### CSRF Protection

- ✅ CSRF tokens sur tous les formulaires
- ✅ SameSite cookies configurés
- ✅ Origin validation

### Input Sanitization

- ✅ Tous les inputs sont validés
- ✅ Tous les inputs sont sanitizés
- ✅ XSS protection active

### Rate Limiting

- ✅ Rate limiting sur les formulaires
- ✅ Rate limiting sur les API
- ✅ DDoS protection active

## Ressources

- [Vercel Documentation](https://vercel.com/docs)
- [Next.js Deployment](https://nextjs.org/docs/deployment)
- [Environment Variables](https://vercel.com/docs/concepts/projects/environment-variables)
- [Custom Domains](https://vercel.com/docs/concepts/projects/domains)
- [Analytics](https://vercel.com/docs/concepts/analytics)
- [Security Best Practices](https://vercel.com/docs/concepts/security)

## Support

Pour les problèmes de déploiement production:
1. Vérifier les logs Vercel
2. Vérifier les variables d'environnement
3. Vérifier les secrets GitHub
4. Contacter le support Vercel (priorité haute)

---

**Dernière mise à jour**: 2024
**Responsable**: DevOps Team
**Escalade**: CTO
