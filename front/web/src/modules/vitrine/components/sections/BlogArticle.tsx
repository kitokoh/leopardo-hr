'use client';

import { useMemo, useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Calendar, User, Clock, ArrowRight, Sparkles, AlertTriangle, Info } from 'lucide-react';
import { SocialShare } from '@/components/SocialShare';
import { SITE_URL } from '@/lib/site-url';
import Image from 'next/image'

import { localImageProps } from '@/modules/vitrine/lib/image-props';
import Link from 'next/link';
import { BlogPost } from '@/modules/vitrine/data/blog';

export interface BlogArticleProps {
  post: BlogPost;
  relatedPosts: BlogPost[];
  dateLocale?: string;
  readingTimeLabel?: string;
  tableOfContentsLabel?: string;
  authorRoleLabel?: string;
  relatedTitle?: string;
  locale?: string;
}

export function BlogArticle({
  post,
  relatedPosts,
  dateLocale = 'fr-FR',
  readingTimeLabel = 'min de lecture',
  tableOfContentsLabel = 'Table des matieres',
  authorRoleLabel = 'Auteur et expert en gestion RH',
  relatedTitle = 'Articles recommandes',
  locale = 'fr',
}: BlogArticleProps) {
  const [readingProgress, setReadingProgress] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
      if (totalHeight > 0) {
        setReadingProgress(Math.min(100, Math.max(0, (window.scrollY / totalHeight) * 100)));
      }
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const formattedDate = new Date(post.date).toLocaleDateString(dateLocale, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  // Generate table of contents from markdown headings
  const tableOfContents = useMemo(() => {
    const headings = post.content.match(/^## .+$/gm) || [];
    return headings.map((heading) => ({
      id: heading.replace(/^## /, '').toLowerCase().replace(/\s+/g, '-'),
      title: heading.replace(/^## /, ''),
    }));
  }, [post.content]);

  // #7870 : les guides « paie par pays » exigent un maillage interne
  // (/alternatives, /signup, pages modules) et la mise en évidence du statut
  // pilote. Le rendu ligne à ligne ne gérait ni les liens ni le gras : le
  // markdown `[label](href)` et `**gras**` s'affichait brut. Rendu inline
  // minimal — liens internes via <Link>, externes via <a>, gras via <strong>.
  const renderInline = (text: string, keyPrefix: string) => {
    const parts = text.split(/(\[[^\]]+\]\([^)\s]+\)|\*\*[^*]+\*\*)/g);
    return parts.map((part, i) => {
      const link = part.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);
      if (link) {
        const [, label, href] = link;
        if (href.startsWith('/')) {
          return (
            <Link
              key={`${keyPrefix}-${i}`}
              href={href}
              className="text-blue-600 dark:text-blue-400 font-semibold underline underline-offset-2 hover:text-blue-700 dark:hover:text-blue-300"
            >
              {label}
            </Link>
          );
        }
        return (
          <a
            key={`${keyPrefix}-${i}`}
            href={href}
            className="text-blue-600 dark:text-blue-400 font-semibold underline underline-offset-2 hover:text-blue-700 dark:hover:text-blue-300"
          >
            {label}
          </a>
        );
      }
      const bold = part.match(/^\*\*([^*]+)\*\*$/);
      if (bold) {
        return (
          <strong key={`${keyPrefix}-${i}`} className="font-bold text-slate-800 dark:text-slate-200">
            {bold[1]}
          </strong>
        );
      }
      return part;
    });
  };

  // Parse markdown to HTML enrichi (images, callouts, titres, listes)
  const renderMarkdown = (markdown: string) => {
    return markdown
      .split('\n')
      .map((line, index) => {
        // 1. Image Markdown : ![alt](src "caption") ou ![alt](src)
        const trimmed = line.trim();
        if (trimmed.startsWith('![') && trimmed.includes('](')) {
          const imgMatch = trimmed.match(/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)$/);
          if (imgMatch) {
            const [, alt, src, caption] = imgMatch;
            const figureCaption = caption || alt;
            return (
              <figure
                key={index}
                className="my-10 rounded-2xl overflow-hidden border border-slate-200/80 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 shadow-xl shadow-slate-900/5 group"
              >
                <div className="relative w-full aspect-[16/9] max-h-[460px] overflow-hidden bg-slate-100 dark:bg-slate-800">
                  <Image
                    src={src}
                    alt={alt || post.title}
                    fill
                    sizes="(max-width: 1024px) 100vw, 860px"
                    {...localImageProps(src)}
                    className="object-cover transition-transform duration-500 group-hover:scale-105"
                  />
                </div>
                {figureCaption && (
                  <figcaption className="px-5 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 bg-slate-100/70 dark:bg-slate-900/90 border-t border-slate-200/60 dark:border-slate-800">
                    <span className="font-bold text-slate-700 dark:text-slate-300">Illustration :</span>{' '}
                    {figureCaption}
                  </figcaption>
                )}
              </figure>
            );
          }
        }

        // 2. Callouts & Citations : > ...
        if (line.startsWith('> ')) {
          const quoteRaw = line.replace(/^>\s*/, '');
          const isTip = quoteRaw.includes('[!TIP]') || quoteRaw.toLowerCase().includes('astuce');
          const isWarning = quoteRaw.includes('[!WARNING]') || quoteRaw.toLowerCase().includes('attention');
          const isNote = quoteRaw.includes('[!NOTE]') || quoteRaw.toLowerCase().includes('à retenir');
          const cleanText = quoteRaw.replace(/\[!(TIP|NOTE|WARNING|IMPORTANT|CAUTION)\]\s*/i, '');

          if (isTip) {
            return (
              <div
                key={index}
                className="my-6 p-5 rounded-2xl bg-emerald-500/[0.08] border border-emerald-500/25 flex items-start gap-3.5"
              >
                <Sparkles className="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0 mt-0.5" />
                <div className="text-sm sm:text-base text-emerald-950 dark:text-emerald-200 leading-relaxed">
                  {renderInline(cleanText, `tip-${index}`)}
                </div>
              </div>
            );
          }
          if (isWarning) {
            return (
              <div
                key={index}
                className="my-6 p-5 rounded-2xl bg-amber-500/[0.08] border border-amber-500/25 flex items-start gap-3.5"
              >
                <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                <div className="text-sm sm:text-base text-amber-950 dark:text-amber-200 leading-relaxed">
                  {renderInline(cleanText, `warn-${index}`)}
                </div>
              </div>
            );
          }
          if (isNote) {
            return (
              <div
                key={index}
                className="my-6 p-5 rounded-2xl bg-cyan-500/[0.08] border border-cyan-500/25 flex items-start gap-3.5"
              >
                <Info className="w-5 h-5 text-cyan-600 dark:text-cyan-400 flex-shrink-0 mt-0.5" />
                <div className="text-sm sm:text-base text-cyan-950 dark:text-cyan-200 leading-relaxed">
                  {renderInline(cleanText, `note-${index}`)}
                </div>
              </div>
            );
          }
          return (
            <blockquote
              key={index}
              className="my-6 pl-5 py-2 border-l-4 border-amber-400/80 bg-amber-400/[0.04] rounded-r-xl italic text-slate-700 dark:text-slate-300"
            >
              {renderInline(cleanText, `quote-${index}`)}
            </blockquote>
          );
        }
        if (line.startsWith('# ')) {
          // Un titre de niveau 1 dans le corps de l'article produisait un
          // SECOND <h1> sur la page (le premier étant le titre de l'article) :
          // la hiérarchie de titres devenait ambiguë pour les lecteurs d'écran
          // comme pour les moteurs. Le titre d'article reste le seul <h1>,
          // les intertitres sont des <h2>.
          return (
            <h2 key={index} className="text-4xl font-black text-slate-900 dark:text-white mt-8 mb-4">
              {line.replace(/^# /, '')}
            </h2>
          );
        }
        if (line.startsWith('## ')) {
          const id = line.replace(/^## /, '').toLowerCase().replace(/\s+/g, '-');
          return (
            <h2
              key={index}
              id={id}
              className="text-2xl font-bold text-slate-900 dark:text-white mt-6 mb-3 scroll-mt-20"
            >
              {line.replace(/^## /, '')}
            </h2>
          );
        }
        if (line.startsWith('### ')) {
          return (
            <h3 key={index} className="text-xl font-bold text-slate-900 dark:text-white mt-4 mb-2">
              {line.replace(/^### /, '')}
            </h3>
          );
        }
        if (line.startsWith('- ')) {
          return (
            <li key={index} className="text-slate-600 dark:text-slate-400 ml-6 mb-2">
              {renderInline(line.replace(/^- /, ''), `li-${index}`)}
            </li>
          );
        }
        if (line.trim() === '') {
          return <div key={index} className="mb-4" />;
        }
        return (
          <p key={index} className="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
            {renderInline(line, `p-${index}`)}
          </p>
        );
      });
  };

  // #AI-SEO : l'URL est conservée pour le partage social. Le JSON-LD Article
  // est émis côté serveur (voir blog/[slug]/layout.tsx) — un composant client
  // ne produirait le balisage qu'après exécution du JS, invisible pour les
  // crawlers IA qui n'exécutent pas JavaScript.
  const siteOrigin = typeof window !== 'undefined' ? window.location.origin : SITE_URL;
  const localeQuery = locale && locale !== 'fr' ? `?lang=${locale}` : '';
  const articleUrl = `${siteOrigin}/blog/${post.slug}${localeQuery}`;

  return (
    <>
    {/* Reading Progress Bar */}
    <div
      aria-hidden="true"
      className="fixed top-0 left-0 right-0 h-1 z-50 bg-slate-200/40 dark:bg-slate-800/40 backdrop-blur-sm pointer-events-none"
    >
      <div
        className="h-full bg-gradient-to-r from-emerald-500 via-teal-400 to-amber-400 transition-[width] duration-150 ease-out shadow-sm shadow-emerald-500/50"
        style={{ width: `${readingProgress}%` }}
      />
    </div>

    <div className="min-h-screen">
      {/* Hero Image */}
      <div className="relative w-full h-96 overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
        <Image
          src={post.image}
          alt={post.title}
          fill
          sizes="100vw"
          {...localImageProps(post.image)}
          className="object-cover"
          priority
        />
      </div>

      {/* Article Content */}
      <article className="relative py-16 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/50 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="mb-12"
          >
            {/* Category Badge */}
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider mb-4">
              {post.category}
            </div>

            {/* Title */}
            <h1 className="text-5xl sm:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              {post.title}
            </h1>

            {/* Meta */}
            <div className="flex flex-wrap items-center gap-6 text-slate-600 dark:text-slate-400 mb-8">
              <div className="flex items-center gap-2">
                <Calendar className="w-4 h-4" />
                {formattedDate}
              </div>
              <div className="flex items-center gap-2">
                <User className="w-4 h-4" />
                {post.author.name}
              </div>
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4" />
                {post.readingTime} {readingTimeLabel}
              </div>
            </div>

            {/* Social Sharing */}
            <SocialShare
              url={articleUrl}
              title={post.title}
              description={post.excerpt}
            />
          </motion.div>

          <div className="grid grid-cols-1 lg:grid-cols-4 gap-12">
            {/* Main Content */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.1 }}
              className="lg:col-span-3 prose dark:prose-invert max-w-none"
            >
              {renderMarkdown(post.content)}
            </motion.div>

            {/* Sidebar */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              className="lg:col-span-1"
            >
              {/* Table of Contents */}
              {tableOfContents.length > 0 && (
                <div className="sticky top-24 mb-8 p-6 rounded-xl bg-transparent dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800">
                  <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-4 uppercase tracking-wider">
                    {tableOfContentsLabel}
                  </h3>
                  <ul className="space-y-2">
                    {tableOfContents.map((item) => (
                      <li key={item.id}>
                        <a
                          href={`#${item.id}`}
                          className="text-sm text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                        >
                          {item.title}
                        </a>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {/* Author Card */}
              <div className="p-6 rounded-xl bg-transparent dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800">
                <div className="relative w-16 h-16 rounded-full overflow-hidden mb-4 flex-shrink-0">
                  <Image
                    src={post.author.avatar}
                    alt={post.author.name}
                    fill
                    sizes="64px"
                    {...localImageProps(post.author.avatar)}
                    className="object-cover"
                  />
                </div>
                <h4 className="font-bold text-slate-900 dark:text-white mb-1">
                  {post.author.name}
                </h4>
                <p className="text-sm text-slate-600 dark:text-slate-400">
                  {authorRoleLabel}
                </p>
              </div>
            </motion.div>
          </div>

          {/* Tags */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="mt-12 pt-8 border-t border-slate-200 dark:border-slate-800"
          >
            <div className="flex flex-wrap gap-2">
              {post.tags.map((tag) => (
                <Link
                  key={tag}
                  href={`/blog?category=${encodeURIComponent(post.category)}`}
                  className="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm font-medium hover:bg-emerald-100 dark:hover:bg-emerald-900/30 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors"
                >
                  #{tag}
                </Link>
              ))}
            </div>
          </motion.div>
        </div>
      </article>

      {/* Related Articles */}
      {relatedPosts.length > 0 && (
        <section className="relative py-32 overflow-hidden">
          <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 via-white to-slate-50/50 dark:from-slate-900/50 dark:via-slate-950 dark:to-slate-900/50" />

          <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6 }}
              className="text-center mb-16"
            >
              <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
                {relatedTitle}
              </h2>
            </motion.div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
              {relatedPosts.slice(0, 3).map((relatedPost, index) => (
                <motion.div
                  key={relatedPost.slug}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.6, delay: index * 0.1 }}
                  className="group relative"
                >
                  <Link href={`/blog/${relatedPost.slug}`}>
                    <div className="absolute -inset-px rounded-2xl bg-gradient-to-r from-emerald-500 to-cyan-500 opacity-0 group-hover:opacity-10 blur-xl transition-opacity duration-500" />

                    <div className="relative bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden transition-all duration-300 group-hover:border-emerald-200/50 dark:group-hover:border-emerald-800/50 group-hover:shadow-xl h-full flex flex-col cursor-pointer">
                      {/* Image */}
                      <div className="relative w-full h-40 overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
                        <Image
                          src={relatedPost.image}
                          alt={relatedPost.title}
                          fill
                          sizes="(min-width: 1024px) 33vw, 100vw"
                          {...localImageProps(relatedPost.image)}
                          className="object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                      </div>

                      {/* Content */}
                      <div className="p-6 flex flex-col flex-1">
                        <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors line-clamp-2">
                          {relatedPost.title}
                        </h3>
                        <p className="text-slate-600 dark:text-slate-400 text-sm leading-relaxed mb-4 flex-1 line-clamp-2">
                          {relatedPost.excerpt}
                        </p>
                        <div className="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
                          <span className="text-xs text-slate-500 dark:text-slate-400">
                            {relatedPost.readingTime} min
                          </span>
                          <ArrowRight className="w-4 h-4 text-emerald-700 dark:text-emerald-400 group-hover:translate-x-1 transition-transform" />
                        </div>
                      </div>
                    </div>
                  </Link>
                </motion.div>
              ))}
            </div>
          </div>
        </section>
      )}
    </div>
    </>
  );
}

