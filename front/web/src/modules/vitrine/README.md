# Vitrine Module - Restructuration

Module de vitrine pour la plateforme Leopardo. Plateforme multi-pages optimisée pour la conversion avec Next.js 16, React 18, TypeScript, Tailwind CSS, Framer Motion et GSAP.

## 📋 Structure

```
vitrine/
├── app/                    # Pages Next.js (App Router)
├── components/             # Composants réutilisables
│   ├── layout/            # Navbar, Footer, MainLayout
│   ├── sections/          # Sections de page (Hero, Features, etc.)
│   ├── cards/             # Cartes réutilisables
│   ├── forms/             # Formulaires
│   ├── animations/        # Composants d'animation
│   └── common/            # Composants communs (Button, Badge, etc.)
├── lib/                    # Utilitaires et configurations
│   ├── animations.ts      # Configurations Framer Motion et GSAP
│   ├── analytics.ts       # Google Analytics 4 et Mixpanel
│   ├── seo.ts            # Metadata SEO et structured data
│   ├── validation.ts      # Schémas Zod pour formulaires
│   ├── forms.ts          # Gestion des soumissions de formulaires
│   ├── env.ts            # Validation des variables d'environnement
│   ├── constants.ts      # Constantes (navigation, pricing, etc.)
│   ├── utils.ts          # Fonctions utilitaires
│   └── index.ts          # Exports
├── hooks/                  # Hooks personnalisés
├── styles/                 # Fichiers CSS
│   ├── globals.css        # Styles globaux et Tailwind
│   └── animations.css     # Animations CSS
├── public/                 # Assets publics
│   ├── images/            # Images
│   └── icons/             # Icônes
└── types/                  # Types TypeScript
```

## 🚀 Phase 1: Setup & Infrastructure (Complétée)

### Tâches Complétées

- ✅ **1.1** Créer structure de dossiers et configuration Next.js
  - Structure complète créée
  - `next.config.ts` configuré avec optimisations
  - `tsconfig.json` mis à jour avec path aliases

- ✅ **1.2** Setup Tailwind CSS et système de design
  - `tailwind.config.ts` créé avec palette de couleurs (Emerald, Cyan, Slate)
  - `globals.css` avec styles de base et utilities
  - `animations.css` avec animations réutilisables
  - Dark mode configuré

- ✅ **1.3** Setup animations avec Framer Motion et GSAP
  - `lib/animations.ts` créé avec variants Framer Motion
  - Configurations GSAP ScrollTrigger
  - Presets d'easing et transitions

- ✅ **1.4** Setup SEO et metadata
  - `lib/seo.ts` créé avec générateurs de metadata
  - Structured data (JSON-LD) pour Organization, Product, FAQ, Review
  - Sitemap et robots.txt helpers

- ✅ **1.5** Setup analytics et conversion tracking
  - `lib/analytics.ts` avec Google Analytics 4 et Mixpanel
  - Tracking d'événements de conversion
  - Scroll depth et time on page tracking

- ✅ **1.6** Setup formulaires et validation
  - `lib/validation.ts` avec schémas Zod
  - `lib/forms.ts` avec handlers de soumission
  - Rate limiting et sanitization

- ✅ **1.7** Setup environnement et variables
  - `.env.example` complété avec toutes les variables
  - `.env.local` créé pour développement
  - `lib/env.ts` avec validation des variables

## 📦 Dépendances

```json
{
  "next": "16.2.4",
  "react": "19.2.4",
  "react-dom": "19.2.4",
  "typescript": "^5",
  "tailwindcss": "^4",
  "framer-motion": "^12.38.0",
  "gsap": "^3.15.0",
  "lucide-react": "^1.14.0",
  "react-hook-form": "^7.48.0",
  "zod": "^3.22.0"
}
```

## 🎨 Design System

### Palette de Couleurs

- **Primaire**: Emerald (500: #10b981)
- **Secondaire**: Cyan (500: #06b6d4)
- **Neutres**: Slate (50-950)

### Typographie

- **Headings**: Inter (900, 800, 700)
- **Body**: Inter (400, 500, 600)
- **Mono**: JetBrains Mono

### Spacing

- xs: 0.25rem (4px)
- sm: 0.5rem (8px)
- md: 1rem (16px)
- lg: 1.5rem (24px)
- xl: 2rem (32px)
- 2xl: 3rem (48px)
- 3xl: 4rem (64px)
- 4xl: 6rem (96px)

## 🔧 Configuration

### Variables d'Environnement

```env
# API
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1

# Analytics
NEXT_PUBLIC_GA_ID=
NEXT_PUBLIC_MIXPANEL_TOKEN=

# Forms & Email
NEXT_PUBLIC_FORM_ENDPOINT=
SENDGRID_API_KEY=

# SEO
NEXT_PUBLIC_SITE_URL=http://localhost:3000
NEXT_PUBLIC_SITE_NAME=Leopardo

# Feature Flags
NEXT_PUBLIC_ENABLE_ANALYTICS=true
NEXT_PUBLIC_ENABLE_FORMS=true
NEXT_PUBLIC_ENABLE_BLOG=true
```

## 📝 Utilisation

### Importer depuis la vitrine

```typescript
// Animations
import { pageVariants, fadeInUpVariants } from "@/vitrine/lib/animations";

// Analytics
import { getAnalytics } from "@/vitrine/lib/analytics";

// SEO
import { generateMetadata, pageMetadata } from "@/vitrine/lib/seo";

// Validation
import { signupFormSchema } from "@/vitrine/lib/validation";

// Forms
import { submitSignupForm } from "@/vitrine/lib/forms";

// Utils
import { cn, formatCurrency, truncate } from "@/vitrine/lib/utils";

// Constants
import { navigationItems, pricingPlans } from "@/vitrine/lib/constants";
```

### Utiliser les animations

```typescript
import { motion } from "framer-motion";
import { pageVariants, fadeInUpVariants } from "@/vitrine/lib/animations";

export function MyComponent() {
  return (
    <motion.div
      initial="initial"
      animate="animate"
      exit="exit"
      variants={pageVariants}
    >
      <motion.h1 variants={fadeInUpVariants}>
        Titre
      </motion.h1>
    </motion.div>
  );
}
```

### Tracker des événements

```typescript
import { getAnalytics } from "@/vitrine/lib/analytics";

const analytics = getAnalytics();

// Page view
analytics.trackPageView("/employes", "Gestion Employés");

// Conversion
analytics.trackConversion("signup", { plan: "business" });

// CTA click
analytics.trackCTAClick("Essai gratuit", "/employes", "hero");
```

### Valider des formulaires

```typescript
import { signupFormSchema } from "@/vitrine/lib/validation";

const data = {
  email: "user@example.com",
  password: "SecurePass123!",
  confirmPassword: "SecurePass123!",
  agreeToTerms: true,
};

const result = signupFormSchema.safeParse(data);

if (result.success) {
  console.log("Données valides:", result.data);
} else {
  console.log("Erreurs:", result.error.errors);
}
```

## 🎯 Prochaines Étapes

### Phase 2: Design System & Composants de Base (10 tasks)
- Créer composants de base (Button, Card, Badge)
- Créer composants de formulaire (Input, Select, Textarea)
- Créer composants de layout (Navbar, Footer)
- Créer composants d'animations
- Créer hooks personnalisés

### Phase 3: Composants Réutilisables (8 tasks)
- HeroSection
- FeatureCard et FeaturesSection
- PricingCard et PricingSection
- TestimonialCard et TestimonialsSection
- CaseStudyCard et CaseStudiesSection
- FAQSection
- CTASection
- BlogCard et BlogGrid

### Phase 4: Pages Principales (8 tasks)
- Landing Page (/)
- Page Gestion Employés (/employes)
- Page Gestion Documents (/documents)
- Page Comptabilité & Paie (/comptabilite)
- Page Marketing Digital (/marketing)
- Page Pricing (/pricing)
- Page À Propos (/about)
- Page Blog (/blog et /blog/[slug])

## 📊 Objectifs

- **Conversion**: > 8% sur landing, > 6% sur modules
- **Performance**: < 2 secondes, Lighthouse > 90
- **Accessibilité**: WCAG 2.1 AA
- **SEO**: Top 10 pour 20+ mots-clés

## 📚 Documentation

- [Requirements](../../.kiro/specs/vitrine-restructure/requirements.md)
- [Design](../../.kiro/specs/vitrine-restructure/design.md)
- [Tasks](../../.kiro/specs/vitrine-restructure/tasks.md)

## 🤝 Contribution

Suivez les conventions de code:
- TypeScript strict mode
- Composants fonctionnels avec hooks
- Nommage: PascalCase pour composants, camelCase pour fonctions
- Styles: Tailwind CSS + CSS modules pour styles complexes

## 📄 License

Propriétaire - Leopardo
