'use client';

import { useMemo, useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer, HeroSection, CTASection, useScrollReveal } from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getFaqPageContent } from '@/modules/vitrine/data/faq-page';
import { motion, AnimatePresence } from 'framer-motion';
import { HelpCircle, ChevronDown, Search } from 'lucide-react';

export default function FaqPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale, direction } = useVitrineLocale();
  useScrollReveal();
  const content = getFaqPageContent(locale);

  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState('__all');
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  const categories = useMemo(
    () => ['__all', ...Array.from(new Set(content.items.map((f) => f.category)))],
    [content.items]
  );

  const categoryLabel = (cat: string) =>
    cat === '__all' ? content.allCategory : content.categories[cat as keyof typeof content.categories] ?? cat;

  const filtered = content.items.filter((item) => {
    const matchesSearch =
      search === '' ||
      item.question.toLowerCase().includes(search.toLowerCase()) ||
      item.answer.toLowerCase().includes(search.toLowerCase());
    const matchesCategory = activeCategory === '__all' || item.category === activeCategory;
    return matchesSearch && matchesCategory;
  });

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />
      <HeroSection
        headline={content.hero.headline}
        subheadline={content.hero.subheadline}
        badge={{ text: content.hero.badge, icon: <HelpCircle className="w-3 h-3" /> }}
      />

      <section className="py-16 bg-white dark:bg-slate-950">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="relative mb-10">
            <Search className="absolute start-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
            <label htmlFor="faq-search" className="sr-only">
              {content.searchLabel}
            </label>
            <input
              id="faq-search"
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={content.searchPlaceholder}
              aria-label={content.searchLabel}
              className="w-full ps-12 pe-4 py-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
            />
          </div>

          <div className="flex flex-wrap gap-2 justify-center mb-12">
            {categories.map((cat) => (
              <button
                key={cat}
                onClick={() => setActiveCategory(cat)}
                className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                  activeCategory === cat
                    ? 'bg-emerald-600 text-white'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-slate-700'
                }`}
              >
                {categoryLabel(cat)}
              </button>
            ))}
          </div>

          {filtered.length === 0 ? (
            <p className="text-center text-slate-500 dark:text-slate-400 py-12">{content.noResults}</p>
          ) : (
            <div className="space-y-4">
              {filtered.map((item, i) => (
                <motion.div
                  key={i}
                  initial={{ opacity: 0, y: 10 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: i * 0.05 }}
                  className="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden"
                >
                  <button
                    onClick={() => setOpenIndex(openIndex === i ? null : i)}
                    aria-expanded={openIndex === i}
                    aria-controls={`faq-answer-${i}`}
                    className="w-full flex items-center justify-between gap-4 p-5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors text-start"
                  >
                    <span className="font-semibold text-slate-900 dark:text-white">{item.question}</span>
                    <ChevronDown
                      className={`w-5 h-5 text-slate-400 shrink-0 transition-transform ${openIndex === i ? 'rotate-180' : ''}`}
                    />
                  </button>
                  <AnimatePresence>
                    {openIndex === i && (
                      <motion.div
                        id={`faq-answer-${i}`}
                        role="region"
                        aria-label={item.question}
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="bg-slate-50 dark:bg-slate-800/60"
                      >
                        <p className="p-5 text-slate-600 dark:text-slate-300 leading-relaxed">{item.answer}</p>
                      </motion.div>
                    )}
                  </AnimatePresence>
                </motion.div>
              ))}
            </div>
          )}
        </div>
      </section>

      <CTASection
        headline={content.cta.headline}
        subheadline={content.cta.subheadline}
        ctaPrimary={{ text: content.cta.primary, href: '/contact' }}
        ctaSecondary={{ text: content.cta.secondary, href: '/demo' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
