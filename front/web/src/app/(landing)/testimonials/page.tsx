'use client';

import { useState } from 'react';
import { Navbar, HeroSection, CTASection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Star, Building2, Users, Quote } from 'lucide-react';

const testimonials = [
  {
    name: 'Amina Belkacem',
    role: 'DRH',
    company: 'TechCorp Algérie',
    industry: 'Technologie',
    employees: '120 employés',
    quote: 'Leopardo RH a transformé notre gestion des pointages. Nous avons réduit de 80% le temps consacré au suivi des présences grâce au module ZKTeco intégré.',
    rating: 5,
    avatar: 'AB',
  },
  {
    name: 'Mehdi Ouazzani',
    role: 'Directeur Général',
    company: 'Atlas Industries',
    industry: 'Manufacture',
    employees: '350 employés',
    quote: 'La paie multi-pays nous a permis de gérer 3 filiales (Maroc, Tunisie, France) depuis une seule plateforme. Le ROI a été visible dès le premier mois.',
    rating: 5,
    avatar: 'MO',
  },
  {
    name: 'Fatima Zahra Idrissi',
    role: 'Responsable Paie',
    company: 'GreenEnergy SARL',
    industry: 'Énergie',
    employees: '85 employés',
    quote: 'Les bulletins de paie PDF générés automatiquement et les exports SEPA nous font gagner 3 jours par mois. L\'interface est intuitive même pour notre équipe non-technique.',
    rating: 5,
    avatar: 'FZ',
  },
  {
    name: 'Karim Benali',
    role: 'CEO',
    company: 'LogiTrans Express',
    industry: 'Transport & Logistique',
    employees: '200 employés',
    quote: 'Le suivi de flotte véhicules combiné à la gestion RH est unique sur le marché. Nos chauffeurs pointent depuis le kiosque ZKTeco et on suit tout en temps réel.',
    rating: 4,
    avatar: 'KB',
  },
  {
    name: 'Sarah Mansouri',
    role: 'Office Manager',
    company: 'Digital Agency Pro',
    industry: 'Marketing Digital',
    employees: '45 employés',
    quote: 'Le module de recrutement et le suivi des formations nous ont permis de structurer notre croissance. On est passé de 15 à 45 employés sans augmenter l\'équipe RH.',
    rating: 5,
    avatar: 'SM',
  },
  {
    name: 'Youssef El Amrani',
    role: 'DAF',
    company: 'BâtiConstruct Group',
    industry: 'BTP',
    employees: '500+ employés',
    quote: 'Pour le BTP avec des chantiers multiples, la gestion des absences et le pointage mobile sont essentiels. Leopardo RH comprend les réalités du terrain.',
    rating: 5,
    avatar: 'YE',
  },
];

const stats = [
  { value: '500+', label: 'Entreprises clientes' },
  { value: '50 000+', label: 'Employés gérés' },
  { value: '4.8/5', label: 'Note moyenne' },
  { value: '6', label: 'Pays couverts' },
];

export default function TestimonialsPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline="Ils nous font confiance"
        subheadline="Découvrez comment nos clients transforment leur gestion RH avec Leopardo"
        ctaPrimary={{ text: 'Démarrer l\'essai gratuit', href: '/signup' }}
        ctaSecondary={{ text: 'Voir les études de cas', href: '/case-studies' }}
        badge={{ text: 'Témoignages', icon: <Star className="w-3 h-3" /> }}
      />

      {/* Stats Banner */}
      <section className="py-16 bg-emerald-600 dark:bg-emerald-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            {stats.map((stat, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.1 }}
                viewport={{ once: true }}
              >
                <p className="text-3xl sm:text-4xl font-black text-white">{stat.value}</p>
                <p className="text-emerald-100 mt-1 text-sm">{stat.label}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Testimonials Grid */}
      <section className="py-24 bg-slate-50 dark:bg-slate-900">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {testimonials.map((t, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.08 }}
                viewport={{ once: true }}
                className="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-shadow"
              >
                <Quote className="w-8 h-8 text-emerald-500/30 mb-4" />
                <p className="text-slate-700 dark:text-slate-300 leading-relaxed mb-6 text-sm">
                  &ldquo;{t.quote}&rdquo;
                </p>

                <div className="flex items-center gap-1 mb-4">
                  {Array.from({ length: 5 }).map((_, s) => (
                    <Star
                      key={s}
                      className={`w-4 h-4 ${s < t.rating ? 'text-yellow-400 fill-yellow-400' : 'text-slate-300'}`}
                    />
                  ))}
                </div>

                <div className="flex items-center gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                  <div className="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-700 dark:text-emerald-400 font-bold text-sm">
                    {t.avatar}
                  </div>
                  <div className="flex-1">
                    <p className="font-semibold text-slate-900 dark:text-white text-sm">{t.name}</p>
                    <p className="text-slate-500 dark:text-slate-400 text-xs">{t.role}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-slate-700 dark:text-slate-300 text-xs font-medium flex items-center gap-1">
                      <Building2 className="w-3 h-3" />{t.company}
                    </p>
                    <p className="text-slate-400 text-xs flex items-center gap-1 justify-end">
                      <Users className="w-3 h-3" />{t.employees}
                    </p>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTASection
        title="Rejoignez nos 500+ clients satisfaits"
        description="Démarrez votre essai gratuit de 14 jours"
        primaryCta={{ text: 'Commencer maintenant', href: '/signup' }}
        secondaryCta={{ text: 'Demander une démo', href: '/demo' }}
      />

      <Footer />
    </div>
  );
}
