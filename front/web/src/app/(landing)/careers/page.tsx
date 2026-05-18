'use client';

import { useState } from 'react';
import { Navbar, Footer, HeroSection, CTASection, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Briefcase, MapPin, Clock, ArrowRight, Heart, Zap, Globe, Users } from 'lucide-react';

interface JobOpening {
  title: string;
  department: string;
  location: string;
  type: string;
  description: string;
}

const openings: JobOpening[] = [
  { title: 'Developpeur Full-Stack Senior', department: 'Engineering', location: 'Paris / Remote', type: 'CDI', description: 'Rejoignez notre equipe pour developper les nouvelles fonctionnalites de la plateforme RH.' },
  { title: 'Designer UI/UX', department: 'Design', location: 'Paris / Remote', type: 'CDI', description: 'Concevez des interfaces intuitives pour notre application web et mobile.' },
  { title: 'Customer Success Manager', department: 'Customer Success', location: 'Paris', type: 'CDI', description: 'Accompagnez nos clients dans l\'adoption de Leopardo RH.' },
  { title: 'Developpeur Mobile Flutter', department: 'Engineering', location: 'Remote', type: 'CDI', description: 'Developpez et ameliorez notre application mobile multi-plateforme.' },
  { title: 'DevOps Engineer', department: 'Engineering', location: 'Paris / Remote', type: 'CDI', description: 'Optimisez notre infrastructure cloud et nos pipelines CI/CD.' },
];

const values = [
  { icon: Heart, title: 'Impact reel', description: 'Nous construisons des outils qui simplifient la vie de milliers de professionnels RH chaque jour.' },
  { icon: Zap, title: 'Innovation', description: 'Nous adoptons les dernieres technologies et experimentons constamment pour offrir le meilleur produit.' },
  { icon: Globe, title: 'Diversite', description: 'Notre equipe est distribuee globalement. Nous valorisons les perspectives differentes.' },
  { icon: Users, title: 'Collaboration', description: 'Nous travaillons ensemble, partageons nos connaissances et celebrons nos reussites en equipe.' },
];

export default function CareersPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <HeroSection
        headline="Rejoignez l'Equipe"
        subheadline="Construisez le futur de la gestion RH avec nous"
        ctaPrimary={{ text: 'Voir les Postes', href: '#openings' }}
        badge={{ text: 'Carrieres', icon: <Briefcase className="w-3 h-3" /> }}
      />

      {/* Values */}
      <section className="py-24 bg-slate-50 dark:bg-slate-900/50">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-4">Nos Valeurs</h2>
            <p className="text-lg text-slate-600 dark:text-slate-400">Ce qui nous anime au quotidien</p>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {values.map((val, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.1 }}
                className="text-center p-6"
              >
                <div className="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center mx-auto mb-4">
                  <val.icon className="w-7 h-7 text-emerald-600 dark:text-emerald-400" />
                </div>
                <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">{val.title}</h3>
                <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{val.description}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Benefits */}
      <section className="py-24">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl font-black text-slate-900 dark:text-white mb-10 text-center">Avantages</h2>
          <div className="grid sm:grid-cols-2 gap-6">
            {[
              'Teletravail flexible (full remote possible)',
              'Mutuelle sante premium',
              'Budget formation annuel',
              'Materiel au choix (Mac/PC/Linux)',
              'Tickets restaurant',
              'Team buildings trimestriels',
              'Conges supplementaires apres 2 ans',
              'Stock options pour les postes senior',
            ].map((benefit, i) => (
              <div key={i} className="flex items-center gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <div className="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0" />
                <span className="text-slate-700 dark:text-slate-300">{benefit}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Openings */}
      <section id="openings" className="py-24 bg-slate-50 dark:bg-slate-900/50">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-4">Postes Ouverts</h2>
            <p className="text-lg text-slate-600 dark:text-slate-400">{openings.length} postes disponibles</p>
          </div>
          <div className="space-y-4">
            {openings.map((job, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 10 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.05 }}
                className="group p-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 hover:border-emerald-500/30 hover:shadow-lg transition-all cursor-pointer"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">{job.title}</h3>
                    <p className="text-sm text-slate-600 dark:text-slate-400 mt-1 mb-3">{job.description}</p>
                    <div className="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
                      <span className="flex items-center gap-1"><Briefcase className="w-3 h-3" />{job.department}</span>
                      <span className="flex items-center gap-1"><MapPin className="w-3 h-3" />{job.location}</span>
                      <span className="flex items-center gap-1"><Clock className="w-3 h-3" />{job.type}</span>
                    </div>
                  </div>
                  <ArrowRight className="w-5 h-5 text-slate-400 group-hover:text-emerald-500 transition-colors flex-shrink-0 mt-1" />
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTASection
        headline="Aucun Poste ne Correspond ?"
        subheadline="Envoyez-nous votre candidature spontanee"
        ctaPrimary={{ text: 'Candidature Spontanee', href: '/contact' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
