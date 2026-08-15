'use client';

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, HeroSection, CTASection, Footer, useScrollReveal } from '@/modules/vitrine';
import { TestimonialCard } from '@/modules/vitrine/components/sections/TestimonialCard';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getTestimonials } from '@/modules/vitrine/data/testimonials';
import { getTestimonialsPageContent } from '@/modules/vitrine/data/testimonials-page';
import { motion } from 'framer-motion';
import { Star } from 'lucide-react';

export default function TestimonialsPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale } = useVitrineLocale();
  useScrollReveal();
  const content = getTestimonialsPageContent(locale);
  const testimonials = getTestimonials(locale);

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <HeroSection
        headline={content.hero.headline}
        subheadline={content.hero.subheadline}
        ctaPrimary={{ text: content.hero.ctaPrimary, href: '/signup' }}
        ctaSecondary={{ text: content.hero.ctaSecondary, href: '/case-studies' }}
        badge={{ text: content.hero.badge, icon: <Star className="w-3 h-3" /> }}
      />

      {/* Stats Banner — données de démonstration, étiquetées honnêtement (#2726) */}
      <section className="py-16 bg-emerald-600 dark:bg-emerald-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            {content.stats.items.map((stat, i) => (
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
          <p className="mt-8 text-center text-emerald-100/80 text-xs">
            {content.stats.footnote}
          </p>
        </div>
      </section>

      {/* Testimonials Grid — cartes marquées DÉMO (TestimonialCard, #2726) */}
      <section className="py-24 bg-transparent dark:bg-slate-900">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {testimonials.map((t, i) => (
              <TestimonialCard
                key={i}
                index={i}
                quote={t.content}
                author={t.name}
                role={t.role}
                company={t.company}
                avatar={t.avatar}
                rating={t.rating}
              />
            ))}
          </div>
        </div>
      </section>

      <CTASection
        headline={content.cta.headline}
        subheadline={content.cta.subheadline}
        ctaPrimary={{ text: content.cta.primary, href: '/signup' }}
        ctaSecondary={{ text: content.cta.secondary, href: '/demo' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
