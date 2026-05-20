'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import {
  Navbar,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import {
  Fingerprint,
  CreditCard,
  CalendarClock,
  Globe,
  FileText,
  Shield,
  Webhook,
  Smartphone,
  Building2,
  Cpu,
  ArrowRight,
} from 'lucide-react';

type Integration = {
  icon: React.ReactNode;
  name: string;
  description: string;
  status: 'available' | 'coming_soon';
  category: string;
};

const integrationsByLocale: Record<string, { title: string; subtitle: string; badge: string; statusLabels: { available: string; coming_soon: string }; categories: string[]; integrations: Integration[] }> = {
  fr: {
    title: 'Integrations',
    subtitle: 'Connectez Leopardo RH a vos outils existants',
    badge: 'Ecosysteme',
    statusLabels: { available: 'Disponible', coming_soon: 'Bientot' },
    categories: ['Tous', 'Pointage', 'Paiement', 'Calendrier', 'API', 'Securite'],
    integrations: [
      { icon: <Fingerprint className="w-6 h-6" />, name: 'ZKTeco', description: 'Pointeuses biometriques TCP/IP. Synchronisation automatique des pointages.', status: 'available', category: 'Pointage' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Stripe', description: 'Paiement SaaS par carte bancaire. Abonnements et factures automatises.', status: 'available', category: 'Paiement' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Chargily', description: 'Paiement en ligne pour l\'Algerie. CIB, EDAHABIA et virement bancaire.', status: 'available', category: 'Paiement' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Google Calendar', description: 'Synchronisation des conges et formations avec Google Calendar.', status: 'available', category: 'Calendrier' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Outlook Calendar', description: 'Synchronisation des evenements RH avec Microsoft Outlook.', status: 'available', category: 'Calendrier' },
      { icon: <Globe className="w-6 h-6" />, name: 'API REST publique', description: 'API versionnee (v1) avec documentation OpenAPI. Rate limiting par plan.', status: 'available', category: 'API' },
      { icon: <Webhook className="w-6 h-6" />, name: 'Webhooks', description: 'Notifications HTTP pour les evenements RH (embauche, paie, conge, pointage).', status: 'available', category: 'API' },
      { icon: <Shield className="w-6 h-6" />, name: 'SSO SAML/OIDC', description: 'Authentification unique via Azure AD, Google Workspace ou Okta.', status: 'coming_soon', category: 'Securite' },
      { icon: <FileText className="w-6 h-6" />, name: 'Sage Comptabilite', description: 'Export des ecritures de paie vers Sage 50/100. Format FEC compatible.', status: 'coming_soon', category: 'API' },
      { icon: <FileText className="w-6 h-6" />, name: 'QuickBooks', description: 'Synchronisation des ecritures de paie vers QuickBooks Online.', status: 'coming_soon', category: 'API' },
      { icon: <Smartphone className="w-6 h-6" />, name: 'Firebase', description: 'Push notifications pour l\'app mobile. Alertes pointage, paie et conges.', status: 'available', category: 'API' },
      { icon: <Building2 className="w-6 h-6" />, name: 'Slack / Teams', description: 'Notifications RH dans vos canaux de communication existants.', status: 'coming_soon', category: 'API' },
    ],
  },
  en: {
    title: 'Integrations',
    subtitle: 'Connect Leopardo RH to your existing tools',
    badge: 'Ecosystem',
    statusLabels: { available: 'Available', coming_soon: 'Coming soon' },
    categories: ['All', 'Attendance', 'Payment', 'Calendar', 'API', 'Security'],
    integrations: [
      { icon: <Fingerprint className="w-6 h-6" />, name: 'ZKTeco', description: 'Biometric attendance terminals via TCP/IP. Automatic attendance sync.', status: 'available', category: 'Attendance' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Stripe', description: 'SaaS card payments. Automated subscriptions and invoices.', status: 'available', category: 'Payment' },
      { icon: <CreditCard className="w-6 h-6" />, name: 'Chargily', description: 'Online payments for Algeria. CIB, EDAHABIA and bank transfer.', status: 'available', category: 'Payment' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Google Calendar', description: 'Sync leave and training events with Google Calendar.', status: 'available', category: 'Calendar' },
      { icon: <CalendarClock className="w-6 h-6" />, name: 'Outlook Calendar', description: 'Sync HR events with Microsoft Outlook.', status: 'available', category: 'Calendar' },
      { icon: <Globe className="w-6 h-6" />, name: 'Public REST API', description: 'Versioned API (v1) with OpenAPI docs. Rate limiting per plan.', status: 'available', category: 'API' },
      { icon: <Webhook className="w-6 h-6" />, name: 'Webhooks', description: 'HTTP notifications for HR events (hire, payroll, leave, attendance).', status: 'available', category: 'API' },
      { icon: <Shield className="w-6 h-6" />, name: 'SSO SAML/OIDC', description: 'Single sign-on via Azure AD, Google Workspace or Okta.', status: 'coming_soon', category: 'Security' },
      { icon: <FileText className="w-6 h-6" />, name: 'Sage Accounting', description: 'Export payroll entries to Sage 50/100. FEC-compatible format.', status: 'coming_soon', category: 'API' },
      { icon: <FileText className="w-6 h-6" />, name: 'QuickBooks', description: 'Sync payroll entries to QuickBooks Online.', status: 'coming_soon', category: 'API' },
      { icon: <Smartphone className="w-6 h-6" />, name: 'Firebase', description: 'Push notifications for the mobile app. Attendance, payroll and leave alerts.', status: 'available', category: 'API' },
      { icon: <Building2 className="w-6 h-6" />, name: 'Slack / Teams', description: 'HR notifications in your existing communication channels.', status: 'coming_soon', category: 'API' },
    ],
  },
};

export default function IntegrationsPage() {
  const [isDark, setIsDark] = useState(false);
  const [activeCategory, setActiveCategory] = useState(0);
  useScrollReveal();
  const { locale } = useVitrineLocale();

  const data = integrationsByLocale[locale] ?? integrationsByLocale.fr;
  const allCategoryLabel = data.categories[0];

  const filtered = activeCategory === 0
    ? data.integrations
    : data.integrations.filter((i) => i.category === data.categories[activeCategory]);

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <section className="relative pt-32 pb-20 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-emerald-50/50 via-white to-white dark:from-emerald-950/20 dark:via-slate-950 dark:to-slate-950" />

        <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <Cpu className="w-4 h-4" />
              {data.badge}
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              {data.title}
            </h1>
            <p className="text-xl text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">
              {data.subtitle}
            </p>
          </motion.div>

          <div className="flex flex-wrap justify-center gap-2 mb-12">
            {data.categories.map((cat, index) => (
              <button
                key={cat}
                onClick={() => setActiveCategory(index)}
                className={`px-4 py-2 rounded-full text-sm font-medium transition-all duration-200 ${
                  activeCategory === index
                    ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/25'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                }`}
              >
                {cat}
              </button>
            ))}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {filtered.map((integration, index) => (
              <motion.div
                key={integration.name}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, delay: index * 0.05 }}
                className="group bg-white dark:bg-slate-900/80 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 hover:shadow-lg hover:border-emerald-200/50 dark:hover:border-emerald-800/50 transition-all duration-300"
              >
                <div className="flex items-start justify-between mb-4">
                  <div className="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    {integration.icon}
                  </div>
                  <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${
                    integration.status === 'available'
                      ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'
                      : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'
                  }`}>
                    {data.statusLabels[integration.status]}
                  </span>
                </div>

                <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">
                  {integration.name}
                </h3>
                <p className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                  {integration.description}
                </p>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="mt-16 text-center"
          >
            <div className="inline-flex items-center gap-2 bg-slate-100 dark:bg-slate-800 rounded-full px-6 py-3 text-slate-600 dark:text-slate-400">
              <span className="text-sm">API publique documentee sur</span>
              <a href="https://gestionemployerbackend.onrender.com/docs" target="_blank" rel="noopener noreferrer" className="text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline inline-flex items-center gap-1">
                /docs <ArrowRight className="w-3 h-3" />
              </a>
            </div>
          </motion.div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
