'use client'

import { motion } from 'framer-motion'
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine'
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale'
import { useState } from 'react'
import {
  Fingerprint,
  CalendarSync,
  Globe,
  Code,
  Webhook,
  CreditCard,
  Shield,
  FileSpreadsheet,
} from 'lucide-react'

type Integration = {
  name: string
  description: string
  category: string
  icon: React.ElementType
  status: 'available' | 'coming_soon'
}

const integrationsCopy = {
  fr: {
    title: 'Integrations',
    titleHighlight: 'connectees',
    subtitle: 'Leopardo RH s\'integre nativement avec vos outils existants.',
    badge: 'Ecosysteme',
    categories: { biometric: 'Pointeuses', calendar: 'Calendrier', api: 'API & Developpeurs', payment: 'Paiement', security: 'Securite', export: 'Exports' },
    available: 'Disponible',
    comingSoon: 'Bientot',
  },
  en: {
    title: 'Connected',
    titleHighlight: 'integrations',
    subtitle: 'Leopardo RH integrates natively with your existing tools.',
    badge: 'Ecosystem',
    categories: { biometric: 'Biometric', calendar: 'Calendar', api: 'API & Developers', payment: 'Payment', security: 'Security', export: 'Exports' },
    available: 'Available',
    comingSoon: 'Coming soon',
  },
  tr: {
    title: 'Bagli',
    titleHighlight: 'entegrasyonlar',
    subtitle: 'Leopardo RH mevcut araclarinizla yerel olarak entegre olur.',
    badge: 'Ekosistem',
    categories: { biometric: 'Biyometrik', calendar: 'Takvim', api: 'API & Gelistiriciler', payment: 'Odeme', security: 'Guvenlik', export: 'Disa aktarim' },
    available: 'Mevcut',
    comingSoon: 'Yakinda',
  },
  ar: {
    title: 'التكاملات',
    titleHighlight: 'المتصلة',
    subtitle: 'Leopardo RH يتكامل بشكل اصلي مع ادواتك الحالية.',
    badge: 'النظام البيئي',
    categories: { biometric: 'بيومتري', calendar: 'تقويم', api: 'API والمطورين', payment: 'الدفع', security: 'الامان', export: 'التصدير' },
    available: 'متاح',
    comingSoon: 'قريبا',
  },
} as const

const integrations: Integration[] = [
  { name: 'ZKTeco', description: 'Pointeuses biometriques ZKTeco (empreinte, visage, badge RFID). Synchronisation automatique des logs.', category: 'biometric', icon: Fingerprint, status: 'available' },
  { name: 'Google Calendar', description: 'Synchronisation des conges et absences avec Google Calendar.', category: 'calendar', icon: CalendarSync, status: 'available' },
  { name: 'Microsoft Outlook', description: 'Integration avec Outlook Calendar pour les plannings et rappels.', category: 'calendar', icon: CalendarSync, status: 'available' },
  { name: 'API REST v1', description: 'API RESTful documentee avec OpenAPI/Swagger. Authentification Bearer token.', category: 'api', icon: Code, status: 'available' },
  { name: 'Webhooks', description: 'Notifications temps reel via webhooks : pointage, conge, paie, onboarding.', category: 'api', icon: Webhook, status: 'available' },
  { name: 'Stripe', description: 'Paiement par carte et gestion des abonnements SaaS.', category: 'payment', icon: CreditCard, status: 'available' },
  { name: 'Chargily', description: 'Paiement local Algerie (CIB, EDAHABIA) pour le marche DZ.', category: 'payment', icon: CreditCard, status: 'available' },
  { name: 'SEPA / Virements bancaires', description: 'Export des fichiers de virement pour les banques (SEPA, CPA, BNA).', category: 'export', icon: FileSpreadsheet, status: 'available' },
  { name: 'SSO SAML / OAuth', description: 'Single Sign-On entreprise avec fournisseurs SAML 2.0 et OAuth 2.0.', category: 'security', icon: Shield, status: 'coming_soon' },
  { name: 'SAP / Sage', description: 'Export comptable compatible SAP et Sage pour la synchronisation financiere.', category: 'export', icon: Globe, status: 'coming_soon' },
]

export default function IntegrationsPage() {
  const [isDark, setIsDark] = useState(false)
  useScrollReveal()
  const { locale } = useVitrineLocale()
  const copy = integrationsCopy[locale] ?? integrationsCopy.fr

  const categories = [...new Set(integrations.map((i) => i.category))]

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <section className="relative pt-32 pb-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-500/[0.08] border border-blue-500/15 text-blue-700 dark:text-blue-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse" />
              {copy.badge}
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              {copy.title}{' '}
              <span className="bg-gradient-to-r from-blue-500 to-violet-500 bg-clip-text text-transparent">
                {copy.titleHighlight}
              </span>
            </h1>
            <p className="text-xl text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">{copy.subtitle}</p>
          </motion.div>

          {categories.map((cat) => {
            const catLabel = (copy.categories as Record<string, string>)[cat] ?? cat
            const items = integrations.filter((i) => i.category === cat)
            return (
              <div key={cat} className="mb-12">
                <h2 className="text-lg font-bold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wide">
                  {catLabel}
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {items.map((integration, index) => {
                    const Icon = integration.icon
                    return (
                      <motion.div
                        key={integration.name}
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.4, delay: index * 0.05 }}
                        className="group relative bg-white dark:bg-slate-900/80 rounded-xl border border-slate-200/80 dark:border-slate-800/80 p-5 transition-all duration-200 hover:shadow-md hover:border-blue-200/50 dark:hover:border-blue-800/50"
                      >
                        <div className="flex items-start gap-4">
                          <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                            <Icon className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1">
                              <h3 className="font-bold text-slate-900 dark:text-white text-sm">{integration.name}</h3>
                              <span
                                className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${
                                  integration.status === 'available'
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                }`}
                              >
                                {integration.status === 'available' ? copy.available : copy.comingSoon}
                              </span>
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                              {integration.description}
                            </p>
                          </div>
                        </div>
                      </motion.div>
                    )
                  })}
                </div>
              </div>
            )
          })}
        </div>
      </section>

      <Footer />
    </div>
  )
}
