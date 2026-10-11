'use client'

import { useState } from 'react'
import { useVitrineLocale } from '../lib/vitrine-locale'
import { FormDataNotice } from './FormDataNotice'
import { antispamFields } from '@/modules/vitrine/lib/antispam-client';
import { HoneypotField } from '@/modules/vitrine/components/common/HoneypotField';
// #7594 — schéma zod branché côté client : messages dans la locale du site (×4).
import { newsletterFormSchema } from '@/modules/vitrine/lib/validation';

export function NewsletterForm() {
  const { copy, locale } = useVitrineLocale()
  const t = copy.footer.newsletter

  const [email, setEmail] = useState('')
  const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle')
  const [message, setMessage] = useState('')

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!email.trim()) return

    // #7594 — validation zod localisée avant l'appel réseau.
    const validation = newsletterFormSchema(locale).safeParse({ email })
    if (!validation.success) {
      setStatus('error')
      setMessage(validation.error.issues[0]?.message ?? t.error)
      return
    }

    setStatus('loading')
    try {
      const res = await fetch('/api/forms/newsletter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...antispamFields(), email, page: window.location.pathname, timestamp: new Date().toISOString() }),
      })
      const data = await res.json()
      if (res.ok) {
        setStatus('success')
        setMessage(data.message || t.success)
        setEmail('')
      } else {
        setStatus('error')
        setMessage(data.message || t.error)
      }
    } catch {
      setStatus('error')
      setMessage(t.error)
    }
  }

  // #8407 — la newsletter adopte la grammaire des colonnes du footer (et non
  // l'inverse) : titre identique aux h4 des colonnes, formulaire sur UNE ligne,
  // notice RGPD conservée mais discrète et HORS du flux flex (elle y cassait la
  // ligne avec `basis-full` et gonflait le bloc — « espace anormalement gros »).
  return (
    <div className="w-full max-w-xs">
      <h4 className="mb-4 text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">{t.title}</h4>
      <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">{t.description}</p>
      {status === 'success' ? (
        <p className="text-sm text-emerald-700 dark:text-emerald-400 font-medium">{message}</p>
      ) : (
        <>
          <form onSubmit={handleSubmit} className="flex gap-2">
            <HoneypotField />
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder={t.placeholder}
              aria-label={t.title}
              aria-invalid={status === 'error' ? true : undefined}
              aria-describedby={status === 'error' ? 'newsletter-footer-error' : undefined}
              required
              className="min-w-0 flex-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
            <button
              type="submit"
              disabled={status === 'loading'}
              className="shrink-0 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50 transition-colors"
            >
              {status === 'loading' ? '...' : t.button}
            </button>
          </form>
          {/* #7593 — finalité et base légale au moment de la collecte, version
              discrète sous le formulaire (pas un bloc à part entière). */}
          <div className="[&_p]:mt-2 [&_p]:text-[11px] [&_p]:leading-snug [&_p]:text-slate-400 dark:[&_p]:text-slate-500">
            <FormDataNotice purpose="newsletter" />
          </div>
        </>
      )}
      {status === 'error' && <p id="newsletter-footer-error" role="alert" aria-live="polite" className="mt-1 text-xs text-red-500">{message}</p>}
    </div>
  )
}
