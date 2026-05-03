'use client';

import { useState } from 'react';
import {
  Navbar,
  HeroSection,
  PricingSection,
  FAQSection,
  CTASection,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';
import { pricingPlans, faqItems } from '@/modules/vitrine/lib/constants';
import { Zap } from 'lucide-react';

// Comparison table data
const comparisonFeatures = [
  {
    category: 'Gestion RH',
    features: [
      { name: 'Pointage numérique', starter: true, business: true, enterprise: true },
      { name: 'Gestion des absences', starter: true, business: true, enterprise: true },
      { name: 'Calendrier partagé', starter: true, business: true, enterprise: true },
      { name: 'Évaluations & Performance', starter: false, business: true, enterprise: true },
    ],
  },
  {
    category: 'Paie & Comptabilité',
    features: [
      { name: 'Paie automatisée', starter: true, business: true, enterprise: true },
      { name: 'Bulletins de paie', starter: true, business: true, enterprise: true },
      { name: 'Exports comptables', starter: false, business: true, enterprise: true },
      { name: 'Multi-devises', starter: false, business: false, enterprise: true },
    ],
  },
  {
    category: 'Documents & Sécurité',
    features: [
      { name: 'Cabinet numérique', starter: false, business: true, enterprise: true },
      { name: 'Chiffrement AES-256', starter: false, business: true, enterprise: true },
      { name: 'Conformité RGPD', starter: false, business: true, enterprise: true },
      { name: 'Audit trail complet', starter: false, business: false, enterprise: true },
    ],
  },
  {
    category: 'Marketing & Intégrations',
    features: [
      { name: 'Email marketing', starter: false, business: true, enterprise: true },
      { name: 'SMS marketing', starter: false, business: true, enterprise: true },
      { name: 'Intégrations avancées', starter: false, business: true, enterprise: true },
      { name: 'API personnalisée', starter: false, business: false, enterprise: true },
    ],
  },
  {
    category: 'Support & Services',
    features: [
      { name: 'Support email', starter: true, business: true, enterprise: true },
      { name: 'Support prioritaire', starter: false, business: true, enterprise: true },
      { name: 'Support 24/7', starter: false, business: false, enterprise: true },
      { name: 'Formations incluses', starter: false, business: false, enterprise: true },
    ],
  },
];

// FAQ items specific to pricing
const pricingFaqItems = [
  {
    id: 'change-plan',
    question: 'Puis-je changer de plan?',
    answer:
      'Oui, vous pouvez changer de plan à tout moment. Les changements prendront effet au prochain cycle de facturation. Si vous passez à un plan supérieur, vous serez facturisé au prorata. Si vous passez à un plan inférieur, le crédit sera appliqué à votre prochaine facture.',
    category: 'Facturation',
  },
  {
    id: 'free-trial',
    question: 'Essai gratuit inclus?',
    answer:
      'Oui, tous les plans incluent un essai gratuit de 14 jours sans carte bancaire requise. Vous avez accès à toutes les fonctionnalités du plan Business pendant l\'essai. Aucune facturation automatique après l\'essai - vous devez confirmer votre abonnement.',
    category: 'Essai',
  },
  {
    id: 'long-term-contract',
    question: 'Contrat long terme?',
    answer:
      'Nous proposons des contrats mensuels ou annuels. Les contrats annuels bénéficient d\'une réduction de 20%. Vous pouvez annuler votre abonnement à tout moment sans pénalité. Pour les contrats annuels, vous pouvez demander un remboursement au prorata si vous annulez avant la fin de l\'année.',
    category: 'Facturation',
  },
  {
    id: 'support-availability',
    question: 'Support client disponible?',
    answer:
      'Oui, nous offrons un support email pour tous les plans. Les plans Business et Enterprise bénéficient d\'un support prioritaire avec temps de réponse garanti. Le plan Enterprise inclut un support 24/7 et un gestionnaire de compte dédié.',
    category: 'Support',
  },
  {
    id: 'data-security',
    question: 'Données sécurisées?',
    answer:
      'Oui, toutes les données sont chiffrées avec AES-256 et stockées sur des serveurs sécurisés conformes à la RGPD. Nous effectuons des sauvegardes automatiques quotidiennes. Tous les plans incluent une conformité RGPD, HIPAA et SOC2 (Enterprise).',
    category: 'Sécurité',
  },
  {
    id: 'employee-limit',
    question: 'Limite d\'employés?',
    answer:
      'Le plan Starter supporte jusqu\'à 10 employés. Le plan Business supporte jusqu\'à 100 employés. Le plan Enterprise supporte un nombre illimité d\'employés. Vous pouvez ajouter des employés supplémentaires à tout moment.',
    category: 'Fonctionnalités',
  },
  {
    id: 'invoice-customization',
    question: 'Puis-je personnaliser les factures?',
    answer:
      'Oui, les plans Business et Enterprise permettent de personnaliser les factures avec votre logo et vos informations. Vous pouvez également configurer des conditions de paiement personnalisées et des cycles de facturation flexibles.',
    category: 'Facturation',
  },
  {
    id: 'bulk-discount',
    question: 'Réductions pour les achats en volume?',
    answer:
      'Oui, nous proposons des réductions pour les achats en volume. Contactez notre équipe de ventes pour discuter de tarifs personnalisés si vous avez plus de 500 employés ou des besoins spécifiques.',
    category: 'Facturation',
  },
];

export default function PricingPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  // Transform pricing plans to match PricingCardProps
  const pricingCards = pricingPlans.map((plan) => ({
    name: plan.name,
    price: plan.price,
    currency: plan.currency,
    period: `/${plan.period}`,
    description: plan.description,
    features: plan.features,
    cta: plan.cta,
    highlighted: plan.highlighted,
    badge: plan.badge,
  }));

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* Hero Section */}
      <HeroSection
        headline="Tarification Transparente et Flexible"
        subheadline="Choisissez le plan adapté à votre taille"
        ctaPrimary={{
          text: 'Essai gratuit',
          href: '/signup',
        }}
        ctaSecondary={{
          text: 'Contacter les ventes',
          href: '/contact?type=enterprise',
        }}
        badge={{
          text: 'Pricing',
          icon: <Zap className="w-3 h-3" />,
        }}
      />

      {/* Pricing Section */}
      <PricingSection
        title="Plans Pricing"
        subtitle="Transparent et sans surprise"
        plans={pricingCards}
        showToggle={true}
        toggleLabel={{
          monthly: 'Mensuel',
          annual: 'Annuel',
        }}
        badge={{
          text: 'Nos Plans',
          icon: <Zap className="w-3 h-3" />,
        }}
      />

      {/* Comparison Table Section */}
      <section className="relative py-32 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 via-white to-slate-50/50 dark:from-slate-900/50 dark:via-slate-950 dark:to-slate-900/50" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              Comparaison Détaillée
            </div>
            <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              Tableau de Comparaison
              <span className="block bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                Fonctionnalités par Plan
              </span>
            </h2>
          </div>

          {/* Comparison Table */}
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800">
                  <th className="text-left py-4 px-6 font-bold text-slate-900 dark:text-white">
                    Fonctionnalités
                  </th>
                  <th className="text-center py-4 px-6 font-bold text-slate-900 dark:text-white">
                    Starter
                  </th>
                  <th className="text-center py-4 px-6 font-bold text-slate-900 dark:text-white">
                    Business
                  </th>
                  <th className="text-center py-4 px-6 font-bold text-slate-900 dark:text-white">
                    Enterprise
                  </th>
                </tr>
              </thead>
              <tbody>
                {comparisonFeatures.map((category, categoryIndex) => (
                  <tbody key={categoryIndex}>
                    {/* Category Header */}
                    <tr className="bg-slate-50 dark:bg-slate-900/50">
                      <td colSpan={4} className="py-3 px-6">
                        <h3 className="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">
                          {category.category}
                        </h3>
                      </td>
                    </tr>
                    {/* Features */}
                    {category.features.map((feature, featureIndex) => (
                      <tr
                        key={featureIndex}
                        className="border-b border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900/30 transition-colors"
                      >
                        <td className="py-4 px-6 text-slate-700 dark:text-slate-300 font-medium">
                          {feature.name}
                        </td>
                        <td className="py-4 px-6 text-center">
                          {feature.starter ? (
                            <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/10">
                              <svg
                                className="w-4 h-4 text-emerald-600 dark:text-emerald-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                              >
                                <path
                                  fillRule="evenodd"
                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                  clipRule="evenodd"
                                />
                              </svg>
                            </span>
                          ) : (
                            <span className="text-slate-400 dark:text-slate-600">—</span>
                          )}
                        </td>
                        <td className="py-4 px-6 text-center">
                          {feature.business ? (
                            <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/10">
                              <svg
                                className="w-4 h-4 text-emerald-600 dark:text-emerald-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                              >
                                <path
                                  fillRule="evenodd"
                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                  clipRule="evenodd"
                                />
                              </svg>
                            </span>
                          ) : (
                            <span className="text-slate-400 dark:text-slate-600">—</span>
                          )}
                        </td>
                        <td className="py-4 px-6 text-center">
                          {feature.enterprise ? (
                            <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/10">
                              <svg
                                className="w-4 h-4 text-emerald-600 dark:text-emerald-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                              >
                                <path
                                  fillRule="evenodd"
                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                  clipRule="evenodd"
                                />
                              </svg>
                            </span>
                          ) : (
                            <span className="text-slate-400 dark:text-slate-600">—</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* FAQ Section */}
      <FAQSection
        title="Questions Fréquentes"
        subtitle="Tout ce que vous devez savoir"
        faqs={pricingFaqItems}
        categories={['Facturation', 'Essai', 'Support', 'Sécurité', 'Fonctionnalités']}
        badge={{
          text: 'FAQ Pricing',
          icon: <Zap className="w-3 h-3" />,
        }}
      />

      {/* CTA Section */}
      <CTASection
        headline="Prêt à transformer votre gestion RH?"
        subheadline="Commencez votre essai gratuit de 14 jours. Aucune carte bancaire requise."
        ctaPrimary={{
          text: 'Essai gratuit',
          href: '/signup',
        }}
        ctaSecondary={{
          text: 'Contacter les ventes',
          href: '/contact?type=enterprise',
        }}
        background="gradient"
        badge={{
          text: 'Commencer Maintenant',
          icon: <Zap className="w-3 h-3" />,
        }}
      />

      <Footer />
    </div>
  );
}
