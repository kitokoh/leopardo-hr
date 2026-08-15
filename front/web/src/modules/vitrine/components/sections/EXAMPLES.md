# Phase 3 Components - Exemples Complets

Exemples d'utilisation complète de tous les composants Phase 3.

## 📋 Table des Matières

1. [HeroSection](#herosection)
2. [FeaturesSection](#featuressection)
3. [PricingSection](#pricingsection)
4. [TestimonialsSection](#testimonialssection)
5. [CaseStudiesSection](#casestudiessection)
6. [FAQSection](#faqsection)
7. [CTASection](#ctasection)
8. [BlogGrid](#bloggrid)
9. [Page Complète](#page-complète)

---

## HeroSection

### Exemple Basique

```tsx
import { HeroSection } from '@/modules/vitrine/components/sections';
import { Sparkles } from 'lucide-react';

export default function Hero() {
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
    />
  );
}
```

### Avec Statistiques

```tsx
import { HeroSection } from '@/modules/vitrine/components/sections';
import { TrendingUp, Users, Zap, Star } from 'lucide-react';

export default function HeroWithStats() {
  return (
    <HeroSection
      headline="Gérez vos RH"
      subheadline="La plateforme tout-en-un pour moderniser votre gestion du personnel."
      ctaPrimary={{
        text: "Essai gratuit 14 jours",
        href: "/auth/login"
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

### Sans Animations

```tsx
<HeroSection
  headline="Gérez vos RH"
  subheadline="La plateforme tout-en-un..."
  ctaPrimary={{ text: "Essai gratuit", href: "/auth/login" }}
  animated={false}
/>
```

---

## FeaturesSection

### Exemple Basique

```tsx
import { FeaturesSection } from '@/modules/vitrine/components/sections';
import { Clock, Users, Wallet, Shield, Brain, Smartphone } from 'lucide-react';

export default function Features() {
  const features = [
    {
      icon: <Clock className="w-7 h-7" />,
      title: 'Pointage Intelligent',
      description: 'Gestion ultra-précise des présences avec compatibilité ZKTeco, NFC et biométrie avancée.',
      gradient: 'from-emerald-400 to-teal-500',
      stats: { value: '99.9%', label: 'Précision' },
      details: ['Reconnaissance faciale', 'NFC / QR Code', 'Géolocalisation', 'Mode hors-ligne'],
    },
    {
      icon: <Users className="w-7 h-7" />,
      title: 'Gestion des Absences',
      description: 'Workflow complet de demande, validation et suivi des congés avec calendrier partagé.',
      gradient: 'from-blue-400 to-indigo-500',
      stats: { value: '50K+', label: 'Utilisateurs' },
      details: ['Soldes en temps réel', 'Validation multi-niveaux', 'Calendrier équipe', 'Alertes automatiques'],
    },
    {
      icon: <Wallet className="w-7 h-7" />,
      title: 'Paie Automatisée',
      description: 'Calcul automatique adapté aux réglementations locales avec génération de bulletins.',
      gradient: 'from-amber-400 to-orange-500',
      stats: { value: '3x', label: 'Plus rapide' },
      details: ['Multi-devises', 'Exports comptables', 'Avances sur salaire', 'Conformité fiscale'],
    },
    {
      icon: <Shield className="w-7 h-7" />,
      title: 'Sécurité Renforcée',
      description: 'Authentification biométrique, chiffrement bout-en-bout et audit trail complet.',
      gradient: 'from-violet-400 to-purple-500',
      stats: { value: 'SOC2', label: 'Certifié' },
      details: ['2FA obligatoire', 'Chiffrement AES-256', 'Audit trail', 'RGPD compliant'],
    },
    {
      icon: <Brain className="w-7 h-7" />,
      title: 'Leo IA',
      description: 'Assistant IA intégré pour analyser vos données RH, prédire les tendances et automatiser les tâches.',
      gradient: 'from-fuchsia-400 to-pink-500',
      stats: { value: 'GPT-4', label: 'Propulsé' },
      details: ['Analyse prédictive', 'Rapports automatiques', 'Commande vocale', 'Suggestions intelligentes'],
    },
    {
      icon: <Smartphone className="w-7 h-7" />,
      title: 'Mobile First',
      description: 'Application native iOS et Android avec synchronisation temps réel et mode offline.',
      gradient: 'from-cyan-400 to-sky-500',
      stats: { value: '4.9', label: 'App Store' },
      details: ['iOS & Android', 'Mode offline', 'Push notifications', 'Widgets natifs'],
    },
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

### Avec 2 Colonnes

```tsx
<FeaturesSection
  title="Fonctionnalités"
  subtitle="principales"
  features={features.slice(0, 4)}
  columns={2}
/>
```

---

## PricingSection

### Exemple Basique

```tsx
import { PricingSection } from '@/modules/vitrine/components/sections';

export default function Pricing() {
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
        'Rapports basiques',
      ],
      cta: { text: 'Commencer', href: '/signup?plan=starter' },
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
        'Rapports avancés',
        'API access',
      ],
      cta: { text: 'Commencer', href: '/signup?plan=business' },
      highlighted: true,
      badge: 'POPULAIRE',
    },
    {
      name: 'Enterprise',
      price: 299,
      currency: 'EUR',
      period: '/mois',
      description: 'Pour les grandes organisations',
      features: [
        'Employés illimités',
        'Pointage personnalisé',
        'Paie multi-pays',
        'Support 24/7',
        'Rapports personnalisés',
        'API illimitée',
        'Intégrations custom',
      ],
      cta: { text: 'Contacter', href: '/contact?plan=enterprise' },
    },
  ];

  return (
    <PricingSection
      title="Tarification"
      subtitle="simple et transparente"
      badge={{ text: 'Plans' }}
      plans={plans}
    />
  );
}
```

### Avec Toggle Annuel/Mensuel

```tsx
<PricingSection
  title="Tarification"
  subtitle="simple et transparente"
  badge={{ text: 'Plans' }}
  plans={plans}
  showToggle={true}
  toggleLabel={{ monthly: 'Mensuel', annual: 'Annuel' }}
/>
```

---

## TestimonialsSection

### Exemple Basique

```tsx
import { TestimonialsSection } from '@/modules/vitrine/components/sections';

export default function Testimonials() {
  const testimonials = [
    {
      quote: "Leopardo a transformé notre gestion RH. Nous avons réduit le temps administratif de 70% et nos employés adorent l'application mobile.",
      author: 'Ahmed Bennani',
      role: 'Fondateur',
      company: 'TechStartup',
      avatar: '/avatars/ahmed.jpg',
      rating: 5,
    },
    {
      quote: "La paie automatisée nous a sauvé des heures chaque mois. Le support est réactif et la plateforme est très intuitive.",
      author: 'Fatima Alaoui',
      role: 'Manager RH',
      company: 'PME Solutions',
      avatar: '/avatars/fatima.jpg',
      rating: 5,
    },
    {
      quote: "Excellent rapport qualité-prix. Nous avons testé plusieurs solutions et Leopardo est la meilleure pour notre budget.",
      author: 'Karim Mansouri',
      role: 'Directeur Général',
      company: 'Commerce Plus',
      avatar: '/avatars/karim.jpg',
      rating: 4,
    },
  ];

  return (
    <TestimonialsSection
      title="Ce que disent"
      subtitle="nos clients"
      badge={{ text: 'Témoignages' }}
      testimonials={testimonials}
      columns={3}
    />
  );
}
```

### Avec 2 Colonnes

```tsx
<TestimonialsSection
  title="Témoignages"
  subtitle="clients"
  testimonials={testimonials}
  columns={2}
/>
```

---

## CaseStudiesSection

### Exemple Basique

```tsx
import { CaseStudiesSection } from '@/modules/vitrine/components/sections';

export default function CaseStudies() {
  const caseStudies = [
    {
      title: 'TechStartup: Croissance de 300%',
      description: 'Comment une startup a géré sa croissance de 5 à 50 employés avec Leopardo.',
      industry: 'Tech',
      metrics: [
        { label: 'Employés', value: '50' },
        { label: 'Réduction temps', value: '70%' },
        { label: 'Satisfaction', value: '4.9/5' },
        { label: 'ROI', value: '300%' },
      ],
      image: '/case-studies/techstartup.jpg',
      link: '/case-studies/techstartup',
    },
    {
      title: 'PME Solutions: Conformité garantie',
      description: 'Comment une PME a atteint la conformité fiscale complète en 2 mois.',
      industry: 'Services',
      metrics: [
        { label: 'Employés', value: '120' },
        { label: 'Conformité', value: '100%' },
        { label: 'Temps paie', value: '-80%' },
        { label: 'Erreurs', value: '0' },
      ],
      image: '/case-studies/pme-solutions.jpg',
      link: '/case-studies/pme-solutions',
    },
    {
      title: 'Commerce Plus: Efficacité maximale',
      description: 'Comment une chaîne de magasins a optimisé la gestion de 500+ employés.',
      industry: 'Retail',
      metrics: [
        { label: 'Employés', value: '500+' },
        { label: 'Magasins', value: '25' },
        { label: 'Efficacité', value: '+45%' },
        { label: 'Coûts', value: '-35%' },
      ],
      image: '/case-studies/commerce-plus.jpg',
      link: '/case-studies/commerce-plus',
    },
  ];

  return (
    <CaseStudiesSection
      title="Cas d'usage"
      subtitle="réels"
      badge={{ text: 'Success Stories' }}
      caseStudies={caseStudies}
      columns={3}
    />
  );
}
```

---

## FAQSection

### Exemple Basique

```tsx
import { FAQSection } from '@/modules/vitrine/components/sections';

export default function FAQ() {
  const faqItems = [
    {
      id: '1',
      question: 'Comment fonctionne le pointage?',
      answer: 'Notre système de pointage utilise la reconnaissance faciale, NFC ou QR codes. Les employés peuvent pointer depuis l\'application mobile ou les bornes de pointage.',
      category: 'Pointage',
    },
    {
      id: '2',
      question: 'Quel est le coût?',
      answer: 'Nos plans commencent à 29€/mois pour les petites équipes. Chaque plan inclut un nombre d\'employés et des fonctionnalités spécifiques.',
      category: 'Tarification',
    },
    {
      id: '3',
      question: 'Offrez-vous un essai gratuit?',
      answer: 'Oui, nous offrons 14 jours d\'essai gratuit sans carte de crédit requise. Vous avez accès à toutes les fonctionnalités.',
      category: 'Tarification',
    },
    {
      id: '4',
      question: 'Comment sont gérées les absences?',
      answer: 'Les employés peuvent demander des congés via l\'application. Les managers valident les demandes. Le système calcule automatiquement les soldes.',
      category: 'Absences',
    },
    {
      id: '5',
      question: 'La paie est-elle automatisée?',
      answer: 'Oui, la paie est entièrement automatisée. Le système calcule les salaires selon les réglementations locales et génère les bulletins.',
      category: 'Paie',
    },
    {
      id: '6',
      question: 'Quel support offrez-vous?',
      answer: 'Nous offrons un support email pour tous les plans. Les plans Business et Enterprise incluent un support prioritaire et 24/7.',
      category: 'Support',
    },
  ];

  return (
    <FAQSection
      title="Questions"
      subtitle="fréquemment posées"
      badge={{ text: 'FAQ' }}
      items={faqItems}
      categories={['Pointage', 'Tarification', 'Absences', 'Paie', 'Support']}
    />
  );
}
```

---

## CTASection

### Gradient Background

```tsx
import { CTASection } from '@/modules/vitrine/components/sections';

export default function CTAGradient() {
  return (
    <CTASection
      headline="Prêt à transformer votre RH?"
      subheadline="Rejoignez 500+ entreprises qui font confiance à Leopardo."
      ctaPrimary={{
        text: "Essai gratuit 14 jours",
        href: "/auth/login"
      }}
      ctaSecondary={{
        text: "Voir la démo",
        href: "#demo"
      }}
      background="gradient"
    />
  );
}
```

### Solid Background

```tsx
<CTASection
  headline="Prêt à transformer votre RH?"
  subheadline="Rejoignez 500+ entreprises qui font confiance à Leopardo."
  ctaPrimary={{
    text: "Essai gratuit 14 jours",
    href: "/auth/login"
  }}
  background="solid"
/>
```

### Image Background

```tsx
<CTASection
  headline="Prêt à transformer votre RH?"
  subheadline="Rejoignez 500+ entreprises qui font confiance à Leopardo."
  ctaPrimary={{
    text: "Essai gratuit 14 jours",
    href: "/auth/login"
  }}
  background="image"
  backgroundImage="/cta-background.jpg"
/>
```

---

## BlogGrid

### Exemple Basique

```tsx
import { BlogGrid } from '@/modules/vitrine/components/sections';

export default function Blog() {
  const posts = [
    {
      slug: 'gestion-rh-2024',
      title: 'Les tendances RH en 2024',
      excerpt: 'Découvrez les principales tendances qui transforment la gestion RH cette année.',
      image: '/blog/rh-2024.jpg',
      date: new Date('2024-01-15'),
      author: { name: 'Jean Dupont', avatar: '/avatars/jean.jpg' },
      category: 'Tendances',
      readingTime: 5,
    },
    {
      slug: 'pointage-biometrique',
      title: 'Guide complet du pointage biométrique',
      excerpt: 'Tout ce que vous devez savoir sur la reconnaissance faciale et NFC pour le pointage.',
      image: '/blog/biometric.jpg',
      date: new Date('2024-01-10'),
      author: { name: 'Marie Martin', avatar: '/avatars/marie.jpg' },
      category: 'Tutoriels',
      readingTime: 8,
    },
    {
      slug: 'paie-automatisee',
      title: 'Comment automatiser votre paie',
      excerpt: 'Réduisez les erreurs et gagnez du temps avec la paie automatisée.',
      image: '/blog/payroll.jpg',
      date: new Date('2024-01-05'),
      author: { name: 'Pierre Durand', avatar: '/avatars/pierre.jpg' },
      category: 'Tutoriels',
      readingTime: 6,
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

---

## Page Complète

### Landing Page Complète

```tsx
'use client';

import {
  HeroSection,
  FeaturesSection,
  TestimonialsSection,
  CaseStudiesSection,
  FAQSection,
  CTASection,
} from '@/modules/vitrine/components/sections';
import { TrendingUp, Users, Zap, Star, Clock, Wallet, Shield, Brain, Smartphone } from 'lucide-react';

export default function LandingPage() {
  return (
    <>
      {/* Hero */}
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

      {/* Features */}
      <FeaturesSection
        title="Tout ce dont vous avez"
        subtitle="besoin"
        badge={{ text: 'Fonctionnalités' }}
        features={[
          {
            icon: <Clock className="w-7 h-7" />,
            title: 'Pointage Intelligent',
            description: 'Gestion ultra-précise des présences avec compatibilité ZKTeco, NFC et biométrie avancée.',
            gradient: 'from-emerald-400 to-teal-500',
            stats: { value: '99.9%', label: 'Précision' },
            details: ['Reconnaissance faciale', 'NFC / QR Code', 'Géolocalisation', 'Mode hors-ligne'],
          },
          // ... autres features
        ]}
        columns={3}
      />

      {/* Testimonials */}
      <TestimonialsSection
        title="Ce que disent"
        subtitle="nos clients"
        badge={{ text: 'Témoignages' }}
        testimonials={[
          {
            quote: "Leopardo a transformé notre gestion RH. Nous avons réduit le temps administratif de 70%.",
            author: 'Ahmed Bennani',
            role: 'Fondateur',
            company: 'TechStartup',
            avatar: '/avatars/ahmed.jpg',
            rating: 5,
          },
          // ... autres témoignages
        ]}
        columns={3}
      />

      {/* Case Studies */}
      <CaseStudiesSection
        title="Cas d'usage"
        subtitle="réels"
        badge={{ text: 'Success Stories' }}
        caseStudies={[
          {
            title: 'TechStartup: Croissance de 300%',
            description: 'Comment une startup a géré sa croissance de 5 à 50 employés avec Leopardo.',
            industry: 'Tech',
            metrics: [
              { label: 'Employés', value: '50' },
              { label: 'Réduction temps', value: '70%' },
            ],
            image: '/case-studies/techstartup.jpg',
            link: '/case-studies/techstartup',
          },
          // ... autres cas d'usage
        ]}
        columns={3}
      />

      {/* FAQ */}
      <FAQSection
        title="Questions"
        subtitle="fréquemment posées"
        badge={{ text: 'FAQ' }}
        items={[
          {
            id: '1',
            question: 'Comment fonctionne le pointage?',
            answer: 'Notre système utilise la reconnaissance faciale, NFC ou QR codes.',
            category: 'Pointage',
          },
          // ... autres questions
        ]}
        categories={['Pointage', 'Tarification', 'Support']}
      />

      {/* CTA Final */}
      <CTASection
        headline="Prêt à transformer votre RH?"
        subheadline="Rejoignez 500+ entreprises qui font confiance à Leopardo."
        ctaPrimary={{
          text: "Essai gratuit 14 jours",
          href: "/auth/login"
        }}
        background="gradient"
      />
    </>
  );
}
```

---

## 💡 Tips

1. **Réutilisez les données:** Créez des fichiers de données centralisés
2. **Combinez les sections:** Créez des pages complètes en combinant les sections
3. **Personnalisez les couleurs:** Modifiez les classes Tailwind selon vos besoins
4. **Testez les animations:** Utilisez `animated={false}` pour désactiver les animations si nécessaire
5. **Responsive:** Tous les composants sont responsive par défaut

---

**Phase 3 Examples:** ✅ Complétés
