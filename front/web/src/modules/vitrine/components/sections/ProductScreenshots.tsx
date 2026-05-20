'use client'

import { useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Monitor, Smartphone, LayoutGrid } from 'lucide-react'

export type ScreenshotItem = {
  id: string
  title: string
  description: string
  platform: 'admin' | 'mobile' | 'kiosk'
  aspectRatio?: string
}

export interface ProductScreenshotsProps {
  title: string
  titleHighlight: string
  subtitle: string
  badge?: string
  screenshots: ScreenshotItem[]
}

const platformConfig = {
  admin: { icon: Monitor, label: 'Admin Dashboard', color: 'emerald' },
  mobile: { icon: Smartphone, label: 'Mobile App', color: 'blue' },
  kiosk: { icon: LayoutGrid, label: 'Kiosk', color: 'purple' },
} as const

type Platform = keyof typeof platformConfig

export function ProductScreenshots({
  title,
  titleHighlight,
  subtitle,
  badge,
  screenshots,
}: ProductScreenshotsProps) {
  const platforms = [...new Set(screenshots.map((s) => s.platform))] as Platform[]
  const [activePlatform, setActivePlatform] = useState<Platform>(platforms[0] ?? 'admin')

  const filtered = screenshots.filter((s) => s.platform === activePlatform)

  return (
    <section className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/50 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-12"
        >
          {badge && (
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-500/[0.08] border border-blue-500/15 text-blue-700 dark:text-blue-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse" />
              {badge}
            </div>
          )}
          <h2 className="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
            {title}{' '}
            <span className="bg-gradient-to-r from-blue-500 to-violet-500 bg-clip-text text-transparent">
              {titleHighlight}
            </span>
          </h2>
          <p className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">{subtitle}</p>
        </motion.div>

        {/* Platform tabs */}
        <div className="flex justify-center gap-2 mb-12">
          {platforms.map((platform) => {
            const config = platformConfig[platform]
            const Icon = config.icon
            const isActive = platform === activePlatform
            return (
              <button
                key={platform}
                onClick={() => setActivePlatform(platform)}
                className={`inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 ${
                  isActive
                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-lg'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                }`}
              >
                <Icon className="w-4 h-4" />
                {config.label}
              </button>
            )
          })}
        </div>

        {/* Screenshot grid */}
        <AnimatePresence mode="wait">
          <motion.div
            key={activePlatform}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.4 }}
            className="grid grid-cols-1 md:grid-cols-2 gap-6"
          >
            {filtered.map((screenshot, index) => (
              <motion.div
                key={screenshot.id}
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.4, delay: index * 0.1 }}
                className="group relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden bg-white dark:bg-slate-900/80 transition-all duration-300 hover:shadow-xl"
              >
                {/* Placeholder screenshot area */}
                <div
                  className={`relative bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 ${screenshot.aspectRatio ?? 'aspect-video'} flex items-center justify-center`}
                >
                  <div className="text-center p-6">
                    {(() => {
                      const Icon = platformConfig[screenshot.platform].icon
                      return <Icon className="w-16 h-16 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                    })()}
                    <div className="text-sm font-medium text-slate-400 dark:text-slate-500">
                      {screenshot.title}
                    </div>
                  </div>
                </div>

                {/* Caption */}
                <div className="p-5">
                  <h3 className="font-bold text-slate-900 dark:text-white mb-1">{screenshot.title}</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400">{screenshot.description}</p>
                </div>
              </motion.div>
            ))}
          </motion.div>
        </AnimatePresence>
      </div>
    </section>
  )
}
