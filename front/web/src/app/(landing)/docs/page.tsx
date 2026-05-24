'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import {
  BookOpen,
  Code2,
  FileText,
  Key,
  LayoutDashboard,
  Search,
  Server,
  Shield,
  Smartphone,
  Terminal,
  Webhook,
  Zap,
} from 'lucide-react';

const docCategories = [
  {
    title: 'Demarrage rapide',
    icon: Zap,
    color: 'emerald',
    items: [
      { title: 'Introduction', desc: 'Vue d\'ensemble de Leopardo RH', href: '/docs#intro' },
      { title: 'Inscription & premier tenant', desc: 'Creer un compte et configurer votre entreprise', href: '/docs#onboarding' },
      { title: 'Inviter votre equipe', desc: 'Ajouter des managers et des employes', href: '/docs#team' },
      { title: 'Pointage depuis le kiosque', desc: 'Configurer une borne ZKTeco', href: '/docs#kiosk' },
    ],
  },
  {
    title: 'Espace Manager',
    icon: LayoutDashboard,
    color: 'blue',
    items: [
      { title: 'Tableau de bord', desc: 'KPIs, alertes, activite recente', href: '/docs#dashboard' },
      { title: 'Gestion des absences', desc: 'Demandes, approbations, soldes', href: '/docs#leaves' },
      { title: 'Paie & bulletins', desc: 'Lancer une paie, generer les bulletins PDF', href: '/docs#payroll' },
      { title: 'Contrats & documents', desc: 'Gestion documentaire securisee', href: '/docs#contracts' },
    ],
  },
  {
    title: 'Application Mobile',
    icon: Smartphone,
    color: 'violet',
    items: [
      { title: 'Installation', desc: 'Android APK ou App Store', href: '/docs#mobile-install' },
      { title: 'Pointage mobile', desc: 'Check-in/check-out avec geolocalisation', href: '/docs#mobile-attendance' },
      { title: 'Mes bulletins', desc: 'Consulter et telecharger les fiches de paie', href: '/docs#mobile-payslips' },
      { title: 'Notifications push', desc: 'Configurer Firebase Cloud Messaging', href: '/docs#mobile-push' },
    ],
  },
  {
    title: 'API Reference',
    icon: Code2,
    color: 'amber',
    items: [
      { title: 'Authentification', desc: 'Bearer token, login, /auth/me', href: '/docs#api-auth' },
      { title: 'Endpoints RH', desc: 'Employes, absences, pointages, paie', href: '/docs#api-hr' },
      { title: 'Webhooks', desc: 'Evenements temps reel vers votre systeme', href: '/docs#api-webhooks' },
      { title: 'Erreurs & rate limiting', desc: 'Codes erreur, throttling, pagination', href: '/docs#api-errors' },
    ],
  },
  {
    title: 'Administration',
    icon: Shield,
    color: 'red',
    items: [
      { title: 'Roles & permissions', desc: 'Principal, RH, Employe, Super Admin', href: '/docs#rbac' },
      { title: 'Multi-tenant', desc: 'Architecture par schema PostgreSQL', href: '/docs#multi-tenant' },
      { title: 'Securite', desc: 'RGPD, chiffrement, audit trail', href: '/docs#security' },
      { title: 'Deploiement', desc: 'Docker, Render, Vercel', href: '/docs#deploy' },
    ],
  },
  {
    title: 'Integrations',
    icon: Webhook,
    color: 'cyan',
    items: [
      { title: 'ZKTeco', desc: 'Configuration des bornes biometriques', href: '/docs#zkteco' },
      { title: 'Calendrier (CalDAV)', desc: 'Synchronisation agenda', href: '/docs#calendar' },
      { title: 'Exports bancaires', desc: 'SEPA, CCP, CSV', href: '/docs#exports' },
      { title: 'API partenaire', desc: 'Guide d\'integration pour partenaires', href: '/docs#partner-api' },
    ],
  },
];

const colorMap: Record<string, { bg: string; icon: string; border: string }> = {
  emerald: { bg: 'bg-emerald-50 dark:bg-emerald-900/20', icon: 'text-emerald-600 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800' },
  blue: { bg: 'bg-blue-50 dark:bg-blue-900/20', icon: 'text-blue-600 dark:text-blue-400', border: 'border-blue-200 dark:border-blue-800' },
  violet: { bg: 'bg-violet-50 dark:bg-violet-900/20', icon: 'text-violet-600 dark:text-violet-400', border: 'border-violet-200 dark:border-violet-800' },
  amber: { bg: 'bg-amber-50 dark:bg-amber-900/20', icon: 'text-amber-600 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800' },
  red: { bg: 'bg-red-50 dark:bg-red-900/20', icon: 'text-red-600 dark:text-red-400', border: 'border-red-200 dark:border-red-800' },
  cyan: { bg: 'bg-cyan-50 dark:bg-cyan-900/20', icon: 'text-cyan-600 dark:text-cyan-400', border: 'border-cyan-200 dark:border-cyan-800' },
};

export default function DocsPage() {
  const [isDark, setIsDark] = useState(false);
  const [search, setSearch] = useState('');
  useScrollReveal();

  const filtered = search.trim()
    ? docCategories.map(cat => ({
        ...cat,
        items: cat.items.filter(
          item => item.title.toLowerCase().includes(search.toLowerCase()) || item.desc.toLowerCase().includes(search.toLowerCase())
        ),
      })).filter(cat => cat.items.length > 0)
    : docCategories;

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* Hero */}
      <section className="pt-32 pb-16 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
            <BookOpen className="w-3.5 h-3.5" />
            Documentation
          </div>
          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white tracking-tight mb-6">
            Tout savoir sur{' '}
            <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
              Leopardo RH
            </span>
          </h1>
          <p className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-8">
            Guides, references API, tutoriels et bonnes pratiques pour tirer le meilleur de votre plateforme RH.
          </p>

          {/* Search */}
          <div className="relative max-w-lg mx-auto">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Rechercher dans la documentation..."
              className="w-full pl-12 pr-4 py-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm"
            />
          </div>
        </div>
      </section>

      {/* Doc categories */}
      <section className="pb-24 px-4">
        <div className="max-w-7xl mx-auto space-y-12">
          {filtered.map((cat, i) => {
            const colors = colorMap[cat.color] || colorMap.emerald;
            return (
              <motion.div
                key={cat.title}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.05 }}
                viewport={{ once: true }}
              >
                <div className="flex items-center gap-3 mb-4">
                  <div className={`w-10 h-10 rounded-xl ${colors.bg} flex items-center justify-center`}>
                    <cat.icon className={`w-5 h-5 ${colors.icon}`} />
                  </div>
                  <h2 className="text-xl font-bold text-slate-900 dark:text-white">{cat.title}</h2>
                </div>
                <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                  {cat.items.map((item) => (
                    <Link
                      key={item.title}
                      href={item.href}
                      className={`block p-5 rounded-xl border ${colors.border} bg-white dark:bg-slate-900 hover:shadow-md transition-all group`}
                    >
                      <h3 className="font-semibold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors text-sm">
                        {item.title}
                      </h3>
                      <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">{item.desc}</p>
                    </Link>
                  ))}
                </div>
              </motion.div>
            );
          })}
        </div>
      </section>

      {/* Quick links */}
      <section className="py-16 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
        <div className="max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-8">Liens rapides</h2>
          <div className="grid sm:grid-cols-3 gap-4">
            {[
              { icon: Terminal, label: 'API Explorer', desc: 'Tester les endpoints en direct', href: '/integrations#api' },
              { icon: Key, label: 'Guide d\'authentification', desc: 'Bearer tokens et scopes', href: '/docs#api-auth' },
              { icon: Server, label: 'Guide de deploiement', desc: 'Docker, Render, Vercel', href: '/docs#deploy' },
            ].map((link) => (
              <Link
                key={link.label}
                href={link.href}
                className="p-6 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors group"
              >
                <link.icon className="w-6 h-6 text-emerald-600 dark:text-emerald-400 mb-3" />
                <h3 className="font-semibold text-slate-900 dark:text-white text-sm">{link.label}</h3>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{link.desc}</p>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
