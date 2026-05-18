'use client';

import { useState } from 'react';
import { Navbar, Footer, HeroSection, useScrollReveal } from '@/modules/vitrine';
import { CTASection } from '@/modules/vitrine';
import { motion, AnimatePresence } from 'framer-motion';
import { HelpCircle, ChevronDown, Search } from 'lucide-react';

interface FaqItem {
  question: string;
  answer: string;
  category: string;
}

const faqItems: FaqItem[] = [
  {
    category: 'General',
    question: 'Qu\'est-ce que Leopardo RH ?',
    answer: 'Leopardo RH est une plateforme SaaS multi-tenant de gestion des ressources humaines qui couvre la paie, les conges, le pointage, le recrutement, la formation et bien plus. Elle est disponible en version web, mobile (Flutter) et borne kiosk (ZKTeco).',
  },
  {
    category: 'General',
    question: 'Pour quels types d\'entreprises est concu Leopardo RH ?',
    answer: 'Leopardo RH est concu pour les PME, startups et grandes entreprises de tous secteurs. L\'architecture multi-tenant permet une isolation complete des donnees entre entreprises tout en partageant la meme infrastructure.',
  },
  {
    category: 'Tarification',
    question: 'Comment fonctionne la tarification ?',
    answer: 'Nous proposons des plans mensuels et annuels adaptes a la taille de votre equipe. Le plan Starter est gratuit pour les petites equipes, le plan Pro inclut toutes les fonctionnalites, et le plan Enterprise offre des options sur mesure.',
  },
  {
    category: 'Tarification',
    question: 'Y a-t-il un essai gratuit ?',
    answer: 'Oui ! Nous offrons un essai gratuit de 14 jours sans engagement et sans carte de credit. Vous avez acces a toutes les fonctionnalites du plan Pro pendant la periode d\'essai.',
  },
  {
    category: 'Fonctionnalites',
    question: 'Comment fonctionne le pointage biometrique ?',
    answer: 'Leopardo RH s\'integre avec les bornes ZKTeco pour le pointage par empreinte digitale, reconnaissance faciale ou QR code. Les pointages sont synchronises en temps reel avec le serveur central, avec support du mode offline.',
  },
  {
    category: 'Fonctionnalites',
    question: 'Peut-on generer des bulletins de paie multi-pays ?',
    answer: 'Oui, Leopardo RH supporte la paie pour 6 pays (France, Algerie, Turquie, Senegal, Maroc, Tunisie) avec les baremes fiscaux et cotisations sociales specifiques a chaque pays. Les bulletins sont generes en PDF.',
  },
  {
    category: 'Fonctionnalites',
    question: 'L\'application mobile est-elle disponible ?',
    answer: 'Oui, l\'application mobile Leopardo RH est disponible pour iOS et Android. Elle permet aux employes de pointer, consulter leurs fiches de paie, demander des conges, voir l\'organigramme et recevoir des notifications push.',
  },
  {
    category: 'Securite',
    question: 'Comment sont protegees les donnees ?',
    answer: 'Toutes les donnees sont chiffrees en transit (TLS 1.3) et au repos. L\'architecture multi-tenant utilise des schemas PostgreSQL isoles par entreprise. Nous appliquons les bonnes pratiques OWASP et effectuons des audits de securite reguliers.',
  },
  {
    category: 'Securite',
    question: 'Etes-vous conforme au RGPD ?',
    answer: 'Oui, Leopardo RH est conforme au RGPD. Les donnees sont hebergees en Europe, et nous offrons des outils d\'export et de suppression des donnees personnelles conformes aux exigences reglementaires.',
  },
  {
    category: 'Support',
    question: 'Quel support est disponible ?',
    answer: 'Le plan Starter inclut le support par email. Le plan Pro inclut le support prioritaire avec un temps de reponse de 4h. Le plan Enterprise inclut un account manager dedie et un support 24/7.',
  },
  {
    category: 'Integration',
    question: 'Quelles integrations sont disponibles ?',
    answer: 'Leopardo RH s\'integre avec Google Calendar, Microsoft Outlook, les bornes ZKTeco, Firebase Cloud Messaging pour les notifications push, et expose une API REST complete pour les integrations personnalisees.',
  },
  {
    category: 'Integration',
    question: 'Peut-on exporter les donnees vers des logiciels comptables ?',
    answer: 'Oui, Leopardo RH supporte l\'export des ecritures de paie en format SEPA XML, CCP DZ et CSV compatible avec les principaux logiciels comptables (Sage, QuickBooks, etc.).',
  },
];

export default function FaqPage() {
  const [isDark, setIsDark] = useState(false);
  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState('Tous');
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  useScrollReveal();

  const categories = ['Tous', ...Array.from(new Set(faqItems.map(f => f.category)))];

  const filtered = faqItems.filter(item => {
    const matchesSearch = search === '' || item.question.toLowerCase().includes(search.toLowerCase()) || item.answer.toLowerCase().includes(search.toLowerCase());
    const matchesCategory = activeCategory === 'Tous' || item.category === activeCategory;
    return matchesSearch && matchesCategory;
  });

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <HeroSection
        headline="Questions Frequentes"
        subheadline="Trouvez rapidement les reponses a vos questions"
        badge={{ text: 'FAQ', icon: <HelpCircle className="w-3 h-3" /> }}
      />

      <section className="py-24">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="relative mb-8">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
            <input
              type="text"
              placeholder="Rechercher une question..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              className="w-full pl-12 pr-4 py-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            />
          </div>

          <div className="flex flex-wrap gap-2 mb-10">
            {categories.map(cat => (
              <button
                key={cat}
                onClick={() => setActiveCategory(cat)}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${activeCategory === cat ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'}`}
              >
                {cat}
              </button>
            ))}
          </div>

          <div className="space-y-3">
            {filtered.map((item, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 10 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.03 }}
                className="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden"
              >
                <button
                  onClick={() => setOpenIndex(openIndex === index ? null : index)}
                  className="w-full flex items-center justify-between p-5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                >
                  <span className="font-semibold text-slate-900 dark:text-white pr-4">{item.question}</span>
                  <ChevronDown className={`w-5 h-5 text-slate-400 transition-transform flex-shrink-0 ${openIndex === index ? 'rotate-180' : ''}`} />
                </button>
                <AnimatePresence>
                  {openIndex === index && (
                    <motion.div
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      transition={{ duration: 0.2 }}
                    >
                      <div className="px-5 pb-5 text-slate-600 dark:text-slate-400 leading-relaxed">
                        {item.answer}
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </motion.div>
            ))}
            {filtered.length === 0 && (
              <p className="text-center py-12 text-slate-500">Aucune question trouvee pour cette recherche.</p>
            )}
          </div>
        </div>
      </section>

      <CTASection
        headline="Encore des Questions ?"
        subheadline="Contactez notre equipe pour une reponse personnalisee"
        ctaPrimary={{ text: 'Nous Contacter', href: '/contact' }}
        ctaSecondary={{ text: 'Essai gratuit', href: '/demo' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
