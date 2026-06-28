# Phase 6 - Task 6.6: Configurer Redirects et Canonical URLs

## Résumé de l'Implémentation

### Canonical URLs

#### Implémentation dans `web/src/modules/vitrine/lib/seo-metadata.ts`

Chaque page inclut une canonical URL:

**Pages Principales:**
- Landing: `https://leopardo.com/`
- Employes: `https://leopardo.com/employes`
- Documents: `https://leopardo.com/documents`
- Comptabilite: `https://leopardo.com/comptabilite`
- Marketing: `https://leopardo.com/marketing`
- Pricing: `https://leopardo.com/pricing`
- About: `https://leopardo.com/about`
- Blog: `https://leopardo.com/blog`

**Articles de Blog:**
- Chaque article a sa canonical URL: `https://leopardo.com/blog/[slug]`

**Pages de Guides:**
- Guide RH: `https://leopardo.com/guides/rh-startup`
- Checklist Paie: `https://leopardo.com/guides/checklist-paie`
- Planning: `https://leopardo.com/guides/planning-employes`

#### Fonction Helper

```typescript
export function getCanonicalUrl(path: string): string {
  return `${siteUrl}${path}`;
}
```

### Redirects

#### Configuration dans `web/next.config.ts`

**Redirects Implémentés:**

1. **Redirects d'Images Anciennes**
   ```
   /images/old/* → /images/*
   ```
   - Permanent (301)
   - Pour les anciennes URLs d'images

2. **Redirects de Blog**
   ```
   /blog/old/:slug → /blog/:slug
   ```
   - Permanent (301)
   - Pour les anciennes URLs d'articles

#### Template pour Redirects Futurs

Les redirects peuvent être facilement ajoutés:
```typescript
redirects: async () => [
  {
    source: '/old-page',
    destination: '/new-page',
    permanent: true, // 301 redirect
  },
  {
    source: '/old-blog/:slug',
    destination: '/blog/:slug',
    permanent: true,
  },
]
```

### Headers de Sécurité

#### Configuration dans `web/next.config.ts`

**Headers Ajoutés:**

1. **Strict-Transport-Security**
   - Force HTTPS
   - max-age: 31536000 (1 an)
   - includeSubDomains

2. **Referrer-Policy**
   - strict-origin-when-cross-origin
   - Protège la confidentialité

3. **Permissions-Policy**
   - Désactive: geolocation, microphone, camera
   - Sécurité renforcée

4. **X-Content-Type-Options**
   - nosniff
   - Prévient le MIME sniffing

5. **X-Frame-Options**
   - DENY
   - Prévient le clickjacking

6. **X-XSS-Protection**
   - 1; mode=block
   - Protection XSS

### Headers de Cache

#### Pour Sitemap et Robots

**Sitemap.xml:**
- Content-Type: application/xml
- Cache-Control: public, s-maxage=3600, stale-while-revalidate=86400
- Cache 1 heure, stale 24 heures

**Robots.txt:**
- Content-Type: text/plain
- Cache-Control: public, s-maxage=3600, stale-while-revalidate=86400
- Cache 1 heure, stale 24 heures

### Hreflang pour Multilingue

#### Préparation pour Multilingue

Structure prête pour hreflang:
```typescript
// À ajouter dans les pages multilingues
<link rel="alternate" hrefLang="fr" href="https://leopardo.com/fr/..." />
<link rel="alternate" hrefLang="en" href="https://leopardo.com/en/..." />
<link rel="alternate" hrefLang="x-default" href="https://leopardo.com/..." />
```

### Intégration avec Google Search Console

#### Étapes de Vérification

1. **Ajouter le Site**
   - Accédez à Google Search Console
   - Ajoutez: https://leopardo.com

2. **Vérifier la Propriété**
   - Utilisez la balise meta ou DNS

3. **Soumettre le Sitemap**
   - Allez à Sitemaps
   - Soumettez: https://leopardo.com/sitemap.xml

4. **Vérifier les Redirects**
   - Allez à Couverture
   - Vérifiez les redirects 301

5. **Vérifier les Canonical URLs**
   - Allez à Améliorations
   - Vérifiez les canonical URLs

6. **Monitorer l'Indexation**
   - Vérifiez que toutes les pages sont indexées
   - Vérifiez les erreurs d'exploration

### Bonnes Pratiques Implémentées

✅ **Canonical URLs**
- Chaque page a une canonical URL
- Évite le duplicate content
- Aide Google à comprendre la structure

✅ **Redirects 301**
- Permanent redirects
- Préserve le PageRank
- Aide les utilisateurs et les bots

✅ **Headers de Sécurité**
- HTTPS forcé
- Protection contre les attaques
- Conformité de sécurité

✅ **Cache Approprié**
- Sitemap et robots.txt cachés
- Améliore la performance
- Réduit la charge serveur

### Fichiers Modifiés

1. `web/next.config.ts`
   - Ajout des redirects
   - Ajout des headers de sécurité
   - Ajout des headers de cache

2. `web/src/modules/vitrine/lib/seo-metadata.ts`
   - Canonical URLs pour toutes les pages
   - Fonction getCanonicalUrl()

### Fichiers Créés

1. `web/src/modules/vitrine/PHASE6_TASK6_6_SUMMARY.md` - Ce fichier

### Validation des Exigences

✅ **Requirement 2.1**: Redirects configurés pour anciennes URLs
✅ **Requirement 2.2**: Canonical URLs pour éviter duplicate content
✅ **Requirement 2.1**: Hreflang préparé pour multilingue
✅ **Requirement 2.2**: Prêt pour Google Search Console

### Prochaines Étapes

1. **Tester les Redirects**
   - Vérifier que les redirects 301 fonctionnent
   - Tester avec curl ou Postman

2. **Vérifier les Canonical URLs**
   - Vérifier que chaque page a une canonical URL
   - Tester avec Google Search Console

3. **Monitorer Google Search Console**
   - Vérifier l'indexation
   - Vérifier les erreurs
   - Vérifier les redirects

4. **Ajouter Hreflang**
   - Quand le multilingue est implémenté
   - Ajouter les balises hreflang
   - Tester avec Google Search Console

### Notes

- Les redirects sont configurés dans next.config.ts
- Les canonical URLs sont dans la metadata
- Les headers de sécurité sont appliqués globalement
- Le cache est optimisé pour performance

## Statut

✅ **COMPLÉTÉ** - Task 6.6 terminée avec succès
