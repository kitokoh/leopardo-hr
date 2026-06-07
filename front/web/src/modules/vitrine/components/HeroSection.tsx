'use client'

import Link from 'next/link'
import { motion, useScroll, useSpring, useTransform } from 'framer-motion'
import { AlertCircle, ArrowRight, CheckCircle, Download, Mail, Play, Smartphone, Sparkles, Star, TrendingUp, Users, Zap } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import type { AppLocale } from '@/lib/i18n'
import { useVitrineLocale } from '../lib/vitrine-locale'
import { ParticleField } from './ParticleField'

function AnimatedCounter({ value, suffix = '' }: { value: number; suffix?: string }) {
  const [count, setCount] = useState(0)
  const ref = useRef<HTMLSpanElement>(null)

  useEffect(() => {
    const el = ref.current
    if (!el) return

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          let start = 0
          const step = value / 120
          const timer = setInterval(() => {
            start += step
            if (start >= value) {
              setCount(value)
              clearInterval(timer)
            } else {
              setCount(Math.floor(start))
            }
          }, 16)
          observer.disconnect()
        }
      },
      { threshold: 0.5 },
    )

    observer.observe(el)
    return () => observer.disconnect()
  }, [value])

  return (
    <span ref={ref} className="tabular-nums">
      {count.toLocaleString()}
      {suffix}
    </span>
  )
}

const statIcons = [TrendingUp, Users, Zap, Star]

type QuickTrialCopy = {
  placeholder: string
  submit: string
  submitting: string
  legal: string
  success: string
  error: string
}

function deriveCompanyFromEmail(email: string): string {
  const domain = email.split('@')[1]?.split('.')[0]?.trim()

  if (!domain || domain.length < 2) {
    return 'Demande essai Leopardo'
  }

  return domain
    .replace(/[-_]+/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function QuickTrialEmailForm({ locale, copy }: { locale: AppLocale; copy: QuickTrialCopy }) {
  const [email, setEmail] = useState('')
  const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle')
  const [message, setMessage] = useState('')

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const normalizedEmail = email.trim().toLowerCase()

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) {
      setStatus('error')
      setMessage(copy.error)
      return
    }

    setStatus('submitting')
    setMessage('')

    try {
      const response = await fetch('/api/forms/signup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: normalizedEmail,
          company: deriveCompanyFromEmail(normalizedEmail),
          role: 'operations',
          employees: '1-10',
          locale,
          page: '/',
          source: 'hero_email_trial',
          timestamp: new Date().toISOString(),
        }),
      })

      if (!response.ok) {
        throw new Error('trial_request_failed')
      }

      setStatus('success')
      setMessage(copy.success)
      setEmail('')
    } catch {
      setStatus('error')
      setMessage(copy.error)
    }
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 18 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.8, delay: 0.62 }}
      className="mx-auto mt-8 max-w-2xl"
    >
      <form
        onSubmit={onSubmit}
        className="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-2xl shadow-emerald-500/10 backdrop-blur dark:border-slate-800/80 dark:bg-slate-900/85 sm:flex-row"
      >
        <label className="flex min-h-14 flex-1 items-center gap-3 rounded-xl bg-slate-50 px-4 text-left dark:bg-slate-950/60">
          <Mail className="h-5 w-5 flex-shrink-0 text-emerald-500" />
          <span className="sr-only">Email</span>
          <input
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            type="email"
            required
            autoComplete="email"
            placeholder={copy.placeholder}
            className="w-full bg-transparent text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400 dark:text-white"
          />
        </label>
        <button
          type="submit"
          disabled={status === 'submitting' || status === 'success'}
          className="inline-flex min-h-14 items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 text-sm font-black text-white shadow-lg shadow-emerald-500/20 transition hover:from-emerald-600 hover:to-cyan-600 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {status === 'submitting' ? copy.submitting : copy.submit}
        </button>
      </form>

      <p className="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">{copy.legal}</p>

      {message && (
        <div
          className={`mt-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ${
            status === 'success'
              ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
              : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'
          }`}
        >
          {status === 'success' ? <CheckCircle className="h-3.5 w-3.5" /> : <AlertCircle className="h-3.5 w-3.5" />}
          {message}
        </div>
      )}
    </motion.div>
  )
}

export function HeroSection() {
  const ref = useRef<HTMLElement>(null)
  const { copy, locale } = useVitrineLocale()
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start start', 'end start'] })
  const y = useSpring(useTransform(scrollYProgress, [0, 1], [0, -200]), { stiffness: 80, damping: 30 })
  const opacity = useTransform(scrollYProgress, [0, 0.6], [1, 0])
  const scale = useTransform(scrollYProgress, [0, 0.6], [1, 0.92])

  return (
    <section ref={ref} className="relative min-h-[92dvh] flex items-center justify-center overflow-hidden">
      <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(16,185,129,0.10),transparent_34%,rgba(34,211,238,0.08))]" />
      <div className="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50/80 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900/80" />
      <div
        className="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]"
        style={{
          backgroundImage:
            'linear-gradient(rgba(0,0,0,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.1) 1px, transparent 1px)',
          backgroundSize: '60px 60px',
        }}
      />

      <ParticleField />

      <motion.div style={{ y, opacity, scale }} className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-24">
        <div className="text-center max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20, filter: 'blur(10px)' }}
            animate={{ opacity: 1, y: 0, filter: 'blur(0px)' }}
            transition={{ duration: 0.8 }}
            className="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-emerald-500/[0.08] border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm font-medium mb-10 backdrop-blur-sm"
          >
            <Sparkles className="w-4 h-4 animate-pulse" />
            <span>{copy.hero.badge}</span>
            <span className="px-2 py-0.5 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-wider rounded-full">
              {copy.hero.badgeNew}
            </span>
          </motion.div>

          <motion.h1
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 1, delay: 0.15, ease: [0.22, 1, 0.36, 1] }}
            className="text-5xl sm:text-6xl lg:text-[5.5rem] font-black tracking-tight leading-[0.95] mb-8"
          >
            <span className="block bg-gradient-to-b from-slate-900 via-slate-800 to-slate-600 dark:from-white dark:via-slate-200 dark:to-slate-400 bg-clip-text text-transparent">
              {copy.hero.titleTop}
            </span>
            <span className="block mt-2 bg-gradient-to-r from-emerald-500 via-emerald-400 to-cyan-400 bg-clip-text text-transparent animate-gradient-x">
              {copy.hero.titleBottom}
            </span>
          </motion.h1>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.35 }}
            className="text-lg sm:text-xl lg:text-2xl text-slate-500 dark:text-slate-400 mb-14 max-w-3xl mx-auto leading-relaxed font-light"
          >
            {copy.hero.subtitle}{' '}
            <span className="text-slate-900 dark:text-white font-medium">{copy.hero.subtitleHighlight}</span>{' '}
            {copy.hero.subtitleTail}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.5 }}
            className="flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <Link
              href="/signup"
              className="group relative px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.4)] hover:scale-[1.03] active:scale-[0.98]"
            >
              <span className="relative z-10 flex items-center gap-2.5 text-base">
                {copy.hero.primaryCta}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" />
              </span>
              <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
            </Link>

            <Link href="/demo" className="group flex items-center gap-3.5 px-8 py-4 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-semibold rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-800 transition-all duration-300 hover:shadow-xl">
              <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/40 dark:to-emerald-900/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <Play className="w-4 h-4 text-emerald-600 dark:text-emerald-400 ml-0.5" />
              </div>
              {copy.hero.secondaryCta}
            </Link>
          </motion.div>

          {/* One-field guided trial request */}
          <QuickTrialEmailForm locale={locale} copy={copy.heroQuickTrial} />

          {/* Mobile apps availability bar - Workforce OS / Mobile-First Company OS */}
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.65 }}
            className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3"
          >
            <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
              <Smartphone className="w-4 h-4 text-emerald-500" />
              <span className="font-medium">{copy.hero.mobileBadge ?? 'Available on mobile'}</span>
              <span className="text-slate-300 dark:text-slate-600">-</span>
            </div>
            <div className="flex items-center gap-2">
              {['Employee', 'Manager', 'Platform Admin'].map((label) => (
                <span
                  key={label}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-xs font-semibold"
                >
                  <Smartphone className="w-3 h-3" />
                  {label}
                </span>
              ))}
            </div>
            <Link
              href="/download"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
            >
              <Download className="w-4 h-4" />
              {copy.hero.downloadCta ?? 'Download'}
            </Link>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 40 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 1, delay: 0.7 }}
            className="mt-24 grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto"
          >
            {copy.hero.stats.map((stat, index) => {
              const StatIcon = statIcons[index] || TrendingUp

              return (
                <div key={`${stat.label}-${index}`} className="text-center group">
                  <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-cyan-500/10 mb-4 group-hover:scale-110 transition-transform duration-300">
                    <StatIcon className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                  </div>
                  <div className="text-3xl sm:text-4xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                    <AnimatedCounter value={stat.value} suffix={stat.suffix} />
                  </div>
                  <div className="text-sm text-slate-500 dark:text-slate-500 mt-1.5 font-medium">{stat.label}</div>
                </div>
              )
            })}
          </motion.div>
        </div>
      </motion.div>

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 1.5 }}
        className="absolute bottom-8 left-1/2 -translate-x-1/2"
      >
        <motion.div
          animate={{ y: [0, 8, 0] }}
          transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
          className="w-6 h-10 rounded-full border-2 border-slate-300 dark:border-slate-700 flex items-start justify-center p-1.5"
        >
          <motion.div
            animate={{ opacity: [1, 0.3, 1], y: [0, 12, 0] }}
            transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
            className="w-1.5 h-1.5 rounded-full bg-emerald-500"
          />
        </motion.div>
      </motion.div>
    </section>
  )
}
