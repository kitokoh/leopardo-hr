'use client'

import { motion } from 'framer-motion'
import { TrendingUp, ArrowRight } from 'lucide-react'

export type MiniCaseItem = {
  company: string
  country: string
  sector: string
  metric: string
  metricLabel: string
  description: string
}

export interface SocialProofCasesProps {
  title: string
  titleHighlight: string
  subtitle: string
  badge?: string
  cases: MiniCaseItem[]
}

const countryFlags: Record<string, string> = {
  DZ: '🇩🇿',
  MA: '🇲🇦',
  SN: '🇸🇳',
  TN: '🇹🇳',
  CI: '🇨🇮',
  TR: '🇹🇷',
  FR: '🇫🇷',
}

export function SocialProofCases({
  title,
  titleHighlight,
  subtitle,
  badge,
  cases,
}: SocialProofCasesProps) {
  return (
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
          {badge && (
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {badge}
            </div>
          )}
          <h2 className="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
            {title}{' '}
            <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
              {titleHighlight}
            </span>
          </h2>
          <p className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">{subtitle}</p>
        </motion.div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {cases.map((item, index) => (
            <motion.div
              key={item.company}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: index * 0.1 }}
              className="group relative bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 transition-all duration-300 hover:shadow-lg hover:border-emerald-200/50 dark:hover:border-emerald-800/50"
            >
              <div className="flex items-center gap-3 mb-4">
                <span className="text-2xl">{countryFlags[item.country] ?? '🌍'}</span>
                <div>
                  <div className="font-bold text-slate-900 dark:text-white">{item.company}</div>
                  <div className="text-xs text-slate-500">{item.sector}</div>
                </div>
              </div>

              <div className="flex items-center gap-2 mb-3">
                <TrendingUp className="w-5 h-5 text-emerald-500" />
                <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{item.metric}</span>
                <span className="text-sm text-slate-500">{item.metricLabel}</span>
              </div>

              <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{item.description}</p>

              <div className="mt-4 flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400 opacity-0 group-hover:opacity-100 transition-opacity">
                <span>Lire le cas complet</span>
                <ArrowRight className="w-3 h-3" />
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  )
}
