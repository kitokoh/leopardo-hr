'use client';

import { useState } from 'react';
import { Navbar, Footer, HeroSection, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Mail, Phone, MapPin, MessageSquare, Send } from 'lucide-react';

export default function ContactPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  const [form, setForm] = useState({ name: '', email: '', company: '', subject: '', message: '' });
  const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setStatus('loading');
    try {
      const res = await fetch('/api/forms/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      });
      if (!res.ok) throw new Error('Erreur');
      setStatus('success');
      setForm({ name: '', email: '', company: '', subject: '', message: '' });
    } catch {
      setStatus('error');
    }
  };

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <HeroSection
        headline="Contactez-nous"
        subheadline="Notre equipe est a votre disposition pour repondre a toutes vos questions"
        badge={{ text: 'Support', icon: <MessageSquare className="w-3 h-3" /> }}
      />

      <section className="py-24">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-5 gap-16">
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            className="lg:col-span-2 space-y-8"
          >
            <div>
              <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-6">Nos Coordonnees</h2>
              <div className="space-y-6">
                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                    <Mail className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                  </div>
                  <div>
                    <p className="font-semibold text-slate-900 dark:text-white">Email</p>
                    <p className="text-slate-600 dark:text-slate-400">contact@leopardo.rh</p>
                  </div>
                </div>
                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                    <Phone className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                  </div>
                  <div>
                    <p className="font-semibold text-slate-900 dark:text-white">Telephone</p>
                    <p className="text-slate-600 dark:text-slate-400">+33 1 23 45 67 89</p>
                  </div>
                </div>
                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                    <MapPin className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                  </div>
                  <div>
                    <p className="font-semibold text-slate-900 dark:text-white">Adresse</p>
                    <p className="text-slate-600 dark:text-slate-400">Paris, France</p>
                  </div>
                </div>
              </div>
            </div>
            <div className="p-6 rounded-2xl bg-emerald-500/5 border border-emerald-500/10">
              <h3 className="font-bold text-slate-900 dark:text-white mb-2">Reponse rapide</h3>
              <p className="text-sm text-slate-600 dark:text-slate-400">Notre equipe repond sous 24h les jours ouvrables. Pour les urgences, appelez-nous directement.</p>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: 20 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            className="lg:col-span-3"
          >
            {status === 'success' ? (
              <div className="text-center py-16">
                <div className="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Send className="w-8 h-8 text-emerald-600" />
                </div>
                <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-2">Message envoye !</h3>
                <p className="text-slate-600 dark:text-slate-400">Nous vous repondrons dans les plus brefs delais.</p>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-5">
                <div className="grid sm:grid-cols-2 gap-5">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Nom complet *</label>
                    <input type="text" required value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email *</label>
                    <input type="email" required value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                  </div>
                </div>
                <div className="grid sm:grid-cols-2 gap-5">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Entreprise</label>
                    <input type="text" value={form.company} onChange={e => setForm(f => ({ ...f, company: e.target.value }))} className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Sujet *</label>
                    <select required value={form.subject} onChange={e => setForm(f => ({ ...f, subject: e.target.value }))} className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                      <option value="">Choisir un sujet</option>
                      <option value="demo">Demande de demo</option>
                      <option value="pricing">Question tarification</option>
                      <option value="support">Support technique</option>
                      <option value="partnership">Partenariat</option>
                      <option value="other">Autre</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Message *</label>
                  <textarea required rows={5} value={form.message} onChange={e => setForm(f => ({ ...f, message: e.target.value }))} className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent resize-none" />
                </div>
                {status === 'error' && <p className="text-sm text-red-500">Une erreur est survenue. Reessayez.</p>}
                <button type="submit" disabled={status === 'loading'} className="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors disabled:opacity-50">
                  {status === 'loading' ? 'Envoi...' : 'Envoyer le message'}
                </button>
              </form>
            )}
          </motion.div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
