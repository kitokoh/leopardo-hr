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

export function QuickTrialEmailForm({ locale, copy }: { locale: AppLocale; copy: QuickTrialCopy }) {
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
        signal: AbortSignal.timeout(20000),
      })

      const result = await response.json().catch(() => null)

      if (!response.ok) {
        setStatus('error')
        setMessage((result && typeof result.message === 'string' && result.message) || copy.error)
        return
      }

      setStatus('success')
      // `provisioned === false` means the backend could not send an OTP right
      // now (cold-start/timeout) but the lead was captured; the API already
      // returns a message explaining the team will follow up under 24h.
      setMessage(
        result && result.provisioned === false && typeof result.message === 'string'
          ? result.message
          : copy.success
      )
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
    <section ref={ref} className="relative min-h-[92dvh] flex items-center justify-center overflow-hidden bg-white dark:bg-stitch-bg">
      <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(16,185,129,0.10),transparent_34%,rgba(34,211,238,0.08))]" />
      <div className="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50/80 dark:from-stitch-bg dark:via-stitch-bg dark:to-surface" />
      <div
        className="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]"
        style={{
          backgroundImage:
            'linear-gradient(rgba(0,0,0,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.1) 1px, transparent 1px)',
          backgroundSize: '60px 60px',
        }}
      />

      <ParticleField />

      <motion.div style={{ y, opacity, scale }} className="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-24">
        {/* Split Screen Layout */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-8 items-center">
          
          {/* Left Side: Content & Lead Capture */}
          <div className="text-left max-w-2xl mx-auto lg:mx-0">
            <motion.div
              initial={{ opacity: 0, y: 20, filter: 'blur(10px)' }}
              animate={{ opacity: 1, y: 0, filter: 'blur(0px)' }}
              transition={{ duration: 0.8 }}
              className="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-emerald-500/[0.08] border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm font-medium mb-10 backdrop-blur-sm shadow-glass-sm"
            >
              <Sparkles className="w-4 h-4 animate-pulse" />
              <span>{copy.hero.badge}</span>
              <span className="px-2 py-0.5 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-wider rounded-full shadow-emerald-500/20">
                {copy.hero.badgeNew}
              </span>
            </motion.div>

            <motion.h1
              initial={{ opacity: 0, y: 30 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 1, delay: 0.15, ease: [0.22, 1, 0.36, 1] }}
              className="text-5xl sm:text-6xl lg:text-7xl font-black tracking-tight leading-[1] mb-8"
            >
              <span className="block bg-gradient-to-br from-slate-900 to-slate-600 dark:from-white dark:to-on-surface-variant bg-clip-text text-transparent">
                {copy.hero.titleTop}
              </span>
              <span className="block mt-2 bg-gradient-to-r from-emerald-400 via-emerald-500 to-cyan-400 bg-clip-text text-transparent animate-gradient-x drop-shadow-sm">
                {copy.hero.titleBottom}
              </span>
            </motion.h1>

            <motion.p
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.35 }}
              className="text-lg sm:text-xl text-slate-500 dark:text-on-surface-variant mb-10 leading-relaxed font-light"
            >
              {copy.hero.subtitle}{' '}
              <span className="text-slate-900 dark:text-on-surface font-medium">{copy.hero.subtitleHighlight}</span>{' '}
              {copy.hero.subtitleTail}
            </motion.p>

            <div className="mb-10">
              {/* One-field guided trial request */}
              <QuickTrialEmailForm locale={locale} copy={copy.heroQuickTrial} />
            </div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.5 }}
              className="flex flex-col sm:flex-row items-center gap-4"
            >
              <Link
                href="/demo"
                className="group flex items-center gap-3.5 px-8 py-4 bg-white dark:bg-surface-bright text-slate-900 dark:text-on-surface font-semibold rounded-2xl border border-slate-200 dark:border-surface-variant hover:border-emerald-300 dark:hover:border-emerald-500/50 transition-all duration-300 hover:shadow-glass-lg backdrop-blur-xl"
              >
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/40 dark:to-emerald-900/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                  <Play className="w-4 h-4 text-emerald-600 dark:text-emerald-400 ml-0.5" />
                </div>
                {copy.hero.secondaryCta}
              </Link>
            </motion.div>

            {/* Mobile apps availability bar */}
            <motion.div
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.65 }}
              className="mt-10 flex flex-wrap items-center gap-3"
            >
              <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-on-surface-variant">
                <Smartphone className="w-4 h-4 text-emerald-500" />
                <span className="font-medium">{copy.hero.mobileBadge ?? 'Available on mobile'}</span>
                <span className="text-slate-300 dark:text-surface-variant">-</span>
              </div>
              <div className="flex items-center gap-2">
                {['Employee', 'Manager', 'Admin'].map((label) => (
                  <span
                    key={label}
                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-xs font-semibold"
                  >
                    {label}
                  </span>
                ))}
              </div>
            </motion.div>
          </div>
          
          {/* Right Side: Abstract Visuals / Cards */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 1, delay: 0.4 }}
            className="relative hidden lg:block h-[600px] w-full"
          >
            {/* Abstract Decorative Elements representing Glassmorphism floating cards */}
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="relative w-full h-full max-w-md">
                {/* Floating Card 1 */}
                <motion.div
                  animate={{ y: [-10, 10, -10] }}
                  transition={{ duration: 6, repeat: Infinity, ease: 'easeInOut' }}
                  className="absolute top-10 right-0 w-64 p-6 rounded-3xl bg-white/10 dark:bg-surface-bright/40 border border-white/20 dark:border-white/10 backdrop-blur-2xl shadow-glass-lg"
                >
                  <div className="w-12 h-12 rounded-2xl bg-emerald-500/20 flex items-center justify-center mb-4">
                    <TrendingUp className="w-6 h-6 text-emerald-500" />
                  </div>
                  <div className="h-4 w-3/4 bg-slate-200/50 dark:bg-surface-variant rounded mb-3" />
                  <div className="h-3 w-1/2 bg-slate-200/30 dark:bg-surface-variant/50 rounded" />
                </motion.div>

                {/* Floating Card 2 */}
                <motion.div
                  animate={{ y: [15, -15, 15] }}
                  transition={{ duration: 7, repeat: Infinity, ease: 'easeInOut', delay: 1 }}
                  className="absolute bottom-20 left-0 w-72 p-6 rounded-3xl bg-white/50 dark:bg-surface/60 border border-white/40 dark:border-surface-variant backdrop-blur-xl shadow-2xl"
                >
                  <div className="flex items-center justify-between mb-4">
                    <div className="flex -space-x-3">
                      {[1,2,3].map(i => (
                        <div key={i} className="w-10 h-10 rounded-full bg-slate-200 dark:bg-surface-bright border-2 border-white dark:border-stitch-bg flex items-center justify-center">
                          <Users className="w-4 h-4 text-slate-400" />
                        </div>
                      ))}
                    </div>
                    <div className="px-3 py-1 bg-emerald-500/20 text-emerald-500 rounded-full text-xs font-bold">+24%</div>
                  </div>
                  <div className="h-4 w-full bg-slate-200/50 dark:bg-surface-variant rounded mb-2" />
                  <div className="h-4 w-5/6 bg-slate-200/50 dark:bg-surface-variant rounded" />
                </motion.div>

                {/* Background Glow */}
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-emerald-500/20 blur-[100px] rounded-full pointer-events-none" />
              </div>
            </div>
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
          className="w-6 h-10 rounded-full border-2 border-slate-300 dark:border-surface-variant flex items-start justify-center p-1.5"
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
