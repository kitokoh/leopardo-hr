'use client';

import { useState } from 'react';
import { Navbar, Footer, HeroSection, CTASection, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Play, Clock, Tag } from 'lucide-react';

interface Video {
  id: string;
  title: string;
  description: string;
  thumbnail: string;
  duration: string;
  category: string;
  youtubeId: string;
}

const videos: Video[] = [
  {
    id: '1',
    title: 'Presentation de Leopardo RH',
    description: 'Decouvrez comment Leopardo RH peut transformer votre gestion des ressources humaines en quelques minutes.',
    thumbnail: '/images/videos/presentation.jpg',
    duration: '5:30',
    category: 'Presentation',
    youtubeId: '',
  },
  {
    id: '2',
    title: 'Configuration Initiale',
    description: 'Apprenez a configurer votre entreprise, ajouter vos premiers employes et parametrer les modules essentiels.',
    thumbnail: '/images/videos/configuration.jpg',
    duration: '8:45',
    category: 'Tutoriel',
    youtubeId: '',
  },
  {
    id: '3',
    title: 'Gestion des Conges et Absences',
    description: 'Tutoriel complet sur la gestion des conges : demande, validation, soldes, calendrier et rapports.',
    thumbnail: '/images/videos/conges.jpg',
    duration: '6:20',
    category: 'Tutoriel',
    youtubeId: '',
  },
  {
    id: '4',
    title: 'Pointage avec Bornes ZKTeco',
    description: 'Installation et configuration des bornes biometriques ZKTeco pour le pointage des employes.',
    thumbnail: '/images/videos/zkteco.jpg',
    duration: '10:15',
    category: 'Integration',
    youtubeId: '',
  },
  {
    id: '5',
    title: 'Generation des Bulletins de Paie',
    description: 'Comment generer les bulletins de paie multi-pays avec calcul automatique des cotisations et exports bancaires.',
    thumbnail: '/images/videos/paie.jpg',
    duration: '12:00',
    category: 'Tutoriel',
    youtubeId: '',
  },
  {
    id: '6',
    title: 'Application Mobile Employe',
    description: 'Tour complet de l\'application mobile : pointage, conges, fiches de paie, notifications et organigramme.',
    thumbnail: '/images/videos/mobile.jpg',
    duration: '7:30',
    category: 'Presentation',
    youtubeId: '',
  },
];

export default function VideosPage() {
  const [isDark, setIsDark] = useState(false);
  const [activeCategory, setActiveCategory] = useState('Tous');
  useScrollReveal();

  const categories = ['Tous', ...Array.from(new Set(videos.map(v => v.category)))];
  const filtered = activeCategory === 'Tous' ? videos : videos.filter(v => v.category === activeCategory);

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <HeroSection
        headline="Videos & Tutoriels"
        subheadline="Apprenez a utiliser Leopardo RH avec nos guides video"
        badge={{ text: 'Videos', icon: <Play className="w-3 h-3" /> }}
      />

      <section className="py-24">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-wrap gap-2 mb-10 justify-center">
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

          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            {filtered.map((video, i) => (
              <motion.div
                key={video.id}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.05 }}
                className="group bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden hover:shadow-xl transition-all cursor-pointer"
              >
                <div className="relative aspect-video bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                  <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                  <div className="w-16 h-16 rounded-full bg-white/90 dark:bg-white/20 flex items-center justify-center group-hover:scale-110 transition-transform z-10">
                    <Play className="w-7 h-7 text-emerald-600 ml-1" />
                  </div>
                  <span className="absolute bottom-3 right-3 px-2 py-1 bg-black/70 rounded text-white text-xs font-mono flex items-center gap-1 z-10">
                    <Clock className="w-3 h-3" />{video.duration}
                  </span>
                </div>
                <div className="p-5">
                  <div className="flex items-center gap-2 mb-2">
                    <Tag className="w-3 h-3 text-emerald-500" />
                    <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">{video.category}</span>
                  </div>
                  <h3 className="font-bold text-slate-900 dark:text-white mb-2 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                    {video.title}
                  </h3>
                  <p className="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">{video.description}</p>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTASection
        headline="Pret a Commencer ?"
        subheadline="Essayez Leopardo RH gratuitement pendant 14 jours"
        ctaPrimary={{ text: 'Essai Gratuit', href: '/demo' }}
        ctaSecondary={{ text: 'Nous Contacter', href: '/contact' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
