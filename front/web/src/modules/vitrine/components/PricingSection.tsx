'use client'

import { useState } from 'react'
import Link from 'next/link'
import { motion } from 'framer-motion'
import { ArrowRight, CheckCircle2, Users } from 'lucide-react'
import { getPricingPlans } from '../data/pricing'
import { useVitrineLocale } from '../lib/vitrine-locale'

function showsCurrency(price: string) {
  return !['Sur devis', 'Custom', 'Teklif', 'حسب العرض'].includes(price)
}

function getPlanCtaHref(price: string, planName?: string, isAnnual?: boolean) {
  if (!showsCurrency(price)) return '/contact?type=enterprise'
  const billing = isAnnual ? 'annual' : 'monthly'
  // Map plan name to plan key
  const planKey = (planName ?? '').toLowerCase().includes('operations') ? 'business'
    : (planName ?? '').toLowerCase().includes('scale') ? 'enterprise'
    : 'starter'
  return `/checkout?plan=${planKey}&billing=${billing}`
}

const savingsLabel: Record<string, string> = {
  fr: 'Economisez 20%',
  en: 'Save 20%',
  tr: '%20 tasarruf',
  ar: 'وفّر 20%',
}

const billingToggle: Record<string, { monthly: string; annual: string }> = {
  fr: { monthly: 'Mensuel', annual: 'Annuel' },
  en: { monthly: 'Monthly', annual: 'Annual' },
  tr: { monthly: 'Aylik', annual: 'Yillik' },
  ar: { monthly: 'شهري', annual: 'سنوي' },
}

export function PricingSection() {
  const { copy, locale } = useVitrineLocale()
  const pricingPlans = getPricingPlans(locale)
  const [isAnnual, setIsAnnual] = useState(true)
  const toggle = billingToggle[locale] ?? billingToggle.en

  return (
    <section id="tarifs" className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/80 to-white dark:from-slate-950 dark:via-slate-900/80 dark:to-slate-950" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12 gsap-reveal">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/[0.08] border border-violet-500/15 text-violet-700 dark:text-violet-400 text-sm font-semibold mb-6">
            <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse" />
            {copy.pricing.badge}
          </div>
          <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
            {copy.pricing.title}{' '}
            <span className="bg-gradient-to-r from-violet-500 to-fuchsia-500 bg-clip-text text-transparent">
              {copy.pricing.titleHighlight}
            </span>
          </h2>
          <p className="text-xl text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">
            {copy.pricing.subtitle}
          </p>
        </div>

        <div className="flex items-center justify-center gap-3 mb-16">
          <span className={`text-sm font-medium ${!isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-400'}`}>
            {toggle.monthly}
          </span>
          <button
            onClick={() => setIsAnnual(!isAnnual)}
            className="relative w-14 h-7 rounded-full bg-emerald-500 transition-colors"
            aria-label="Toggle billing period"
          >
            <motion.div
              className="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-sm"
              animate={{ left: isAnnual ? '1.75rem' : '0.125rem' }}
              transition={{ type: 'spring', stiffness: 500, damping: 30 }}
            />
          </button>
          <span className={`text-sm font-medium ${isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-400'}`}>
            {toggle.annual}
          </span>
          {isAnnual && (
            <motion.span
              initial={{ opacity: 0, scale: 0.8 }}
              animate={{ opacity: 1, scale: 1 }}
              className="ml-1 px-2.5 py-0.5 text-xs font-bold text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full"
            >
              {savingsLabel[locale] ?? savingsLabel.en}
            </motion.span>
          )}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
          {pricingPlans.map((plan, index) => {
            const displayPrice = isAnnual ? plan.annualPrice : plan.price
            const displayPeriod = isAnnual ? plan.annualPeriod : plan.period
            const hasNumericPrice = showsCurrency(displayPrice)

            return (
              <motion.div
                key={`${plan.name}-${index}`}
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.6, delay: index * 0.12 }}
                whileHover={{ y: -8, transition: { duration: 0.25 } }}
                className={`relative rounded-3xl ${
                  plan.popular
                    ? 'bg-gradient-to-b from-emerald-400 via-emerald-500 to-cyan-500 p-px shadow-2xl shadow-emerald-500/20'
                    : 'bg-slate-200/80 dark:bg-slate-800/80 p-px'
                }`}
              >
                <div className="relative h-full rounded-[23px] bg-white dark:bg-slate-950 p-8">
                  {plan.popular && (
                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2">
                      <span className="px-4 py-1.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-xs font-black uppercase tracking-wider rounded-full shadow-lg shadow-emerald-500/30">
                        {copy.pricing.recommended}
                      </span>
                    </div>
                  )}

                  <div className="text-center mb-8">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-1">{plan.name}</h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-2">{plan.description}</p>
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400 mb-6">
                      <Users className="w-3 h-3" />
                      {plan.employeeLimit}
                    </div>
                    <div className="flex items-baseline justify-center gap-1">
                      {hasNumericPrice && <span className="text-sm text-slate-500">{copy.pricing.currency}</span>}
                      <span className="text-5xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                        {displayPrice}
                      </span>
                    </div>
                    {displayPeriod && (
                      <span className="text-sm text-slate-500">{displayPeriod}</span>
                    )}
                    {plan.priceNote && (
                      <p className="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{plan.priceNote}</p>
                    )}
                    {isAnnual && hasNumericPrice && (
                      <div className="mt-1">
                        <span className="text-xs text-slate-400 line-through">{copy.pricing.currency} {plan.price}</span>
                      </div>
                    )}
                  </div>

                  <ul className="space-y-3.5 mb-8">
                    {plan.features.map((feature, featureIndex) => (
                      <li key={`${plan.name}-feature-${featureIndex}`} className="flex items-center gap-3">
                        <CheckCircle2 className={`w-4 h-4 flex-shrink-0 ${plan.popular ? 'text-emerald-500' : 'text-slate-400'}`} />
                        <span className="text-sm text-slate-700 dark:text-slate-300">{feature}</span>
                      </li>
                    ))}
                  </ul>

                  <Link
                    href={getPlanCtaHref(displayPrice, plan.name, isAnnual)}
                    className={`flex items-center justify-center gap-2 w-full py-3.5 rounded-xl font-bold text-sm transition-all duration-300 ${
                      plan.popular
                        ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:from-emerald-600 hover:to-emerald-700 shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/40 hover:scale-[1.02] active:scale-[0.98]'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white hover:bg-slate-200 dark:hover:bg-slate-700'
                    }`}
                  >
                    {plan.cta}
                    {plan.popular && <ArrowRight className="w-4 h-4" />}
                  </Link>
                </div>
              </motion.div>
            )
          })}
        </div>

        <div className="mt-12 text-center">
          <Link
            href="/pricing"
            className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
          >
            {locale === 'fr' ? 'Voir la comparaison complete' : locale === 'tr' ? 'Tam karsilastirmayi gorun' : locale === 'ar' ? 'عرض المقارنة الكاملة' : 'View full comparison'}
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
      </div>
    </section>
  )
}
