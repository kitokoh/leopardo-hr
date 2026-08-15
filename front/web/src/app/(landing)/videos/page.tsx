'use client';

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, HeroSection, CTASection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Clock, MonitorPlay, PlayCircle, Video } from 'lucide-react';

/**
 * Vidéo démo réelle, commitée dans public/videos/ (product-demo.mp4/.webm,
 * poster + sous-titres FR/EN). Les tutoriels supplémentaires sont annoncés
 * honnêtement comme « Bientôt disponible » — aucun ID YouTube factice ni
 * thumbnail inexistant ne doit réapparaître ici.
 */
const upcomingVideos = [
  {
    title: 'Configuration du pointage ZKTeco',
    description: 'Connecter et configurer vos bornes biométriques ZKTeco avec Leopardo RH.',
    category: 'Tutoriel',
  },
  {
    title: 'Paie multi-pays : Algérie, Maroc, France',
    description: 'Générer des bulletins de paie conformes pour plusieurs pays depuis une seule interface.',
    category: 'Tutoriel',
  },
  {
    title: 'Application mobile pour les employés',
    description: 'Pointage, demandes de congés et consultation des bulletins depuis le smartphone.',
    category: 'Tutoriel',
  },
  {
    title: 'Intégration API et webhooks',
    description: 'Connecter Leopardo RH à vos outils existants via l\'API REST et les webhooks.',
    category: 'Intégration',
  },
  {
    title: 'Témoignage client : Atlas Digital',
    description: 'Comment Atlas Digital gère 350 employés sur 3 pays avec Leopardo RH.',
    category: 'Témoignage',
  },
];

export default function VideosPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  useScrollReveal();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <HeroSection
        headline="Vidéos & Démos"
        subheadline="Découvrez Leopardo RH en action à travers notre démo produit et nos tutoriels"
        ctaPrimary={{ text: 'Demander une démo live', href: '/demo' }}
        ctaSecondary={{ text: 'Essai gratuit', href: '/signup' }}
        badge={{ text: 'Vidéos', icon: <MonitorPlay className="w-3 h-3" /> }}
      />

      {/* Demo vidéo réelle */}
      <section className="py-16 px-4">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
            className="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm bg-slate-900"
          >
            <div className="px-6 py-4 bg-slate-900 flex items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                  <PlayCircle className="w-5 h-5 text-emerald-400" />
                </div>
                <div>
                  <h2 className="font-bold text-white text-lg">Présentation complète de Leopardo RH</h2>
                  <p className="text-slate-400 text-sm">Tour d&apos;horizon de la plateforme — pointage, paie, absences, mobile et kiosk.</p>
                </div>
              </div>
            </div>
            <video
              controls
              preload="metadata"
              playsInline
              poster="/videos/product-demo-poster.jpg"
              className="w-full aspect-video bg-black"
              aria-label="Vidéo de présentation de Leopardo RH"
            >
              <source src="/videos/product-demo.mp4" type="video/mp4" />
              <source src="/videos/product-demo.webm" type="video/webm" />
              <track
                kind="captions"
                srcLang="fr"
                src="/videos/product-demo.fr.vtt"
                label="Français"
                default
              />
              <track
                kind="captions"
                srcLang="en"
                src="/videos/product-demo.en.vtt"
                label="English"
              />
              Votre navigateur ne supporte pas la lecture vidéo HTML5.
            </video>
          </motion.div>
        </div>
      </section>

      {/* Tutoriels à venir — état honnête, pas de lecteur factice */}
      <section className="py-16 px-4 bg-transparent dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-5xl mx-auto">
          <h2 className="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white text-center mb-4">
            Plus de vidéos à venir
          </h2>
          <p className="text-slate-500 dark:text-slate-400 text-center mb-12 max-w-2xl mx-auto">
            Nous préparons de nouveaux tutoriels détaillés. En attendant, découvrez la démo ci-dessus ou
            réservez une démonstration en direct avec notre équipe.
          </p>

          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {upcomingVideos.map((video, i) => (
              <motion.div
                key={video.title}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.08 }}
                viewport={{ once: true }}
                className="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-shadow"
              >
                <div className="relative aspect-video bg-slate-100 dark:bg-slate-900 flex items-center justify-center">
                  <Video className="w-10 h-10 text-slate-300 dark:text-slate-700" />
                  <span className="absolute bottom-3 right-3 bg-black/70 text-white text-xs px-2 py-1 rounded-md flex items-center gap-1">
                    <Clock className="w-3 h-3" />À venir
                  </span>
                  <span className="absolute top-3 left-3 bg-slate-500/90 text-white text-xs px-2 py-1 rounded-md">
                    {video.category}
                  </span>
                </div>
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
