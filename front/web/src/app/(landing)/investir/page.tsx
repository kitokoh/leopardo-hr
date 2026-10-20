'use client';

/**
 * #8440 — page vitrine « Devenir actionnaire » (/investir).
 *
 * Positionnement (décidé par le propriétaire) : permettre à un visiteur de
 * MANIFESTER SON INTÉRÊT pour prendre des parts de l'entreprise. Ce n'est
 * **pas** une offre publique de titres : la manifestation est non
 * contraignante, le suivi (échange, term-sheet, closing) se fait en direct,
 * hors plateforme. La page le dit explicitement — conformité et honnêteté.
 *
 * Entrée volontairement discrète : lien en footer (colonne « Légal »),
 * aucune carte home ni bandeau. La capture passe par `/api/forms/equity`
 * (canal leads `equity_interest`) et se suit dans l'admin plateforme.
 */

import { Suspense, useState } from 'react';
import { Navbar, HeroSection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import {
  AlertCircle,
  CheckCircle,
  Handshake,
  LineChart,
  Scale,
  Send,
  ShieldCheck,
  TrendingUp,
  Users,
} from 'lucide-react';
import { FormDataNotice } from '@/modules/vitrine/components/FormDataNotice';
import { antispamFields } from '@/modules/vitrine/lib/antispam-client';
import { HoneypotField } from '@/modules/vitrine/components/common/HoneypotField';
import { Input, Textarea, Button } from '@/modules/vitrine/components/common';
import { EQUITY_AMOUNT_BRACKETS, equityFormSchema, parseZodErrors } from '@/modules/vitrine/lib/validation';

import { COUNTRIES, investCopy, type AmountBracket } from '@/modules/vitrine/lib/investir-copy';
const PILLAR_ICONS = [TrendingUp, Scale, LineChart] as const;
const STEP_ICONS = [Send, Users, ShieldCheck] as const;

function InvestirPageInner() {
  const { locale } = useVitrineLocale();
  const copy = investCopy[locale] ?? investCopy.fr;
  useScrollReveal();

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    country: '',
    amountBracket: '' as '' | AmountBracket,
    message: '',
    consent: false,
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const target = e.target;
    const value = target instanceof HTMLInputElement && target.type === 'checkbox' ? target.checked : target.value;
    setForm(prev => ({ ...prev, [target.name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    const validation = equityFormSchema(locale).safeParse(form);
    if (!validation.success) {
      const errors: Record<string, string> = {};
      for (const { field, message } of parseZodErrors(validation.error)) {
        if (!errors[field]) errors[field] = message;
      }
      setFieldErrors(errors);
      return;
    }
    setFieldErrors({});
    setIsSubmitting(true);

    try {
      const res = await fetch('/api/forms/equity', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...antispamFields(),
          name: form.name,
          email: form.email,
          phone: form.phone || undefined,
          country: form.country || undefined,
          amountBracket: form.amountBracket,
          message: form.message || undefined,
          consent: form.consent,
          locale,
          page: '/investir',
          source: 'equity_form',
          timestamp: new Date().toISOString(),
        }),
      });
      if (!res.ok) throw new Error(copy.form.errorGeneric);
      setIsSubmitted(true);
    } catch {
      setError(copy.form.errorGeneric);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className={`min-h-screen bg-white `}>
      <Navbar />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.cta, href: '#investir-form' }}
        badge={{ text: copy.hero.badge, icon: <Handshake className="w-3 h-3" /> }}
      />

      {/* Mention de cadrage — dite clairement, dès le haut de page. */}
      <section className="pt-4 pb-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <p className="text-sm leading-relaxed text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-4 bg-slate-50 dark:bg-slate-900/40">
            {copy.notice}
          </p>
        </div>
      </section>

      {/* Pourquoi cette ouverture */}
      <section className="py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-8 text-center">{copy.pillars.title}</h2>
          <div className="grid md:grid-cols-3 gap-6">
            {copy.pillars.items.map((item, index) => {
              const Icon = PILLAR_ICONS[index] ?? TrendingUp;
              return (
                <motion.div
                  key={item.title}
                  initial={{ opacity: 0, y: 16 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.08 }}
                  className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700"
                >
                  <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-4">
                    <Icon className="w-5 h-5 text-emerald-700 dark:text-emerald-400" />
                  </div>
                  <h3 className="font-bold text-slate-900 dark:text-white mb-2">{item.title}</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{item.body}</p>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Étapes */}
      <section className="py-12 bg-slate-50 dark:bg-slate-900/30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-8 text-center">{copy.steps.title}</h2>
          <div className="grid md:grid-cols-3 gap-6">
            {copy.steps.items.map((item, index) => {
              const Icon = STEP_ICONS[index] ?? Send;
              return (
                <motion.div
                  key={item.title}
                  initial={{ opacity: 0, y: 16 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.08 }}
                  className="rounded-2xl p-6 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                >
                  <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-4">
                    <Icon className="w-5 h-5 text-emerald-700 dark:text-emerald-400" />
                  </div>
                  <h3 className="font-bold text-slate-900 dark:text-white mb-2">{item.title}</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{item.body}</p>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Formulaire */}
      <section className="py-16">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8" id="investir-form">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-sm border border-slate-200 dark:border-slate-700"
          >
            {isSubmitted ? (
              <div className="text-center py-12">
                <CheckCircle className="w-16 h-16 text-emerald-500 mx-auto mb-4" />
                <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-2">{copy.form.successTitle}</h3>
                <p className="text-slate-500 dark:text-slate-400">{copy.form.successBody}</p>
              </div>
            ) : (
              <>
                <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-6">{copy.form.title}</h2>
                <form onSubmit={handleSubmit} className="space-y-6">
                  <HoneypotField />
                  <div className="grid sm:grid-cols-2 gap-6">
                    <Input
                      id="name" name="name" required value={form.name} onChange={handleChange}
                      label={copy.form.name} error={fieldErrors.name}
                    />
                    <Input
                      id="email" name="email" type="email" required value={form.email} onChange={handleChange}
                      label={copy.form.email} error={fieldErrors.email}
                    />
                  </div>

                  <div className="grid sm:grid-cols-2 gap-6">
                    <Input
                      id="phone" name="phone" value={form.phone} onChange={handleChange}
                      label={copy.form.phone} error={fieldErrors.phone}
                    />
                    <div className="w-full">
                      <label htmlFor="country" className="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        {copy.form.country}
                      </label>
                      <select
                        id="country" name="country" value={form.country} onChange={handleChange}
                        className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 dark:text-white"
                      >
                        <option value="">{copy.form.countryPlaceholder}</option>
                        {COUNTRIES.map(code => (
                          <option key={code} value={code}>{code}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="w-full">
                    <label htmlFor="amountBracket" className="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                      {copy.form.bracket} <span className="text-red-500 ml-1">*</span>
                    </label>
                    <select
                      id="amountBracket" name="amountBracket" required value={form.amountBracket} onChange={handleChange}
                      aria-invalid={fieldErrors.amountBracket ? true : undefined}
                      className={`w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 dark:text-white ${fieldErrors.amountBracket ? 'border-red-500 focus:ring-red-500' : 'border-slate-300 dark:border-slate-700'}`}
                    >
                      <option value="">{copy.form.bracketPlaceholder}</option>
                      {EQUITY_AMOUNT_BRACKETS.map(bracket => (
                        <option key={bracket} value={bracket}>{copy.form.brackets[bracket]}</option>
                      ))}
                    </select>
                    {fieldErrors.amountBracket && (
                      <p role="alert" className="flex items-center gap-1.5 mt-2 text-sm text-red-600 dark:text-red-400">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />{fieldErrors.amountBracket}
                      </p>
                    )}
                  </div>

                  <Textarea
                    id="message" name="message" rows={4} value={form.message} onChange={handleChange}
                    label={copy.form.message} error={fieldErrors.message} className="resize-none"
                  />

                  <div>
                    <label className="flex items-start gap-3 cursor-pointer">
                      <input
                        type="checkbox" name="consent" checked={form.consent} onChange={handleChange}
                        className="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                      />
                      <span className="text-sm text-slate-600 dark:text-slate-300">{copy.form.consent}</span>
                    </label>
                    {fieldErrors.consent && (
                      <p role="alert" className="flex items-center gap-1.5 mt-2 text-sm text-red-600 dark:text-red-400">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />{fieldErrors.consent}
                      </p>
                    )}
                  </div>

                  {error && (
                    <div className="flex items-center gap-2 text-red-600 dark:text-red-400 text-sm" role="alert" aria-live="assertive">
                      <AlertCircle className="w-4 h-4" />{error}
                    </div>
                  )}

                  <FormDataNotice purpose="equity" />

                  <Button
                    type="submit"
                    size="lg"
                    loading={isSubmitting}
                    icon={<Send className="w-4 h-4" />}
                    iconPosition="left"
                    className="w-full sm:w-auto"
                  >
                    {isSubmitting ? copy.form.sending : copy.form.send}
                  </Button>
                </form>
              </>
            )}
          </motion.div>
        </div>
      </section>

      <Footer />
    </div>
  );
}

export default function InvestirPage() {
  return (
    <Suspense fallback={null}>
      <InvestirPageInner />
    </Suspense>
  );
}
