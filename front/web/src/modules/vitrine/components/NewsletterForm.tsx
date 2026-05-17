'use client'

import { useState } from 'react'

export function NewsletterForm() {
  const [email, setEmail] = useState('')
  const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle')
  const [message, setMessage] = useState('')

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!email.trim()) return

    setStatus('loading')
    try {
      const res = await fetch('/api/forms/newsletter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, page: window.location.pathname, timestamp: new Date().toISOString() }),
      })
      const data = await res.json()
      if (res.ok) {
        setStatus('success')
        setMessage(data.message || 'Inscription reussie !')
        setEmail('')
      } else {
        setStatus('error')
        setMessage(data.message || 'Erreur lors de l\'inscription.')
      }
    } catch {
      setStatus('error')
      setMessage('Erreur reseau. Veuillez reessayer.')
    }
  }

  return (
    <div className="w-full max-w-md">
      <h4 className="text-sm font-bold text-slate-900 dark:text-white mb-2">Newsletter</h4>
      <p className="text-xs text-slate-500 dark:text-slate-400 mb-3">
        Recevez nos conseils RH et mises a jour produit.
      </p>
      {status === 'success' ? (
        <p className="text-sm text-emerald-600 dark:text-emerald-400 font-medium">{message}</p>
      ) : (
        <form onSubmit={handleSubmit} className="flex gap-2">
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="votre@email.com"
            required
            className="flex-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          />
          <button
            type="submit"
            disabled={status === 'loading'}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 transition-colors"
          >
            {status === 'loading' ? '...' : 'OK'}
          </button>
        </form>
      )}
      {status === 'error' && <p className="mt-1 text-xs text-red-500">{message}</p>}
    </div>
  )
}
