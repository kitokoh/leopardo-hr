'use client'

import { motion } from 'framer-motion'
import { Quote, Star } from 'lucide-react'

export interface SocialProofTestimonialProps {
  quote: string
  authorName: string
  authorRole: string
  authorCompany: string
  authorInitials: string
  rating: number
  badge?: string
}

export function SocialProofTestimonial({
  quote,
  authorName,
  authorRole,
  authorCompany,
  authorInitials,
  rating,
  badge,
}: SocialProofTestimonialProps) {
  return (
    <section className="relative py-24 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/30 to-white dark:from-slate-950 dark:via-slate-900/30 dark:to-slate-950" />

      <motion.div
        initial={{ opacity: 0, y: 30 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.7 }}
        className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center"
      >
        {badge && (
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/[0.08] border border-amber-500/15 text-amber-700 dark:text-amber-400 text-sm font-semibold mb-8">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
            {badge}
          </div>
        )}

        <Quote className="w-12 h-12 text-amber-200 dark:text-amber-900/50 mx-auto mb-6" />

        <div className="flex justify-center gap-1 mb-6">
          {Array.from({ length: rating }).map((_, i) => (
            <Star key={i} className="w-5 h-5 fill-amber-400 text-amber-400" />
          ))}
        </div>

        <blockquote className="text-xl sm:text-2xl lg:text-3xl font-medium text-slate-700 dark:text-slate-200 leading-relaxed mb-10">
          &ldquo;{quote}&rdquo;
        </blockquote>

        <div className="flex items-center justify-center gap-4">
          <div className="w-14 h-14 rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center text-white text-lg font-black shadow-lg">
            {authorInitials}
          </div>
          <div className="text-left">
            <div className="font-bold text-slate-900 dark:text-white text-lg">{authorName}</div>
            <div className="text-sm text-slate-500 dark:text-slate-400">
              {authorRole} &mdash; {authorCompany}
            </div>
          </div>
        </div>
      </motion.div>
    </section>
  )
}
