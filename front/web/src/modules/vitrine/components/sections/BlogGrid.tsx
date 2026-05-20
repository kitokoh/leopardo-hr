'use client';

import { useState, useMemo } from 'react';
import { motion } from 'framer-motion';
import { BlogCard, type BlogCardProps } from './BlogCard';

export interface BlogGridProps {
  title: string;
  subtitle: string;
  badge?: {
    text: string;
    icon?: React.ReactNode;
  };
  posts: BlogCardProps[];
  categories?: string[];
  itemsPerPage?: number;
  showPagination?: boolean;
  showFilters?: boolean;
  allLabel?: string;
  previousLabel?: string;
  nextLabel?: string;
  dateLocale?: string;
  readingTimeLabel?: string;
}

export function BlogGrid({
  title,
  subtitle,
  badge,
  posts,
  categories,
  itemsPerPage = 9,
  showPagination = true,
  showFilters = true,
  allLabel = 'Tous',
  previousLabel = 'Precedent',
  nextLabel = 'Suivant',
  dateLocale = 'fr-FR',
  readingTimeLabel = 'min de lecture',
}: BlogGridProps) {
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);

  // Filter posts
  const filteredPosts = useMemo(() => {
    if (!selectedCategory) return posts;
    return posts.filter((post) => post.category === selectedCategory);
  }, [posts, selectedCategory]);

  // Paginate posts
  const totalPages = Math.ceil(filteredPosts.length / itemsPerPage);
  const paginatedPosts = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return filteredPosts.slice(startIndex, startIndex + itemsPerPage);
  }, [filteredPosts, currentPage, itemsPerPage]);

  // Reset to page 1 when category changes
  const handleCategoryChange = (category: string | null) => {
    setSelectedCategory(category);
    setCurrentPage(1);
  };

  // Get unique categories
  const uniqueCategories = useMemo(() => {
    if (categories) return categories;
    return Array.from(new Set(posts.map((post) => post.category)));
  }, [posts, categories]);

  return (
    <section className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 via-white to-slate-50/50 dark:from-slate-900/50 dark:via-slate-950 dark:to-slate-900/50" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
        >
          {badge && (
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              {badge.icon && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />}
              {badge.text}
            </div>
          )}
          <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
            {title}
            {subtitle && (
              <span className="block bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                {subtitle}
              </span>
            )}
          </h2>
        </motion.div>

        {/* Category filters */}
        {showFilters && uniqueCategories.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="flex flex-wrap gap-3 justify-center mb-12"
          >
            <button
              onClick={() => handleCategoryChange(null)}
              className={`px-4 py-2 rounded-full font-medium transition-all duration-300 ${
                selectedCategory === null
                  ? 'bg-emerald-500 text-white'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white hover:bg-slate-200 dark:hover:bg-slate-700'
              }`}
            >
              {allLabel} ({posts.length})
            </button>
            {uniqueCategories.map((category) => {
              const count = posts.filter((post) => post.category === category).length;
              return (
                <button
                  key={category}
                  onClick={() => handleCategoryChange(category)}
                  className={`px-4 py-2 rounded-full font-medium transition-all duration-300 ${
                    selectedCategory === category
                      ? 'bg-emerald-500 text-white'
                      : 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white hover:bg-slate-200 dark:hover:bg-slate-700'
                  }`}
                >
                  {category} ({count})
                </button>
              );
            })}
          </motion.div>
        )}

        {/* Blog Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
          {paginatedPosts.map((post, index) => (
            <BlogCard
              key={post.slug}
              {...post}
              index={index}
              dateLocale={dateLocale}
              readingTimeLabel={readingTimeLabel}
            />
          ))}
        </div>

        {/* Pagination */}
        {showPagination && totalPages > 1 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="flex items-center justify-center gap-2"
          >
            <button
              onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
              disabled={currentPage === 1}
              className="px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
            >
              {previousLabel}
            </button>

            {Array.from({ length: totalPages }).map((_, i) => {
              const page = i + 1;
              return (
                <button
                  key={page}
                  onClick={() => setCurrentPage(page)}
                  className={`w-10 h-10 rounded-lg font-medium transition-all duration-300 ${
                    currentPage === page
                      ? 'bg-emerald-500 text-white'
                      : 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white hover:bg-slate-200 dark:hover:bg-slate-700'
                  }`}
                >
                  {page}
                </button>
              );
            })}

            <button
              onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
              disabled={currentPage === totalPages}
              className="px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
            >
              {nextLabel}
            </button>
          </motion.div>
        )}
      </div>
    </section>
  );
}
