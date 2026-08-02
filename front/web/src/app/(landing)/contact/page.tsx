'use client';

import { Suspense, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { Navbar, HeroSection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { Mail, Phone, MapPin, Clock, Send, CheckCircle, AlertCircle } from 'lucide-react';

const subjects = [
  'Information gÃ©nÃ©rale',
  'Demande de dÃ©mo',
  'Support technique',
  'Partenariat',
  'Presse & MÃ©dias',
  'Mot de passe oubliÃ©',
  'Mise Ã  niveau (upgrade)',
  'TÃ©lÃ©chargement - Kiosque',
  'TÃ©lÃ©chargement - Windows',
  'TÃ©lÃ©chargement - macOS',
  'CommunautÃ©',
  'Autre',
];

// Maps the `?topic=` query param (used by links across login, dashboard,
// navbar/footer and the download page) to a matching entry in `subjects`.
// See issue #1304: without this mapping the "Sujet" select always fell
// back to its default value regardless of which link was clicked.
const TOPIC_TO_SUBJECT: Record<string, string> = {
  password: 'Mot de passe oubliÃ©',
  upgrade: 'Mise Ã  niveau (upgrade)',
  support: 'Support technique',
  community: 'CommunautÃ©',
  'download-kiosk': 'TÃ©lÃ©chargement - Kiosque',
  'download-windows': 'TÃ©lÃ©chargement - Windows',
  'download-macos': 'TÃ©lÃ©chargement - macOS',
  download: 'Information gÃ©nÃ©rale',
};

function ContactPageInner() {
  const searchParams = useSearchParams();
  const [isDark, setIsDark] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  useScrollReveal();

  const [form, setForm] = useState({
    name: '',
    email: '',
    company: '',
    subject: '',
    message: '',
  });

  // Prefill the "Sujet" select from the `?topic=` query param so contextual
  // links (forgot password, upgrade, support, download, community) land on
  // the right subject instead of the default empty option (issue #1304).
  useEffect(() => {
    const topic = searchParams.get('topic');
    if (!topic) return;
    const mapped = TOPIC_TO_SUBJECT[topic];
    if (mapped) {
      setForm(prev => ({ ...prev, subject: mapped }));
    }
  }, [searchParams]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      const res = await fetch('/api/forms/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...form, timestamp: new Date().toISOString() }),
      });
      if (!res.ok) throw new Error('Erreur lors de l\'envoi');
      setIsSubmitted(true);
    } catch {
      setError('Une erreur est survenue. Veuillez rÃ©essayer.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline="Contactez-nous"
        subheadline="Notre Ã©quipe est lÃ  pour rÃ©pondre Ã  toutes vos questions"
        ctaPrimary={{ text: 'Envoyer un message', href: '#contact-form' }}
        ctaSecondary={{ text: 'Demander une dÃ©mo', href: '/demo' }}
        badge={{ text: 'Contact', icon: <Mail className="w-3 h-3" /> }}
      />

      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-3 gap-12">
            {/* Contact Info */}
            <div className="space-y-8">
              <motion.div initial={{ opacity: 0, x: -20 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}>
                <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-6">Informations</h2>
                <div className="space-y-6">
                  {[
                    { icon: Mail, label: 'Email', value: 'contact@leopardo.com' },
                    { icon: Phone, label: 'TÃ©lÃ©phone', value: '+213 (0) 555 123 456' },
                    { icon: MapPin, label: 'Adresse', value: 'Alger, AlgÃ©rie' },
                    { icon: Clock, label: 'Horaires', value: 'Lun-Ven 9h-18h (GMT+1)' },
                  ].map((item, i) => (
                    <div key={i} className="flex items-start gap-4">
                      <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                        <item.icon className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                      </div>
                      <div>
                        <p className="text-sm text-slate-500 dark:text-slate-400">{item.label}</p>
                        <p className="font-medium text-slate-900 dark:text-white">{item.value}</p>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="mt-8 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
                  <p className="text-sm text-emerald-700 dark:text-emerald-400 font-medium">
                    Temps de rÃ©ponse moyen : moins de 24h
                  </p>
                </div>
              </motion.div>
            </div>

            {/* Contact Form */}
            <div className="lg:col-span-2" id="contact-form">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                className="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-sm border border-slate-200 dark:border-slate-700"
              >
                {isSubmitted ? (
                  <div className="text-center py-12">
                    <CheckCircle className="w-16 h-16 text-emerald-500 mx-auto mb-4" />
                    <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-2">Message envoyÃ© !</h3>
                    <p className="text-slate-500 dark:text-slate-400">Notre Ã©quipe vous rÃ©pondra sous 24h.</p>
                  </div>
                ) : (
                  <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid sm:grid-cols-2 gap-6">
                      <div>
                        <label htmlFor="name" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                          Nom complet <span className="text-red-500">*</span>
                        </label>
                        <input
                          id="name" name="name" required value={form.name} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                      </div>
                      <div>
                        <label htmlFor="email" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                          Email <span className="text-red-500">*</span>
                        </label>
                        <input
                          id="email" name="email" type="email" required value={form.email} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                      </div>
                    </div>

                    <div className="grid sm:grid-cols-2 gap-6">
                      <div>
                        <label htmlFor="company" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Entreprise</label>
                        <input
                          id="company" name="company" value={form.company} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                      </div>
                      <div>
                        <label htmlFor="subject" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                          Sujet <span className="text-red-500">*</span>
                        </label>
                        <select
                          id="subject" name="subject" required value={form.subject} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        >
                          <option value="">Choisir un sujet</option>
                          {subjects.map(s => <option key={s} value={s}>{s}</option>)}
                        </select>
                      </div>
                    </div>

                    <div>
                      <label htmlFor="message" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        Message <span className="text-red-500">*</span>
                      </label>
                      <textarea
                        id="message" name="message" required rows={5} value={form.message} onChange={handleChange}
                        className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent resize-none"
                      />
                    </div>

                    {error && (
                      <div className="flex items-center gap-2 text-red-600 dark:text-red-400 text-sm" role="alert">
                        <AlertCircle className="w-4 h-4" />{error}
                      </div>
                    )}

                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="w-full sm:w-auto px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-colors disabled:opacity-50 flex items-center gap-2"
                    >
                      <Send className="w-4 h-4" />
                      {isSubmitting ? 'Envoi en cours...' : 'Envoyer le message'}
                    </button>
                  </form>
                )}
              </motion.div>
            </div>
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}

export default function ContactPage() {
  return (
    <Suspense fallback={null}>
      <ContactPageInner />
    </Suspense>
  );
}

