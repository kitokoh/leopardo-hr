'use client';

import { useState } from 'react';
import {
  Navbar,
  HeroSection,
  CTASection,
  Footer,
  useScrollReveal,
  BlogGrid,
} from '@/modules/vitrine';
import { blogPosts } from '@/modules/vitrine/data/blog';
import { motion } from 'framer-motion';
import { BookOpen } from 'lucide-react';

export default function BlogPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  // Transform blog posts to match BlogCardProps
  const blogCards = blogPosts.map((post) => ({
    slug: post.slug,
    title: post.title,
    excerpt: post.excerpt,
    image: post.image,
    date: post.date,
    author: post.author,
    category: post.category,
    readingTime: post.readingTime,
  }));

  // Get unique categories
  const categories = Array.from(new Set(blogPosts.map((post) => post.category)));

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* Hero Section */}
      <HeroSection
        headline="Blog & Resources"
        subheadline="Guides, articles et conseils pour optimiser votre gestion RH"
        ctaPrimary={{
          text: 'S\'inscrire à la Newsletter',
          href: '#newsletter',
        }}
        badge={{
          text: 'Contenu Gratuit',
          icon: <BookOpen className="w-3 h-3" />,
        }}
      />

      {/* Blog Grid */}
      <BlogGrid
        title="Nos Articles"
        subtitle="Découvrez nos derniers articles"
        posts={blogCards}
        categories={categories}
        itemsPerPage={9}
        showPagination={true}
        showFilters={true}
        badge={{
          text: 'Ressources',
          icon: <BookOpen className="w-3 h-3" />,
        }}
      />

      {/* Newsletter Section */}
      <section id="newsletter" className="relative py-32 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-emerald-500/10 via-cyan-500/10 to-emerald-500/10 dark:from-emerald-500/5 dark:via-cyan-500/5 dark:to-emerald-500/5" />

        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="text-center"
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              Newsletter
            </div>
            <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              Recevez nos Conseils Hebdomadaires
            </h2>
            <p className="text-lg text-slate-600 dark:text-slate-400 mb-8 leading-relaxed">
              Inscrivez-vous à notre newsletter pour recevoir les derniers articles, guides et conseils directement
              dans votre boîte mail.
            </p>

            {/* Newsletter Form */}
            <motion.form
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6, delay: 0.1 }}
              className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto"
              onSubmit={(e) => {
                e.preventDefault();
                // Handle newsletter signup
              }}
            >
              <input
                type="email"
                placeholder="Votre email"
                required
                className="flex-1 px-4 py-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all"
              />
              <button
                type="submit"
                className="px-6 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors whitespace-nowrap"
              >
                S&apos;inscrire
              </button>
            </motion.form>

            <p className="text-sm text-slate-500 dark:text-slate-400 mt-4">
              Pas de spam, juste des conseils utiles. Désinscription facile.
            </p>
          </motion.div>
        </div>
      </section>

      {/* CTA Section */}
      <CTASection
        headline="Besoin d'Aide?"
        subheadline="Contactez notre équipe pour des conseils personnalisés"
        ctaPrimary={{
          text: 'Nous Contacter',
          href: '/contact',
        }}
        ctaSecondary={{
          text: 'Essai gratuit',
          href: '/signup',
        }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
