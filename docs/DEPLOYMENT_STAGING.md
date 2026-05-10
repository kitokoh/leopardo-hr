# Déploiement Staging - Guide Complet

## Vue d'ensemble

L'environnement staging est un environnement de pré-production qui reflète exactement la configuration de production. Il est utilisé pour tester les nouvelles fonctionnalités, les intégrations et les performances avant le déploiement en production.

**URL Staging**: https://staging.leopardo.com

## Configuration Vercel Staging

### 1. Créer le projet Vercel

```bash
# Installer Vercel CLI
npm i -g vercel

# Se connecter à Vercel
vercel login

# Créer le projet staging
cd web
vercel --prod --name leopardo-staging
```

### 2. Configurer les variables d'environnement

Dans le dashboard Vercel (https://vercel.com/dashboard):

1. Aller à **Settings** → **Environment Variables**
2. Ajouter les variables suivantes:

```
NEXT_PUBLIC_API_URL=https://api-staging.leopardo.com/api/v1
NEXT_PUBLIC_GA_ID=G-STAGING123456
NEXT_PUBLIC_MIXPANEL_TOKEN=staging_token_xyz123
SENDGRID_API_KEY=<votre_clé_sendgrid>
NEXT_PUBLIC_SITE_URL=https://staging.leopardo.com
NEXT_PUBLIC_SITE_NAME=Leopardo Staging
NEXT_PUBLIC_ENVIRONMENT=staging
NEXT_PUBLIC_SHOW_STAGING_BANNER=true
DATABASE_URL=<votre_url_db_staging>
NEXTAUTH_SECRET=<votre_secret_nextauth>
NEXTAUTH_URL=https://staging.leopardo.com
NEXT_PUBLIC_SENTRY_DSN=<votre_sentry_dsn>
```

### 3. Configurer le domaine personnalisé

1. Dans Vercel Dashboard → **Domains**
2. Ajouter le domaine `staging.leopardo.com`
3. Configurer les DNS records:

```
Type: CNAME
Name: staging
Value: cname.vercel-dns.com
```

### 4. Configurer SSL/TLS

Vercel configure automatiquement SSL/TLS avec Let's Encrypt. Vérifier que:
- ✅ HTTPS est activé
- ✅ Certificat est valide
- ✅ Redirection HTTP → HTTPS est active

## Déploiement Automatique

### Via GitHub Actions

Les déploiements staging sont automatiques lors d'un push sur la branche `staging`:

```bash
# Créer une branche staging
git checkout -b staging

# Faire des changements
git add .
git commit -m "feat: nouvelle fonctionnalité"

# Pousser vers staging
git push origin staging
```

Le workflow GitHub Actions va:
1. ✅ Exécuter les tests
2. ✅ Vérifier le linting
3. ✅ Builder le projet
4. ✅ Déployer sur Vercel staging
5. ✅ Commenter la PR avec l'URL

### Déploiement Manuel

```bash
# Depuis la branche staging
vercel --prod --env-file=.env.staging
```

## Tests Staging

### 1. Tester les formulaires

- [ ] Signup form fonctionne
- [ ] Demo request form fonctionne
- [ ] Contact form fonctionne
- [ ] Newsletter signup fonctionne
- [ ] Emails de confirmation reçus

### 2. Tester les intégrations

- [ ] Google Analytics tracking fonctionne
- [ ] Mixpanel events sont envoyés
- [ ] SendGrid emails sont envoyés
- [ ] API calls vers backend staging fonctionnent

### 3. Tester la performance

```bash
# Lighthouse audit
npm run test:lighthouse

# Vérifier les Core Web Vitals
# - LCP < 2.5s
# - FID < 100ms
# - CLS < 0.1
```

### 4. Tester l'accessibilité

```bash
# Tests d'accessibilité
npm run test:a11y

# Vérifier:
# - Contraste des couleurs
# - Navigation au clavier
# - Alt text sur images
# - Labels sur formulaires
```

### 5. Tester la sécurité

- [ ] HTTPS activé
- [ ] Headers de sécurité présents
- [ ] CSRF protection active
- [ ] Input sanitization fonctionne
- [ ] Rate limiting fonctionne

## Monitoring Staging

### Vercel Analytics

1. Aller à **Analytics** dans Vercel Dashboard
2. Vérifier:
   - Page load time
   - Core Web Vitals
   - Erreurs et exceptions

### Google Analytics

1. Aller à https://analytics.google.com
2. Sélectionner la propriété staging
3. Vérifier:
   - Page views
   - Conversion events
   - User behavior

### Sentry (Error Tracking)

1. Aller à https://sentry.io
2. Sélectionner le projet staging
3. Vérifier:
   - Erreurs JavaScript
   - Erreurs API
   - Performance issues

## Checklist Pré-Production

Avant de déployer en production, vérifier:

### Fonctionnalités
- [ ] Toutes les pages chargent correctement
- [ ] Tous les formulaires fonctionnent
- [ ] Tous les liens internes fonctionnent
- [ ] Tous les liens externes fonctionnent
- [ ] Dark mode fonctionne
- [ ] Responsive design fonctionne

### Performance
- [ ] Lighthouse score > 90
- [ ] Page load time < 2s
- [ ] Core Web Vitals pass
- [ ] Images optimisées
- [ ] Code splitting fonctionne

### SEO
- [ ] Metadata présente sur toutes les pages
- [ ] Sitemap.xml valide
- [ ] Robots.txt correct
- [ ] Structured data valide
- [ ] Canonical URLs correctes

### Sécurité
- [ ] HTTPS activé
- [ ] Headers de sécurité présents
- [ ] CSRF protection active
- [ ] Input sanitization fonctionne
- [ ] Rate limiting fonctionne

### Analytics
- [ ] Google Analytics tracking fonctionne
- [ ] Mixpanel events envoyés
- [ ] Conversion events trackés
- [ ] Scroll depth trackée

### Accessibilité
- [ ] WCAG 2.1 AA compliant
- [ ] Keyboard navigation fonctionne
- [ ] Screen reader compatible
- [ ] Color contrast OK
- [ ] Focus indicators visibles

## Rollback

Si un problème est détecté en staging:

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

# Pousser vers staging
git push origin staging
```

## Logs et Debugging

### Vercel Logs

```bash
# Voir les logs de build
vercel logs --follow

# Voir les logs de runtime
vercel logs --follow --tail
```

### Sentry Logs

1. Aller à https://sentry.io
2. Sélectionner le projet staging
3. Voir les erreurs et stack traces

### Google Analytics

1. Aller à https://analytics.google.com
2. Vérifier les événements et comportements

## Ressources

- [Vercel Documentation](https://vercel.com/docs)
- [Next.js Deployment](https://nextjs.org/docs/deployment)
- [Environment Variables](https://vercel.com/docs/concepts/projects/environment-variables)
- [Custom Domains](https://vercel.com/docs/concepts/projects/domains)
- [Analytics](https://vercel.com/docs/concepts/analytics)

## Support

Pour les problèmes de déploiement:
1. Vérifier les logs Vercel
2. Vérifier les variables d'environnement
3. Vérifier les secrets GitHub
4. Contacter le support Vercel

---

**Dernière mise à jour**: 2024
**Responsable**: DevOps Team
