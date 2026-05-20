'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { CheckCircle } from 'lucide-react';

interface NewsletterFormProps {
  locale?: string;
  placeholder?: string;
  submitLabel?: string;
  submittingLabel?: string;
  successFallback?: string;
  errorFallback?: string;
}

export function NewsletterForm({
  locale = 'fr',
  placeholder = 'Votre email',
  submitLabel = "S'inscrire",
  submittingLabel = 'Envoi...',
  successFallback = 'Inscription reussie !',
  errorFallback = "Erreur lors de l'inscription",
}: NewsletterFormProps) {
  const [email, setEmail] = useState('');
  const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');
  const [message, setMessage] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setStatus('loading');

    try {
      const res = await fetch('/api/forms/newsletter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email,
          locale,
          page: typeof window !== 'undefined' ? window.location.pathname : '/blog',
          timestamp: new Date().toISOString(),
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.message || errorFallback);
      }

      setStatus('success');
      setMessage(data.message || successFallback);
      setEmail('');
    } catch (err) {
      setStatus('error');
      setMessage(err instanceof Error ? err.message : errorFallback);
    }
  };

  if (status === 'success') {
    return (
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="flex items-center justify-center gap-2 text-emerald-600 dark:text-emerald-400"
      >
        <CheckCircle className="w-5 h-5" />
        <span className="font-medium">{message}</span>
      </motion.div>
    );
  }

  return (
    <div>
      <motion.form
        initial={{ opacity: 0, y: 20 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.6, delay: 0.1 }}
        className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto"
        onSubmit={handleSubmit}
      >
        <input
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder={placeholder}
          required
          disabled={status === 'loading'}
          className="flex-1 px-4 py-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all disabled:opacity-50"
        />
        <button
          type="submit"
          disabled={status === 'loading'}
          className="px-6 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-400 text-white font-bold transition-colors whitespace-nowrap"
        >
          {status === 'loading' ? submittingLabel : submitLabel}
        </button>
      </motion.form>

      {status === 'error' && (
        <p className="text-sm text-red-500 mt-2 text-center">{message}</p>
      )}
    </div>
  );
}
