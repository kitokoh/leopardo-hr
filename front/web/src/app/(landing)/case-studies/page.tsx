'use client';

import { useState } from 'react';
import { Navbar, HeroSection, CTASection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { ArrowRight, TrendingUp, Clock, Users, Building2, CheckCircle } from 'lucide-react';

const caseStudies = [
  {
    company: 'TechCorp Algérie',
    industry: 'Technologie',
    employees: '120',
    country: 'Algérie',
    challenge: 'Suivi des présences manuel avec des feuilles Excel, erreurs fréquentes en paie, 3 jours par mois perdus en vérifications.',
    solution: 'Déploiement de bornes ZKTeco + module pointage Leopardo RH avec synchronisation automatique vers la paie.',
    results: [
      { metric: '-80%', label: 'Temps de suivi présences' },
      { metric: '-95%', label: 'Erreurs de paie' },
      { metric: '3j/mois', label: 'Temps récupéré' },
    ],
    testimonial: 'Le ROI a été immédiat. Nous avons éliminé les erreurs de pointage dès la première semaine.',
    author: 'Amina Belkacem, DRH',
    color: 'emerald',
  },
  {
    company: 'Atlas Industries',
    industry: 'Manufacture',
    employees: '350',
    country: 'Maroc, Tunisie, France',
    challenge: 'Gestion RH fragmentée sur 3 pays avec des logiciels différents, impossibilité de consolider les rapports.',
    solution: 'Migration vers Leopardo RH multi-tenant avec paie multi-pays (barèmes fiscaux locaux) et tableau de bord consolidé.',
    results: [
      { metric: '1', label: 'Plateforme unique pour 3 pays' },
      { metric: '-60%', label: 'Coût logiciel RH' },
      { metric: '100%', label: 'Conformité locale' },
    ],
    testimonial: 'Nous gérons maintenant 3 filiales depuis un seul dashboard. La paie multi-pays est un game-changer.',
    author: 'Mehdi Ouazzani, DG',
    color: 'blue',
  },
  {
    company: 'LogiTrans Express',
    industry: 'Transport & Logistique',
    employees: '200',
    country: 'Algérie',
    challenge: 'Chauffeurs sur le terrain sans accès au bureau, suivi des véhicules déconnecté de la RH, pointage impossible.',
    solution: 'App mobile Flutter pour pointage terrain + module flotte véhicules + kiosque ZKTeco aux dépôts.',
    results: [
      { metric: '100%', label: 'Couverture pointage terrain' },
      { metric: '-40%', label: 'Coûts carburant (optimisation routes)' },
      { metric: '24/7', label: 'Visibilité temps réel' },
    ],
    testimonial: 'Le suivi flotte + RH combiné est unique. Nos chauffeurs pointent depuis leur mobile et on suit tout en temps réel.',
    author: 'Karim Benali, CEO',
    color: 'amber',
  },
];

const colorMap: Record<string, { bg: string; text: string; border: string; badge: string }> = {
  emerald: { bg: 'bg-emerald-50 dark:bg-emerald-900/20', text: 'text-emerald-700 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800', badge: 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400' },
  blue: { bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-700 dark:text-blue-400', border: 'border-blue-200 dark:border-blue-800', badge: 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400' },
  amber: { bg: 'bg-amber-50 dark:bg-amber-900/20', text: 'text-amber-700 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800', badge: 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400' },
};

export default function CaseStudiesPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline="Études de Cas Clients"
        subheadline="Comment nos clients ont transformé leur gestion RH avec Leopardo"
        ctaPrimary={{ text: 'Démarrer gratuitement', href: '/signup' }}
        ctaSecondary={{ text: 'Voir les témoignages', href: '/testimonials' }}
        badge={{ text: 'Succès Clients', icon: <TrendingUp className="w-3 h-3" /> }}
      />

      {/* Case Studies */}
      <section className="py-24">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
          {caseStudies.map((cs, i) => {
            const colors = colorMap[cs.color] || colorMap.emerald;
            return (
              <motion.article
                key={i}
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5 }}
                viewport={{ once: true }}
                className={`rounded-3xl border ${colors.border} overflow-hidden`}
              >
                {/* Header */}
                <div className={`${colors.bg} px-8 py-6`}>
                  <div className="flex flex-wrap items-center gap-4">
                    <div className={`px-3 py-1 rounded-full text-xs font-bold ${colors.badge}`}>
                      {cs.industry}
                    </div>
                    <h2 className="text-2xl font-black text-slate-900 dark:text-white">{cs.company}</h2>
                    <div className="flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400 ml-auto">
                      <span className="flex items-center gap-1"><Users className="w-4 h-4" />{cs.employees} employés</span>
                      <span className="flex items-center gap-1"><Building2 className="w-4 h-4" />{cs.country}</span>
                    </div>
                  </div>
                </div>

                {/* Body */}
                <div className="bg-white dark:bg-slate-900 px-8 py-8">
                  <div className="grid md:grid-cols-2 gap-8 mb-8">
                    <div>
                      <h3 className="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
                        <Clock className="w-4 h-4 text-red-500" /> Le défi
                      </h3>
                      <p className="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">{cs.challenge}</p>
                    </div>
                    <div>
                      <h3 className="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
                        <CheckCircle className="w-4 h-4 text-emerald-500" /> La solution
                      </h3>
                      <p className="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">{cs.solution}</p>
                    </div>
                  </div>

                  {/* Results */}
                  <div className="grid grid-cols-3 gap-4 mb-8">
                    {cs.results.map((r, j) => (
                      <div key={j} className={`${colors.bg} rounded-xl p-4 text-center`}>
                        <p className={`text-2xl font-black ${colors.text}`}>{r.metric}</p>
                        <p className="text-slate-500 dark:text-slate-400 text-xs mt-1">{r.label}</p>
                      </div>
                    ))}
                  </div>

                  {/* Testimonial */}
                  <blockquote className="border-l-4 border-emerald-500 pl-4 py-2">
                    <p className="text-slate-700 dark:text-slate-300 italic text-sm">&ldquo;{cs.testimonial}&rdquo;</p>
                    <cite className="text-slate-500 text-xs mt-2 block not-italic">&mdash; {cs.author}</cite>
                  </blockquote>
                </div>
              </motion.article>
            );
          })}
        </div>
      </section>

      <CTASection
        title="Votre entreprise pourrait être la prochaine"
        description="Rejoignez 500+ entreprises qui ont choisi Leopardo RH"
        primaryCta={{ text: 'Essai gratuit 14 jours', href: '/signup' }}
        secondaryCta={{ text: 'Demander une démo', href: '/demo' }}
      />

      <Footer />
    </div>
  );
}
