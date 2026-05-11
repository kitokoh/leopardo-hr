'use client';

import { motion } from 'framer-motion';
import { Star } from 'lucide-react';
import Image from 'next/image';

export interface TestimonialCardProps {
  quote: string;
  author: string;
  role: string;
  company: string;
  avatar: string;
  rating: number;
  index?: number;
}

export function TestimonialCard({
  quote,
  author,
  role,
  company,
  avatar,
  rating,
  index = 0,
}: TestimonialCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.6, delay: index * 0.1, ease: [0.22, 1, 0.36, 1] }}
      whileHover={{ y: -8, transition: { duration: 0.25 } }}
      className="group relative"
    >
      {/* Glow effect */}
      <div className="absolute -inset-px rounded-3xl bg-gradient-to-r from-emerald-500 to-cyan-500 opacity-0 group-hover:opacity-10 blur-xl transition-opacity duration-500" />

      <div className="relative bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-3xl border border-slate-200/80 dark:border-slate-800/80 p-8 transition-all duration-300 group-hover:border-emerald-200/50 dark:group-hover:border-emerald-800/50 group-hover:shadow-xl">
        {/* Rating */}
        <div className="flex gap-1 mb-6">
          {Array.from({ length: 5 }).map((_, i) => (
            <Star
              key={i}
              className={`w-5 h-5 ${i < rating ? 'fill-amber-400 text-amber-400' : 'text-slate-300 dark:text-slate-600'}`}
            />
          ))}
        </div>

        {/* Quote */}
        <p className="text-slate-700 dark:text-slate-300 text-lg leading-relaxed mb-8 font-light italic">
          &quot;{quote}&quot;
        </p>

        {/* Author */}
        <div className="flex items-center gap-4">
          <div className="relative w-12 h-12 rounded-full overflow-hidden flex-shrink-0">
            <Image
              src={avatar}
              alt={author}
              fill
              className="object-cover"
            />
          </div>
          <div>
            <div className="font-bold text-slate-900 dark:text-white">{author}</div>
            <div className="text-sm text-slate-500 dark:text-slate-400">
              {role} at {company}
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
