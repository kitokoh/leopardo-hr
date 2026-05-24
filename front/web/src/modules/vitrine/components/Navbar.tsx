'use client'

import { useEffect, useRef, useState } from 'react'
import Link from 'next/link'
import { motion, AnimatePresence } from 'framer-motion'
import {
  ArrowRight,
  Book,
  BookOpen,
  ChevronDown,
  Download,
  FileText,
  Globe,
  HelpCircle,
  Laptop,
  Mail,
  Menu,
  MessageCircle,
  Monitor,
  Moon,
  PenTool,
  Smartphone,
  Sun,
  Users,
  X,
} from 'lucide-react'
import { useVitrineLocale } from '../lib/vitrine-locale'

type Props = {
  isDark: boolean
  onToggleDark: () => void
}

type DropdownItem = {
  href: string
  icon: React.ReactNode
  label: string
  description: string
}

type NavLink = {
  href: string
  label: string
}

type NavDropdown = {
  label: string
  items: DropdownItem[]
}

type NavEntry = NavLink | NavDropdown

function isDropdown(entry: NavEntry): entry is NavDropdown {
  return 'items' in entry
}

const navByLocale: Record<string, NavEntry[]> = {
  fr: [
    { href: '/pricing', label: 'Tarifs' },
    {
      label: 'Ressources',
      items: [
        { href: '/guides/rh-startup', icon: <BookOpen className="w-4 h-4" />, label: 'Guides', description: 'Bonnes pratiques RH et tutoriels' },
        { href: '/blog', icon: <PenTool className="w-4 h-4" />, label: 'Insights RH', description: 'Analyses, cas pratiques et idees de croissance' },
        { href: '/docs', icon: <Book className="w-4 h-4" />, label: 'Docs API', description: 'Guides techniques et integration' },
        { href: '/changelog', icon: <FileText className="w-4 h-4" />, label: 'Changelog', description: 'Dernieres mises a jour produit' },
      ],
    },
    { href: '/contact', label: 'Contact' },
    {
      label: 'Installer Leopardo',
      items: [
        { href: '/download?platform=windows', icon: <Monitor className="w-4 h-4" />, label: 'Windows', description: 'Client desktop pour ZKTeco et synchronisation' },
        { href: '/download?platform=macos', icon: <Laptop className="w-4 h-4" />, label: 'macOS', description: 'Espace bureau pour les equipes terrain' },
        { href: '/download?platform=android', icon: <Smartphone className="w-4 h-4" />, label: 'Android', description: 'Pointage mobile et self-service employe' },
        { href: '/download?platform=ios', icon: <Smartphone className="w-4 h-4" />, label: 'iPhone', description: 'Application mobile iOS pour employes et managers' },
      ],
    },
    {
      label: 'Communaute',
      items: [
        { href: '/contact?topic=community', icon: <Users className="w-4 h-4" />, label: 'Forum', description: 'Echangez avec la communaute' },
        { href: '/faq', icon: <HelpCircle className="w-4 h-4" />, label: 'FAQ', description: 'Questions frequentes' },
        { href: '/contact?topic=support', icon: <MessageCircle className="w-4 h-4" />, label: 'Support', description: 'Contactez notre equipe' },
      ],
    },
  ],
  en: [
    { href: '/pricing', label: 'Pricing' },
    {
      label: 'Resources',
      items: [
        { href: '/guides/rh-startup', icon: <BookOpen className="w-4 h-4" />, label: 'Guides', description: 'HR best practices and tutorials' },
        { href: '/blog', icon: <PenTool className="w-4 h-4" />, label: 'HR Insights', description: 'Analysis, playbooks and growth ideas' },
        { href: '/docs', icon: <Book className="w-4 h-4" />, label: 'API Docs', description: 'Technical guides and integration' },
        { href: '/changelog', icon: <FileText className="w-4 h-4" />, label: 'Changelog', description: 'Latest product updates' },
      ],
    },
    { href: '/contact', label: 'Contact' },
    {
      label: 'Install Leopardo',
      items: [
        { href: '/download?platform=windows', icon: <Monitor className="w-4 h-4" />, label: 'Windows', description: 'Desktop client for ZKTeco and sync' },
        { href: '/download?platform=macos', icon: <Laptop className="w-4 h-4" />, label: 'macOS', description: 'Desktop workspace for field teams' },
        { href: '/download?platform=android', icon: <Smartphone className="w-4 h-4" />, label: 'Android', description: 'Mobile attendance and employee self-service' },
        { href: '/download?platform=ios', icon: <Smartphone className="w-4 h-4" />, label: 'iPhone', description: 'iOS app for employees and managers' },
      ],
    },
    {
      label: 'Community',
      items: [
        { href: '/contact?topic=community', icon: <Users className="w-4 h-4" />, label: 'Forum', description: 'Connect with the community' },
        { href: '/faq', icon: <HelpCircle className="w-4 h-4" />, label: 'FAQ', description: 'Frequently asked questions' },
        { href: '/contact?topic=support', icon: <MessageCircle className="w-4 h-4" />, label: 'Support', description: 'Contact our team' },
      ],
    },
  ],
  tr: [
    { href: '/pricing', label: 'Fiyatlar' },
    {
      label: 'Kaynaklar',
      items: [
        { href: '/guides/rh-startup', icon: <BookOpen className="w-4 h-4" />, label: 'Rehberler', description: 'IK en iyi uygulamalari' },
        { href: '/blog', icon: <PenTool className="w-4 h-4" />, label: 'IK Icgoruleri', description: 'Analizler, rehberler ve buyume fikirleri' },
        { href: '/docs', icon: <Book className="w-4 h-4" />, label: 'API Dokumanlari', description: 'Teknik rehberler ve entegrasyon' },
        { href: '/changelog', icon: <FileText className="w-4 h-4" />, label: 'Degisiklikler', description: 'Son urun guncellemeleri' },
      ],
    },
    { href: '/contact', label: 'Iletisim' },
    {
      label: 'Leopardo yu Kur',
      items: [
        { href: '/download?platform=windows', icon: <Monitor className="w-4 h-4" />, label: 'Windows', description: 'ZKTeco ve senkronizasyon icin masaustu istemcisi' },
        { href: '/download?platform=macos', icon: <Laptop className="w-4 h-4" />, label: 'macOS', description: 'Saha ekipleri icin masaustu calisma alani' },
        { href: '/download?platform=android', icon: <Smartphone className="w-4 h-4" />, label: 'Android', description: 'Mobil yoklama ve calisan self-servis' },
        { href: '/download?platform=ios', icon: <Smartphone className="w-4 h-4" />, label: 'iPhone', description: 'Calisan ve yoneticiler icin iOS uygulamasi' },
      ],
    },
    {
      label: 'Topluluk',
      items: [
        { href: '/contact?topic=community', icon: <Users className="w-4 h-4" />, label: 'Forum', description: 'Toplulukla baglanti kurun' },
        { href: '/faq', icon: <HelpCircle className="w-4 h-4" />, label: 'SSS', description: 'Sik sorulan sorular' },
        { href: '/contact?topic=support', icon: <MessageCircle className="w-4 h-4" />, label: 'Destek', description: 'Ekibimize ulasin' },
      ],
    },
  ],
  ar: [
    { href: '/pricing', label: 'الأسعار' },
    {
      label: 'الموارد',
      items: [
        { href: '/guides/rh-startup', icon: <BookOpen className="w-4 h-4" />, label: 'أدلة', description: 'أفضل ممارسات الموارد البشرية' },
        { href: '/blog', icon: <PenTool className="w-4 h-4" />, label: 'مدونة', description: 'مقالات ورؤى الموارد البشرية' },
        { href: '/changelog', icon: <FileText className="w-4 h-4" />, label: 'سجل التغييرات', description: 'آخر تحديثات المنتج' },
      ],
    },
    { href: '/docs', label: 'الوثائق' },
    { href: '/contact', label: 'اتصل بنا' },
    {
      label: 'المجتمع',
      items: [
        { href: '/contact?topic=community', icon: <Users className="w-4 h-4" />, label: 'منتدى', description: 'تواصل مع المجتمع' },
        { href: '/faq', icon: <HelpCircle className="w-4 h-4" />, label: 'الأسئلة الشائعة', description: 'الأسئلة المتكررة' },
        { href: '/contact?topic=support', icon: <MessageCircle className="w-4 h-4" />, label: 'الدعم', description: 'تواصل مع فريقنا' },
      ],
    },
    { href: '/download', label: 'ليوباردو لويندوز' },
  ],
}

function DropdownMenu({ entry, onClose }: { entry: NavDropdown; onClose: () => void }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 8, scale: 0.96 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      exit={{ opacity: 0, y: 8, scale: 0.96 }}
      transition={{ duration: 0.15, ease: 'easeOut' }}
      className="absolute top-full left-1/2 -translate-x-1/2 pt-2 z-50"
    >
      <div className="w-72 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-xl shadow-slate-900/5 dark:shadow-black/20 p-2">
        {entry.items.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            onClick={onClose}
            className="flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors group"
          >
            <div className="mt-0.5 flex-shrink-0 w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/50 transition-colors">
              {item.icon}
            </div>
            <div>
              <div className="text-sm font-semibold text-slate-900 dark:text-white">{item.label}</div>
              <div className="text-xs text-slate-500 dark:text-slate-400">{item.description}</div>
            </div>
          </Link>
        ))}
      </div>
    </motion.div>
  )
}

export function Navbar({ isDark, onToggleDark }: Props) {
  const [scrolled, setScrolled] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [openDropdown, setOpenDropdown] = useState<string | null>(null)
  const dropdownTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const { copy, locale, options, setLocale } = useVitrineLocale()
  const entries = navByLocale[locale] ?? navByLocale.fr

  useEffect(() => {
    const handler = () => setScrolled(window.scrollY > 40)
    window.addEventListener('scroll', handler, { passive: true })
    return () => window.removeEventListener('scroll', handler)
  }, [])

  const handleDropdownEnter = (label: string) => {
    if (dropdownTimeoutRef.current) clearTimeout(dropdownTimeoutRef.current)
    setOpenDropdown(label)
  }

  const handleDropdownLeave = () => {
    dropdownTimeoutRef.current = setTimeout(() => setOpenDropdown(null), 150)
  }

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
            {entries.map((entry, i) =>
              isDropdown(entry) ? (
                <div
                  key={entry.label}
                  className="relative"
                  onMouseEnter={() => handleDropdownEnter(entry.label)}
                  onMouseLeave={handleDropdownLeave}
                >
                  <button
                    className="flex items-center gap-1 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors rounded-lg hover:bg-slate-100/80 dark:hover:bg-slate-800/80"
                    onClick={() => setOpenDropdown(openDropdown === entry.label ? null : entry.label)}
                    aria-expanded={openDropdown === entry.label}
                  >
                    {entry.label}
                    <ChevronDown className={`w-3.5 h-3.5 transition-transform ${openDropdown === entry.label ? 'rotate-180' : ''}`} />
                  </button>
                  <AnimatePresence>
                    {openDropdown === entry.label && (
                      <DropdownMenu entry={entry} onClose={() => setOpenDropdown(null)} />
                    )}
                  </AnimatePresence>
                </div>
              ) : (
                <Link
                  key={entry.href}
                  href={entry.href}
                  className={`relative px-4 py-2 text-sm font-medium transition-colors rounded-lg hover:bg-slate-100/80 dark:hover:bg-slate-800/80 ${
                    entry.href === '/download'
                      ? 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-1.5'
                      : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                  }`}
                >
                  {entry.href === '/download' && <Monitor className="w-3.5 h-3.5" />}
                  {entry.label}
                </Link>
              )
            )}
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
            className="lg:hidden bg-white/95 dark:bg-slate-950/95 backdrop-blur-2xl border-t border-slate-200/50 dark:border-slate-800/50 max-h-[80vh] overflow-y-auto"
          >
            <div className="px-6 py-6 space-y-1">
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

              {entries.map((entry, index) =>
                isDropdown(entry) ? (
                  <div key={entry.label}>
                    <motion.button
                      initial={{ opacity: 0, x: -20 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ delay: index * 0.05 }}
                      className="w-full flex items-center justify-between px-4 py-3 text-lg font-semibold text-slate-900 dark:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                      onClick={() => setOpenDropdown(openDropdown === entry.label ? null : entry.label)}
                    >
                      {entry.label}
                      <ChevronDown className={`w-4 h-4 transition-transform ${openDropdown === entry.label ? 'rotate-180' : ''}`} />
                    </motion.button>
                    <AnimatePresence>
                      {openDropdown === entry.label && (
                        <motion.div
                          initial={{ opacity: 0, height: 0 }}
                          animate={{ opacity: 1, height: 'auto' }}
                          exit={{ opacity: 0, height: 0 }}
                          className="pl-4 space-y-1 overflow-hidden"
                        >
                          {entry.items.map((item) => (
                            <Link
                              key={item.href}
                              href={item.href}
                              onClick={() => setMobileOpen(false)}
                              className="flex items-center gap-3 px-4 py-2.5 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            >
                              <span className="text-emerald-500">{item.icon}</span>
                              <div>
                                <div className="text-sm font-medium">{item.label}</div>
                                <div className="text-xs text-slate-400">{item.description}</div>
                              </div>
                            </Link>
                          ))}
                        </motion.div>
                      )}
                    </AnimatePresence>
                  </div>
                ) : (
                  <motion.div
                    key={entry.href}
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: index * 0.05 }}
                  >
                    <Link
                      href={entry.href}
                      className={`block px-4 py-3 text-lg font-semibold rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors ${
                        entry.href === '/download'
                          ? 'text-emerald-600 dark:text-emerald-400 flex items-center gap-2'
                          : 'text-slate-900 dark:text-white'
                      }`}
                      onClick={() => setMobileOpen(false)}
                    >
                      {entry.href === '/download' && <Monitor className="w-5 h-5" />}
                      {entry.label}
                    </Link>
                  </motion.div>
                )
              )}

              <div className="pt-4 space-y-2">
                <Link
                  href="/auth/login"
                  className="block w-full text-center py-3 text-slate-700 dark:text-slate-300 font-semibold rounded-xl border border-slate-200 dark:border-slate-800"
                  onClick={() => setMobileOpen(false)}
                >
                  {copy.nav.login}
                </Link>
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
