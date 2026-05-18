'use client';

import { useState } from 'react';
import { Navbar, Footer, HeroSection, CTASection, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Star, Quote, Building2 } from 'lucide-react';

interface Testimonial {
  name: string;
  role: string;
  company: string;
  industry: string;
  employees: string;
  quote: string;
  rating: number;
  avatar: string;
}

const testimonials: Testimonial[] = [
  {
    name: 'Sophie Martin',
    role: 'DRH',
    company: 'TechVision SAS',
    industry: 'Technologie',
    employees: '150 employes',
    quote: 'Leopardo RH a completement transforme notre gestion des conges et de la paie. Nous avons gagne 20 heures par mois sur l\'administration.',
    rating: 5,
    avatar: '',
  },
  {
    name: 'Ahmed Benali',
    role: 'Directeur General',
    company: 'Atlas Consulting',
    industry: 'Conseil',
    employees: '85 employes',
    quote: 'La gestion multi-pays nous a permis de centraliser la paie de nos bureaux en France, Algerie et Tunisie sur une seule plateforme.',
    rating: 5,
    avatar: '',
  },
  {
    name: 'Marie Dupont',
    role: 'Responsable RH',
    company: 'GreenEnergy Corp',
    industry: 'Energie',
    employees: '320 employes',
    quote: 'L\'integration ZKTeco a ete un game changer. Le pointage biometrique en temps reel avec le mode offline nous a evite les problemes de connectivite.',
    rating: 5,
    avatar: '',
  },
  {
    name: 'Mehmet Yilmaz',
    role: 'CEO',
    company: 'Istanbul Digital',
    industry: 'Digital',
    employees: '45 employes',
    quote: 'L\'application mobile est excellente. Nos employes adorent pouvoir demander leurs conges et consulter leurs fiches de paie depuis leur telephone.',
    rating: 4,
    avatar: '',
  },
  {
    name: 'Fatima Zahra',
    role: 'Chief People Officer',
    company: 'Sahara Logistics',
    industry: 'Logistique',
    employees: '500+ employes',
    quote: 'Le tableau de bord d\'administration nous donne une vue complete sur notre effectif. Les rapports sont clairs et actionables.',
    rating: 5,
    avatar: '',
  },
  {
    name: 'Jean-Pierre Leroy',
    role: 'Fondateur',
    company: 'StartupFactory',
    industry: 'Startup Studio',
    employees: '25 employes',
    quote: 'En tant que startup, nous avions besoin d\'une solution simple mais complete. Leopardo RH coche toutes les cases sans etre trop complexe.',
    rating: 5,
    avatar: '',
  },
];

export default function TestimonialsPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <HeroSection
        headline="Ils Nous Font Confiance"
        subheadline="Decouvrez les temoignages de nos clients a travers le monde"
        badge={{ text: 'Temoignages', icon: <Star className="w-3 h-3" /> }}
      />

      {/* Stats */}
      <section className="py-16 bg-slate-50 dark:bg-slate-900/50">
        <div className="max-w-5xl mx-auto px-4 grid grid-cols-2 sm:grid-cols-4 gap-8 text-center">
          {[
            { value: '500+', label: 'Entreprises' },
            { value: '50 000+', label: 'Employes Geres' },
            { value: '4.8/5', label: 'Note Moyenne' },
            { value: '6', label: 'Pays Supportes' },
          ].map((stat, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 10 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: i * 0.1 }}
            >
              <div className="text-3xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400">{stat.value}</div>
              <div className="text-sm text-slate-600 dark:text-slate-400 mt-1">{stat.label}</div>
            </motion.div>
          ))}
        </div>
      </section>

      {/* Testimonials Grid */}
      <section className="py-24">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            {testimonials.map((t, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.05 }}
                className="p-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-shadow"
              >
                <Quote className="w-8 h-8 text-emerald-500/30 mb-4" />
                <p className="text-slate-700 dark:text-slate-300 leading-relaxed mb-6 italic">
                  &ldquo;{t.quote}&rdquo;
                </p>
                <div className="flex items-center gap-1 mb-4">
                  {Array.from({ length: 5 }).map((_, si) => (
                    <Star key={si} className={`w-4 h-4 ${si < t.rating ? 'text-amber-400 fill-amber-400' : 'text-slate-300'}`} />
                  ))}
                </div>
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold text-sm">
                    {t.name.split(' ').map(n => n[0]).join('')}
                  </div>
                  <div>
                    <p className="font-semibold text-slate-900 dark:text-white text-sm">{t.name}</p>
                    <p className="text-xs text-slate-500">{t.role}</p>
                  </div>
                </div>
                <div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center gap-2 text-xs text-slate-500">
                  <Building2 className="w-3 h-3" />
                  <span>{t.company} · {t.industry} · {t.employees}</span>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTASection
        headline="Rejoignez nos Clients Satisfaits"
        subheadline="Essayez Leopardo RH gratuitement pendant 14 jours"
        ctaPrimary={{ text: 'Essai Gratuit', href: '/demo' }}
        ctaSecondary={{ text: 'Nous Contacter', href: '/contact' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
