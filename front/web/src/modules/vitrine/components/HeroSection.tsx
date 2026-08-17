'use client'

import { motion } from 'framer-motion'
import { AlertCircle, CheckCircle, Mail } from 'lucide-react'
import { useState } from 'react'
import type { FormEvent } from 'react'
import type { AppLocale } from '@/lib/i18n'

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
        <label className="flex min-h-14 flex-1 items-center gap-3 rounded-xl bg-transparent px-4 text-left dark:bg-slate-950/60">
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

