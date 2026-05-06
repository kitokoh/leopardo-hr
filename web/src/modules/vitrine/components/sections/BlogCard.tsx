'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight, Calendar, User } from 'lucide-react';
import Image from 'next/image';

export interface BlogCardProps {
  slug: string;
  title: string;
  excerpt: string;
  image: string;
  date: Date | string;
  author: {
    name: string;
    avatar: string;
  };
  category: string;
  readingTime?: number;
  index?: number;
}

export function BlogCard({
  slug,
  title,
  excerpt,
  image,
  date,
  author,
  category,
  readingTime,
  index = 0,
}: BlogCardProps) {
  const formattedDate = new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.6, delay: index * 0.08, ease: [0.22, 1, 0.36, 1] }}
      whileHover={{ y: -8, transition: { duration: 0.25 } }}
      className="group relative"
    >
      {/* Glow effect */}
      <div className="absolute -inset-px rounded-3xl bg-gradient-to-r from-emerald-500 to-cyan-500 opacity-0 group-hover:opacity-10 blur-xl transition-opacity duration-500" />

      <Link href={`/blog/${slug}`}>
        <div className="relative bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-3xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden transition-all duration-300 group-hover:border-emerald-200/50 dark:group-hover:border-emerald-800/50 group-hover:shadow-xl h-full flex flex-col cursor-pointer">
          {/* Image */}
          <div className="relative w-full h-48 overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
            <Image
              src={image}
              alt={title}
              fill
              className="object-cover group-hover:scale-105 transition-transform duration-300"
            />
            {/* Category badge */}
            <div className="absolute top-4 left-4">
              <div className="px-3 py-1 rounded-full bg-emerald-500/90 text-white text-xs font-bold uppercase tracking-wider backdrop-blur-sm">
                {category}
              </div>
            </div>
          </div>

          {/* Content */}
          <div className="p-6 flex flex-col flex-1">
            {/* Meta */}
            <div className="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400 mb-4">
              <div className="flex items-center gap-1">
                <Calendar className="w-3.5 h-3.5" />
                {formattedDate}
              </div>
              {readingTime && <span>{readingTime} min de lecture</span>}
            </div>

            {/* Title */}
            <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-3 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors line-clamp-2">
              {title}
            </h3>

            {/* Excerpt */}
            <p className="text-slate-600 dark:text-slate-400 text-sm leading-relaxed mb-6 flex-1 line-clamp-2">
              {excerpt}
            </p>

            {/* Author + CTA */}
            <div className="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <div className="relative w-8 h-8 rounded-full overflow-hidden flex-shrink-0">
                  <Image
                    src={author.avatar}
                    alt={author.name}
                    fill
                    className="object-cover"
                  />
                </div>
                <span className="text-xs font-medium text-slate-600 dark:text-slate-400">{author.name}</span>
              </div>
              <ArrowRight className="w-4 h-4 text-emerald-600 dark:text-emerald-400 group-hover:translate-x-1 transition-transform" />
            </div>
          </div>
        </div>
      </Link>
    </motion.div>
  );
}
