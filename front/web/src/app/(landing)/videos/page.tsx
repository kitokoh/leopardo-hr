'use client';

import { useState } from 'react';
import { Navbar, HeroSection, CTASection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Play, Clock, Tag, MonitorPlay } from 'lucide-react';

const videos = [
  {
    title: 'Présentation complète de Leopardo RH',
    description: 'Tour d\'horizon de toutes les fonctionnalités de la plateforme en 8 minutes.',
    category: 'Présentation',
    duration: '8:24',
    thumbnail: '/images/video-thumb-overview.jpg',
    youtubeId: 'demo-overview',
  },
  {
    title: 'Configuration du pointage ZKTeco',
    description: 'Comment connecter et configurer vos bornes biométriques ZKTeco avec Leopardo RH.',
    category: 'Tutoriel',
    duration: '5:12',
    thumbnail: '/images/video-thumb-zkteco.jpg',
    youtubeId: 'demo-zkteco',
  },
  {
    title: 'Paie multi-pays : Algérie, Maroc, France',
    description: 'Générer des bulletins de paie conformes pour 3 pays depuis une seule interface.',
    category: 'Tutoriel',
    duration: '6:45',
    thumbnail: '/images/video-thumb-payroll.jpg',
    youtubeId: 'demo-payroll',
  },
  {
    title: 'Application mobile pour les employés',
    description: 'Pointage, demandes de congés et consultation des bulletins depuis le smartphone.',
    category: 'Tutoriel',
    duration: '4:30',
    thumbnail: '/images/video-thumb-mobile.jpg',
    youtubeId: 'demo-mobile',
  },
  {
    title: 'Intégration API et webhooks',
    description: 'Connecter Leopardo RH à vos outils existants via l\'API REST et les webhooks.',
    category: 'Intégration',
    duration: '7:15',
    thumbnail: '/images/video-thumb-api.jpg',
    youtubeId: 'demo-api',
  },
  {
    title: 'Témoignage client : Atlas Industries',
    description: 'Comment Atlas Industries gère 350 employés sur 3 pays avec Leopardo RH.',
    category: 'Témoignage',
    duration: '3:50',
    thumbnail: '/images/video-thumb-case-study.jpg',
    youtubeId: 'case-atlas',
  },
];

const categories = ['Tous', 'Présentation', 'Tutoriel', 'Intégration', 'Témoignage'];

export default function VideosPage() {
  const [isDark, setIsDark] = useState(false);
  const [activeCategory, setActiveCategory] = useState('Tous');
  const [playingVideo, setPlayingVideo] = useState<string | null>(null);
  useScrollReveal();

  const filtered = activeCategory === 'Tous' ? videos : videos.filter(v => v.category === activeCategory);

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline="Vidéos & Démos"
        subheadline="Découvrez Leopardo RH en action à travers nos tutoriels et démonstrations"
        ctaPrimary={{ text: 'Demander une démo live', href: '/demo' }}
        ctaSecondary={{ text: 'Essai gratuit', href: '/signup' }}
        badge={{ text: 'Vidéos', icon: <MonitorPlay className="w-3 h-3" /> }}
      />

      {/* Category filter */}
      <section className="py-8 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap gap-3 justify-center">
          {categories.map(cat => (
            <button
              key={cat}
              onClick={() => setActiveCategory(cat)}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                activeCategory === cat
                  ? 'bg-emerald-600 text-white'
                  : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-emerald-300'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>
      </section>

      {/* Videos Grid */}
      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            {filtered.map((video, i) => (
              <motion.div
                key={video.youtubeId}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.08 }}
                viewport={{ once: true }}
                className="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-shadow group"
              >
                {/* Thumbnail / Player */}
                <div className="relative aspect-video bg-slate-900 flex items-center justify-center">
                  {playingVideo === video.youtubeId ? (
                    <div className="w-full h-full flex items-center justify-center bg-slate-900 text-slate-400 text-sm">
                      <p>Vidéo en cours de chargement...</p>
                    </div>
                  ) : (
                    <button
                      onClick={() => setPlayingVideo(video.youtubeId)}
                      className="absolute inset-0 flex items-center justify-center bg-gradient-to-t from-black/60 to-transparent group-hover:from-black/70 transition-all"
                      aria-label={`Lire ${video.title}`}
                    >
                      <div className="w-16 h-16 rounded-full bg-emerald-500 flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                        <Play className="w-7 h-7 text-white ml-1" fill="white" />
                      </div>
                    </button>
                  )}

                  {/* Duration badge */}
                  <span className="absolute bottom-3 right-3 bg-black/80 text-white text-xs px-2 py-1 rounded-md flex items-center gap-1">
                    <Clock className="w-3 h-3" />{video.duration}
                  </span>

                  {/* Category badge */}
                  <span className="absolute top-3 left-3 bg-emerald-500/90 text-white text-xs px-2 py-1 rounded-md flex items-center gap-1">
                    <Tag className="w-3 h-3" />{video.category}
                  </span>
                </div>

                {/* Info */}
                <div className="p-6">
                  <h3 className="font-bold text-slate-900 dark:text-white text-lg mb-2">{video.title}</h3>
                  <p className="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">{video.description}</p>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTASection
        title="Prêt à voir Leopardo RH en action ?"
        description="Réservez une démo personnalisée avec notre équipe"
        primaryCta={{ text: 'Réserver ma démo', href: '/demo' }}
        secondaryCta={{ text: 'Voir les tarifs', href: '/pricing' }}
      />

      <Footer />
    </div>
  );
}
