# Phase 3: Composants Réutilisables

Composants réutilisables pour la vitrine de conversion optimisée. Tous les composants sont construits avec React 18, TypeScript, Tailwind CSS et Framer Motion.

## 📦 Composants Disponibles

### Sections Principales

1. **HeroSection** - Section héro avec headline, CTA et statistiques animées
2. **FeaturesSection** - Grille de features avec cartes animées
3. **PricingSection** - Section tarification avec toggle mensuel/annuel
4. **TestimonialsSection** - Grille de témoignages clients
5. **CaseStudiesSection** - Grille de cas d'usage
6. **FAQSection** - Accordion FAQ avec filtrage par catégorie
7. **CTASection** - Section appel à l'action avec 3 variantes de fond
8. **BlogGrid** - Grille d'articles avec pagination et filtres

### Cartes Individuelles

- **FeatureCard** - Carte feature réutilisable
- **PricingCard** - Carte plan tarifaire
- **TestimonialCard** - Carte témoignage
- **CaseStudyCard** - Carte cas d'usage
- **BlogCard** - Carte article blog

## 🚀 Utilisation

### Import

```tsx
import {
  HeroSection,
  FeaturesSection,
  PricingSection,
  TestimonialsSection,
  CaseStudiesSection,
  FAQSection,
  CTASection,
  BlogGrid,
} from '@/modules/vitrine/components/sections';
```

### Exemple: HeroSection

```tsx
import { HeroSection } from '@/modules/vitrine/components/sections';
import { Sparkles, TrendingUp, Users, Zap, Star } from 'lucide-react';

export default function LandingPage() {
  return (
    <HeroSection
      headline="Gérez vos RH"
      subheadline="La plateforme tout-en-un pour moderniser votre gestion du personnel."
      badge={{
        icon: <Sparkles className="w-4 h-4" />,
        text: "Leo IA 2.0 disponible",
        label: "New"
      }}
      ctaPrimary={{
        text: "Essai gratuit 14 jours",
        href: "/auth/login"
      }}
      ctaSecondary={{
        text: "Voir la démo",
        href: "#demo"
      }}
      stats={[
        {
          value: 500,
          suffix: '+',
          label: 'Entreprises',
          icon: <TrendingUp className="w-5 h-5 text-emerald-600" />
        },
        {
          value: 50,
          suffix: 'K+',
          label: 'Employés gérés',
          icon: <Users className="w-5 h-5 text-emerald-600" />
        },
        {
          value: 99,
          suffix: '.9%',
          label: 'Uptime',
          icon: <Zap className="w-5 h-5 text-emerald-600" />
        },
        {
          value: 4,
          suffix: '.9',
          label: 'Note moyenne',
          icon: <Star className="w-5 h-5 text-emerald-600" />
        }
      ]}
    />
  );
}
```

### Exemple: FeaturesSection

```tsx
import { FeaturesSection } from '@/modules/vitrine/components/sections';
import { Clock, Users, Wallet } from 'lucide-react';

export default function FeaturesPage() {
  const features = [
    {
      icon: <Clock className="w-7 h-7" />,
      title: 'Pointage Intelligent',
      description: 'Gestion ultra-précise des présences avec compatibilité ZKTeco, NFC et biométrie avancée.',
      gradient: 'from-emerald-400 to-teal-500',
      stats: { value: '99.9%', label: 'Précision' },
      details: ['Reconnaissance faciale', 'NFC / QR Code', 'Géolocalisation', 'Mode hors-ligne'],
    },
    // ... autres features
  ];

  return (
    <FeaturesSection
      title="Tout ce dont vous avez"
      subtitle="besoin"
      badge={{ text: 'Fonctionnalités' }}
      features={features}
      columns={3}
    />
  );
}
```

### Exemple: PricingSection

```tsx
import { PricingSection } from '@/modules/vitrine/components/sections';

export default function PricingPage() {
  const plans = [
    {
      name: 'Starter',
      price: 29,
      currency: 'EUR',
      period: '/mois',
      description: 'Pour les petites équipes',
      features: [
        'Jusqu\'à 10 employés',
        'Pointage basique',
        'Gestion des absences',
        'Support email',
      ],
      cta: { text: 'Commencer', href: '/signup' },
    },
    {
      name: 'Business',
      price: 79,
      currency: 'EUR',
      period: '/mois',
      description: 'Pour les PME',
      features: [
        'Jusqu\'à 100 employés',
        'Pointage avancé',
        'Paie automatisée',
        'Support prioritaire',
      ],
      cta: { text: 'Commencer', href: '/signup' },
      highlighted: true,
      badge: 'POPULAIRE',
    },
    // ... autres plans
  ];

  return (
    <PricingSection
      title="Tarification"
      subtitle="simple et transparente"
      badge={{ text: 'Plans' }}
      plans={plans}
      showToggle={true}
      toggleLabel={{ monthly: 'Mensuel', annual: 'Annuel' }}
    />
  );
}
```

### Exemple: FAQSection

```tsx
import { FAQSection } from '@/modules/vitrine/components/sections';

export default function FAQPage() {
  const faqItems = [
    {
      id: '1',
      question: 'Comment fonctionne le pointage?',
      answer: 'Notre système de pointage utilise la reconnaissance faciale, NFC ou QR codes...',
      category: 'Pointage',
    },
    {
      id: '2',
      question: 'Quel est le coût?',
      answer: 'Nos plans commencent à 29€/mois pour les petites équipes...',
      category: 'Tarification',
    },
    // ... autres questions
  ];

  return (
    <FAQSection
      title="Questions"
      subtitle="fréquemment posées"
      badge={{ text: 'FAQ' }}
      items={faqItems}
      categories={['Pointage', 'Tarification', 'Support']}
    />
  );
}
```

### Exemple: BlogGrid

```tsx
import { BlogGrid } from '@/modules/vitrine/components/sections';

export default function BlogPage() {
  const posts = [
    {
      slug: 'gestion-rh-2024',
      title: 'Les tendances RH en 2024',
      excerpt: 'Découvrez les principales tendances qui transforment la gestion RH...',
      image: '/blog/rh-2024.jpg',
      date: new Date('2024-01-15'),
      author: { name: 'Jean Dupont', avatar: '/avatars/jean.jpg' },
      category: 'Tendances',
      readingTime: 5,
    },
    // ... autres articles
  ];

  return (
    <BlogGrid
      title="Blog"
      subtitle="Ressources et insights"
      badge={{ text: 'Articles' }}
      posts={posts}
      categories={['Tendances', 'Tutoriels', 'Cas d\'usage']}
      itemsPerPage={9}
      showPagination={true}
      showFilters={true}
    />
  );
}
```

## 🎨 Design System

### Couleurs

- **Primaire:** Emerald 500 (`#10b981`)
- **Secondaire:** Cyan 400 (`#22d3ee`)
- **Neutres:** Slate (900, 600, 400)

### Typographie

- **Headings:** Inter, font-weight 900/800/700
- **Body:** Inter, font-weight 400/500/600
- **Mono:** JetBrains Mono

### Spacing

Utilise le système Tailwind standard:
- `sm`: 0.5rem (8px)
- `md`: 1rem (16px)
- `lg`: 1.5rem (24px)
- `xl`: 2rem (32px)
- `2xl`: 3rem (48px)

### Border Radius

- Cartes: `rounded-3xl` (24px)
- Boutons: `rounded-2xl` (16px)
- Petits éléments: `rounded-xl` (12px)

## ✨ Animations

Tous les composants utilisent:

- **Framer Motion** pour les animations
- **Intersection Observer** pour les animations au scroll
- **Stagger animations** pour les listes
- **Hover effects** cohérents
- **Transitions fluides** (300-600ms)

### Animations Disponibles

- `fadeIn` - Apparition progressive
- `slideUp` - Glissement vers le haut
- `scaleIn` - Zoom d'apparition
- `stagger` - Animation décalée pour les listes
- `hover` - Effets au survol

## 📱 Responsive Design

Tous les composants sont optimisés pour:

- **Mobile** (320px+)
- **Tablet** (768px+)
- **Desktop** (1024px+)
- **Large Desktop** (1280px+)

### Breakpoints Tailwind

```
sm: 640px
md: 768px
lg: 1024px
xl: 1280px
2xl: 1536px
```

## 🌙 Dark Mode

Tous les composants supportent le dark mode avec la classe `dark`:

```tsx
<div className="dark">
  <HeroSection {...props} />
</div>
```

## ♿ Accessibilité

Tous les composants respectent:

- **WCAG 2.1 AA** standards
- **Color contrast** > 4.5:1
- **Keyboard navigation** complète
- **ARIA labels** appropriés
- **Focus indicators** visibles

## 🧪 Tests

Tests d'import disponibles dans `__tests__/imports.test.ts`:

```bash
npm test -- components/sections/__tests__/imports.test.ts
```

## 📚 Props Interfaces

Toutes les props sont typées en TypeScript. Consultez les fichiers individuels pour les interfaces complètes:

- `HeroSectionProps`
- `FeatureCardProps`, `FeaturesSectionProps`
- `PricingCardProps`, `PricingSectionProps`
- `TestimonialCardProps`, `TestimonialsSectionProps`
- `CaseStudyCardProps`, `CaseStudiesSectionProps`
- `FAQSectionProps`, `FAQItem`
- `CTASectionProps`
- `BlogCardProps`, `BlogGridProps`

## 🔧 Customization

### Modifier les couleurs

Modifiez les classes Tailwind dans les composants:

```tsx
// Avant
className="bg-emerald-500"

// Après
className="bg-blue-500"
```

### Modifier les animations

Modifiez les props Framer Motion:

```tsx
initial={{ opacity: 0, y: 40 }}
animate={{ opacity: 1, y: 0 }}
transition={{ duration: 0.6 }}
```

### Modifier les layouts

Utilisez les props `columns` pour ajuster les grilles:

```tsx
<FeaturesSection columns={2} />
<PricingSection columns={2} />
```

## 📖 Documentation Complète

Voir `PHASE3_SUMMARY.md` pour la documentation complète de Phase 3.

## 🚀 Prochaines Étapes

Ces composants sont prêts pour être utilisés dans Phase 4 (Pages Principales):

1. Landing Page
2. Module Pages (Employés, Documents, etc.)
3. Pricing Page
4. Blog Pages
5. À Propos

## 💡 Tips & Tricks

### Réutiliser les données

Créez des fichiers de données centralisés:

```tsx
// data/features.ts
export const features = [
  { icon: Clock, title: '...', ... },
  // ...
];

// pages/index.tsx
import { features } from '@/data/features';
<FeaturesSection features={features} />
```

### Combiner les sections

Créez des pages complètes en combinant les sections:

```tsx
export default function LandingPage() {
  return (
    <>
      <HeroSection {...heroProps} />
      <FeaturesSection {...featuresProps} />
      <PricingSection {...pricingProps} />
      <TestimonialsSection {...testimonialsProps} />
      <FAQSection {...faqProps} />
      <CTASection {...ctaProps} />
    </>
  );
}
```

### Animations personnalisées

Créez des variantes d'animations personnalisées:

```tsx
const customVariants = {
  initial: { opacity: 0, y: 100 },
  animate: { opacity: 1, y: 0 },
  transition: { duration: 1, delay: 0.5 },
};

<motion.div variants={customVariants}>
  {/* content */}
</motion.div>
```

## 📞 Support

Pour des questions ou des problèmes, consultez:

1. Les fichiers individuels des composants
2. `PHASE3_SUMMARY.md`
3. Les exemples d'utilisation ci-dessus

---

**Phase 3 Status:** ✅ Complétée
**Prêt pour Phase 4:** ✅ Oui
