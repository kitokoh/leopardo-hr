'use client'

import { useState } from 'react'
import { useVitrineLocale } from '../lib/vitrine-locale'
import { FormDataNotice } from './FormDataNotice'
import { antispamFields } from '@/modules/vitrine/lib/antispam-client'
import { HoneypotField } from '@/modules/vitrine/components/common/HoneypotField'
// #7594 — schéma zod branché côté client : messages dans la locale du site (×4).
import { newsletterFormSchema } from '@/modules/vitrine/lib/validation'

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

  // #8407 / modernisation vitrine : la newsletter adopte la grammaire sombre et prestigieuse
  // des colonnes du footer. Titre harmonisé avec la puce ambre, fond d'input en accord avec
  // le conteneur slate-950, bouton en dégradé émeraude/teal.
  return (
    <div className="w-full max-w-sm">
      <h4 className="text-xs font-black text-slate-100 mb-2 uppercase tracking-[0.16em] flex items-center gap-2">
        <span className="w-1.5 h-1.5 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.8)] inline-block" />
        {t.title}
      </h4>
      <p className="mb-3 text-xs text-slate-400 leading-relaxed">{t.description}</p>
      {status === 'success' ? (
        <div className="p-3 rounded-lg bg-emerald-950/60 border border-emerald-500/40 text-xs text-emerald-300 font-medium">
          {message}
        </div>
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
              className="min-w-0 flex-1 rounded-lg border border-slate-800 bg-slate-900/90 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-emerald-500/80 focus:outline-none focus:ring-1 focus:ring-emerald-500/80 transition-colors"
            />
            <button
              type="submit"
              disabled={status === 'loading'}
              className="shrink-0 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-sm font-semibold text-white hover:from-emerald-500 hover:to-teal-500 shadow-sm shadow-emerald-950/50 disabled:opacity-50 transition-all duration-200 cursor-pointer"
            >
              {status === 'loading' ? '...' : t.button}
            </button>
          </form>
          {/* #7593 — finalité et base légale au moment de la collecte, version
              discrète sous le formulaire (pas un bloc à part entière). */}

          <div className="[&_p]:mt-2 [&_p]:text-[11px] [&_p]:leading-snug [&_p]:text-slate-500">

            <FormDataNotice purpose="newsletter" />
          </div>
        </>
      )}
      {status === 'error' && (
        <p id="newsletter-footer-error" role="alert" aria-live="polite" className="mt-1.5 text-xs text-rose-400 font-medium">
          {message}
        </p>
      )}
    </div>
  )
}
