'use client'

import { motion } from 'framer-motion'
import { Building2, Users, Clock, Star } from 'lucide-react'

export type MetricItem = {
  icon: 'building' | 'users' | 'clock' | 'star'
  value: string
  label: string
}

export interface SocialProofMetricsProps {
  metrics: MetricItem[]
}

const iconMap = {
  building: Building2,
  users: Users,
  clock: Clock,
  star: Star,
} as const

export function SocialProofMetrics({ metrics }: SocialProofMetricsProps) {
  return (
    <section className="relative py-16 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 dark:from-emerald-800 dark:to-cyan-800" />
      <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMSIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA4KSIvPjwvc3ZnPg==')] opacity-50" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
          {metrics.map((metric, index) => {
            const Icon = iconMap[metric.icon]
            return (
              <motion.div
                key={metric.label}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="text-center"
              >
                <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-sm mb-4">
                  <Icon className="w-6 h-6 text-white" />
                </div>
                <div className="text-3xl sm:text-4xl font-black text-white mb-1">{metric.value}</div>
                <div className="text-sm text-white/70 font-medium">{metric.label}</div>
              </motion.div>
            )
          })}
        </div>
      </div>
    </section>
  )
}
