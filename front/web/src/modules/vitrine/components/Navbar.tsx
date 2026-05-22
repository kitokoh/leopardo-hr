'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { motion, AnimatePresence } from 'framer-motion'
import { ArrowRight, Globe, Menu, Moon, Sun, X } from 'lucide-react'
import { useVitrineLocale } from '../lib/vitrine-locale'

type Props = {
  isDark: boolean
  onToggleDark: () => void
}

const routeLinks = {
  fr: [
    { href: '/blog', label: 'Blog' },
    { href: '/guides/rh-startup', label: 'Guides' },
    { href: '/demo', label: 'Demo' },
  ],
  en: [
    { href: '/blog', label: 'Blog' },
    { href: '/guides/rh-startup', label: 'Guides' },
    { href: '/demo', label: 'Demo' },
  ],
  tr: [
    { href: '/blog', label: 'Blog' },
    { href: '/guides/rh-startup', label: 'Rehberler' },
    { href: '/demo', label: 'Demo' },
  ],
  ar: [
    { href: '/blog', label: 'Blog' },
    { href: '/guides/rh-startup', label: 'Guides' },
    { href: '/demo', label: 'Demo' },
  ],
} as const

export function Navbar({ isDark, onToggleDark }: Props) {
  const [scrolled, setScrolled] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const { copy, locale, options, setLocale } = useVitrineLocale()

  useEffect(() => {
    const handler = () => setScrolled(window.scrollY > 40)
    window.addEventListener('scroll', handler, { passive: true })
    return () => window.removeEventListener('scroll', handler)
  }, [])

  return (
    <motion.header
      initial={{ y: -100 }}
      animate={{ y: 0 }}
      transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
      className={`fixed top-0 inset-x-0 z-50 transition-all duration-500 ${
        scrolled
          ? 'bg-white/80 dark:bg-slate-950/80 backdrop-blur-2xl shadow-[0_1px_0_0_rgba(0,0,0,0.04)] dark:shadow-[0_1px_0_0_rgba(255,255,255,0.04)]'
          : 'bg-transparent'
      }`}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-20">
          <Link href="/" className="flex items-center gap-3 group">
            <div className="relative">
              <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 via-emerald-500 to-cyan-500 flex items-center justify-center shadow-lg shadow-emerald-500/25 group-hover:shadow-emerald-500/50 transition-all duration-300 group-hover:scale-105">
                <span className="text-white font-black text-lg">L</span>
              </div>
              <div className="absolute -inset-1 rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-500 blur-lg opacity-0 group-hover:opacity-40 transition-opacity duration-500" />
            </div>
            <div className="flex flex-col">
              <span className="font-black text-xl text-slate-900 dark:text-white leading-none">Leopardo</span>
              <span className="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">{copy.nav.brandTagline}</span>
            </div>
          </Link>

          <nav className="hidden lg:flex items-center gap-1">
            {copy.nav.sections.map((item) => (
              <Link
                key={item.id}
                href={`#${item.id}`}
                className="relative px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors rounded-lg hover:bg-slate-100/80 dark:hover:bg-slate-800/80"
              >
                {item.label}
              </Link>
            ))}
            {routeLinks[locale].map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="relative px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors rounded-lg hover:bg-slate-100/80 dark:hover:bg-slate-800/80"
              >
                {item.label}
              </Link>
            ))}
          </nav>

          <div className="flex items-center gap-3">
            <label className="hidden md:flex items-center gap-2 rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/80 px-3 py-2 text-sm text-slate-600 dark:text-slate-300">
              <Globe className="w-4 h-4" />
              <span className="sr-only">{copy.nav.localeLabel}</span>
              <select
                value={locale}
                onChange={(event) => setLocale(event.target.value as typeof locale)}
                className="bg-transparent outline-none"
                aria-label={copy.nav.localeLabel}
              >
                {options.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.nativeLabel}
                  </option>
                ))}
              </select>
            </label>

            <button
              onClick={onToggleDark}
              className="p-2.5 rounded-xl bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all"
              aria-label={copy.nav.themeLabel}
            >
              {isDark ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
            </button>

            <Link
              href="/auth/login"
              className="hidden sm:inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors"
            >
              {copy.nav.login}
            </Link>

            <Link
              href="/signup"
              className="hidden sm:inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-sm font-bold rounded-xl hover:from-emerald-600 hover:to-emerald-700 transition-all shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/40 hover:scale-[1.02] active:scale-[0.98]"
            >
              {copy.nav.trial}
              <ArrowRight className="w-4 h-4" />
            </Link>

            <button
              className="lg:hidden p-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              onClick={() => setMobileOpen(!mobileOpen)}
              aria-label={copy.nav.menuLabel}
            >
              {mobileOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>
        </div>
      </div>

      <AnimatePresence>
        {mobileOpen && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
            className="lg:hidden bg-white/95 dark:bg-slate-950/95 backdrop-blur-2xl border-t border-slate-200/50 dark:border-slate-800/50"
          >
            <div className="px-6 py-6 space-y-2">
              <div className="mb-4 flex items-center gap-2 rounded-xl border border-slate-200/80 dark:border-slate-800/80 px-4 py-3 text-sm text-slate-700 dark:text-slate-300">
                <Globe className="w-4 h-4" />
                <select
                  value={locale}
                  onChange={(event) => setLocale(event.target.value as typeof locale)}
                  className="w-full bg-transparent outline-none"
                  aria-label={copy.nav.localeLabel}
                >
                  {options.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.nativeLabel}
                    </option>
                  ))}
                </select>
              </div>

              {copy.nav.sections.map((item, index) => (
                <motion.div
                  key={item.id}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.05 }}
                >
                  <Link
                    href={`#${item.id}`}
                    className="block px-4 py-3 text-lg font-semibold text-slate-900 dark:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    onClick={() => setMobileOpen(false)}
                  >
                    {item.label}
                  </Link>
                </motion.div>
              ))}
              {routeLinks[locale].map((item, index) => (
                <motion.div
                  key={item.href}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: (copy.nav.sections.length + index) * 0.05 }}
                >
                  <Link
                    href={item.href}
                    className="block px-4 py-3 text-lg font-semibold text-slate-900 dark:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    onClick={() => setMobileOpen(false)}
                  >
                    {item.label}
                  </Link>
                </motion.div>
              ))}

              <div className="pt-4">
                <Link
                  href="/signup"
                  className="block w-full text-center py-3.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/20"
                  onClick={() => setMobileOpen(false)}
                >
                  {copy.nav.trial}
                </Link>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </motion.header>
  )
}
