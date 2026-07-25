'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { PlayCircle } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

/**
 * PA2-MKT-014: short real product demo video, built from the actual
 * captured web/admin/mobile clips already committed under
 * `assets/videos/{landing,admin,mobile}_demo.webm` (previously unused on
 * the vitrine, only referenced in README.md). Concatenated + normalized
 * into `public/videos/product-demo.{mp4,webm}` (~64s, within the 60-120s
 * target), with fr/en WebVTT captions and a real poster frame — no stock
 * footage, no placeholder.
 */

type Copy = {
  title: string;
  subtitle: string;
  playLabel: string;
};

const copyByLocale: Record<AppLocale, Copy> = {
  fr: {
    title: 'Voyez Leopardo RH en action',
    subtitle: 'Vitrine, dashboard admin et application mobile employee, captures reelles du produit.',
    playLabel: 'Lire la video de demonstration',
  },
  en: {
    title: 'See Leopardo RH in action',
    subtitle: 'Marketing site, admin dashboard and employee mobile app, real product captures.',
    playLabel: 'Play the product demo video',
  },
  tr: {
    title: "Leopardo RH'yi is basinda gorun",
    subtitle: 'Vitrin, admin paneli ve calisan mobil uygulamasi, gercek urun kayitlari.',
    playLabel: 'Urun demo videosunu oynat',
  },
  ar: {
    title: 'شاهد Leopardo RH في العمل',
    subtitle: 'الموقع التسويقي ولوحة تحكم الإدارة وتطبيق الموظف، لقطات حقيقية من المنتج.',
    playLabel: 'تشغيل فيديو العرض التوضيحي',
  },
};

export interface ProductDemoVideoProps {
  locale?: AppLocale;
}

export function ProductDemoVideo({ locale = 'fr' }: ProductDemoVideoProps) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;
  const [isPlaying, setIsPlaying] = useState(false);
  const captionLang = locale === 'ar' || locale === 'tr' ? 'en' : locale;

  return (
    <section className="relative py-20 overflow-hidden bg-white dark:bg-slate-950">
      <div className="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-10"
        >
          <h2 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white mb-3">
            {copy.title}
          </h2>
          <p className="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
            {copy.subtitle}
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 30, scale: 0.98 }}
          whileInView={{ opacity: 1, y: 0, scale: 1 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6, delay: 0.1 }}
          className="relative rounded-2xl overflow-hidden shadow-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-900"
        >
          <div className="flex items-center gap-1.5 h-8 px-4 bg-slate-800">
            <span className="w-2.5 h-2.5 rounded-full bg-red-500/60" />
            <span className="w-2.5 h-2.5 rounded-full bg-amber-500/60" />
            <span className="w-2.5 h-2.5 rounded-full bg-green-500/60" />
          </div>

          <div className="relative aspect-video bg-black">
            <video
              className="w-full h-full"
              controls
              playsInline
              preload="metadata"
              poster="/videos/product-demo-poster.jpg"
              onPlay={() => setIsPlaying(true)}
              data-testid="product-demo-video"
            >
              <source src="/videos/product-demo.webm" type="video/webm" />
              <source src="/videos/product-demo.mp4" type="video/mp4" />
              <track
                kind="captions"
                srcLang="fr"
                label="Francais"
                src="/videos/product-demo.fr.vtt"
                default={captionLang === 'fr'}
              />
              <track
                kind="captions"
                srcLang="en"
                label="English"
                src="/videos/product-demo.en.vtt"
                default={captionLang === 'en'}
              />
            </video>

            {!isPlaying && (
              <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                <PlayCircle
                  className="w-16 h-16 text-white/90 drop-shadow-lg"
                  aria-label={copy.playLabel}
                />
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </section>
  );
}
