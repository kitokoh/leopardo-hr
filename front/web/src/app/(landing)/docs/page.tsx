'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import {
  BookOpen,
  Code2,
  Copy,
  ExternalLink,
  FileText,
  Key,
  LayoutDashboard,
  Package,
  Play,
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
      { title: 'Introduction', desc: "Vue d'ensemble de Leopardo RH — Mobile-First Company OS", href: '/docs#intro' },
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
    title: 'Applications Mobiles',
    icon: Smartphone,
    color: 'violet',
    items: [
      { title: 'Leopardo Employee', desc: 'Pointage, demandes, bulletin, notifications push', href: '/docs#mobile-employee' },
      { title: 'Leopardo Manager', desc: 'Equipe, horaires, taches, approbations', href: '/docs#mobile-manager' },
      { title: 'Platform Admin', desc: 'Super-admin : creation tenant, supervision', href: '/docs#mobile-platform-admin' },
      { title: 'Notifications push (FCM)', desc: 'Configurer Firebase Cloud Messaging', href: '/docs#mobile-push' },
    ],
  },
  {
    title: 'API REST — Reference',
    icon: Code2,
    color: 'amber',
    items: [
      { title: 'Authentification', desc: 'Bearer token, /auth/login, /auth/me, Google OAuth', href: '/docs#api-auth' },
      { title: 'Employes & RH', desc: 'CRUD employes, absences, pointages, paie', href: '/docs#api-hr' },
      { title: 'Platform Admin', desc: 'Tenants, creation entreprise, super-admin', href: '/docs#api-platform' },
      { title: 'Erreurs & pagination', desc: 'Codes erreur standards, throttling, curseur', href: '/docs#api-errors' },
    ],
  },
  {
    title: 'Webhooks & Events',
    icon: Webhook,
    color: 'pink',
    items: [
      { title: 'Introduction aux webhooks', desc: 'Signature HMAC-SHA256, retry, idempotence', href: '/docs#webhooks-intro' },
      { title: 'Evenements disponibles', desc: 'attendance.*, leave.*, salary_advance.*, payroll.*', href: '/docs#webhooks-events' },
      { title: 'Securite & verification', desc: 'Valider la signature X-Leopardo-Signature', href: '/docs#webhooks-security' },
      { title: 'Tester en local', desc: 'ngrok, cli-test, replay d\'evenements', href: '/docs#webhooks-testing' },
    ],
  },
  {
    title: 'SDK Mobiles',
    icon: Package,
    color: 'orange',
    items: [
      { title: 'leopardo_core (Flutter)', desc: 'Package partagé — ApiClient, SecureStorage, modeles', href: '/docs#sdk-core' },
      { title: 'Auth & Google Sign-In', desc: 'GoogleSignIn v7+ initialize(), idToken, backend JWT', href: '/docs#sdk-auth' },
      { title: 'Notifications (FCM)', desc: 'FirebaseMessaging, foreground/background, deep links', href: '/docs#sdk-fcm' },
      { title: 'Publication & CI', desc: 'GitHub Actions flutter-ci.yml, build, tests', href: '/docs#sdk-ci' },
    ],
  },
  {
    title: 'API Playground',
    icon: Play,
    color: 'teal',
    items: [
      { title: 'Environnement sandbox', desc: 'URL demo Render, comptes de test, token Bearer demo', href: '/docs#playground-sandbox' },
      { title: 'Explorer les endpoints', desc: 'Interface Swagger / Redoc interactive', href: '/docs#playground-explorer' },
      { title: 'Exemples cURL', desc: "Collection d'appels prets a l'emploi pour tous les modules", href: '/docs#playground-curl' },
      { title: 'Tokens developpeur', desc: 'Creer un token scope-reduit pour tests partenaires', href: '/docs#playground-tokens' },
    ],
  },
  {
    title: 'Administration',
    icon: Shield,
    color: 'red',
    items: [
      { title: 'Roles & permissions', desc: 'Principal, RH, Employe, Super Admin, RBAC', href: '/docs#rbac' },
      { title: 'Multi-tenant', desc: 'Architecture par schema PostgreSQL', href: '/docs#multi-tenant' },
      { title: 'Securite & RGPD', desc: 'Chiffrement, audit trail, conformite', href: '/docs#security' },
      { title: 'Deploiement', desc: 'Docker, Render, Vercel, variables env', href: '/docs#deploy' },
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
      { title: 'Guide partenaire API', desc: "Guide d'integration pour ISV et partenaires", href: '/docs#partner-api' },
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
  pink: { bg: 'bg-pink-50 dark:bg-pink-900/20', icon: 'text-pink-600 dark:text-pink-400', border: 'border-pink-200 dark:border-pink-800' },
  orange: { bg: 'bg-orange-50 dark:bg-orange-900/20', icon: 'text-orange-600 dark:text-orange-400', border: 'border-orange-200 dark:border-orange-800' },
  teal: { bg: 'bg-teal-50 dark:bg-teal-900/20', icon: 'text-teal-600 dark:text-teal-400', border: 'border-teal-200 dark:border-teal-800' },
};

/** Minimal code-block sample for the REST API section */
const apiSamples = [
  {
    label: 'Login',
    lang: 'bash',
    code: `POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "manager@acme.com",
  "password": "secret",
  "device_name": "Mobile App"
}`,
  },
  {
    label: 'Bearer token',
    lang: 'bash',
    code: `GET /api/v1/auth/me
Authorization: Bearer <token>`,
  },
  {
    label: 'Webhook payload',
    lang: 'json',
    code: `{
  "event": "attendance.checked_in",
  "tenant_id": "acme",
  "data": {
    "employee_id": 42,
    "checked_in_at": "2026-05-31T08:00:00Z"
  }
}`,
  },
];

export default function DocsPage() {
  const [isDark, setIsDark] = useState(false);
  const [search, setSearch] = useState('');
  const [copiedIdx, setCopiedIdx] = useState<number | null>(null);
  useScrollReveal();

  const filtered = search.trim()
    ? docCategories.map(cat => ({
        ...cat,
        items: cat.items.filter(
          item =>
            item.title.toLowerCase().includes(search.toLowerCase()) ||
            item.desc.toLowerCase().includes(search.toLowerCase()),
        ),
      })).filter(cat => cat.items.length > 0)
    : docCategories;

  function copyCode(idx: number, code: string) {
    navigator.clipboard.writeText(code).then(() => {
      setCopiedIdx(idx);
      setTimeout(() => setCopiedIdx(null), 1500);
    });
  }

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* Hero */}
      <section className="pt-32 pb-16 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
            <BookOpen className="w-3.5 h-3.5" />
            Documentation — Developer Ecosystem
          </div>
          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white tracking-tight mb-6">
            Tout savoir sur{' '}
            <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
              Leopardo RH
            </span>
          </h1>
          <p className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-8">
            Guides, references API REST, webhooks, SDK mobiles, playground interactif et bonnes pratiques pour
            integrer et etendre votre Mobile-First Company OS.
          </p>

          {/* Search */}
          <div className="relative max-w-lg mx-auto mb-6">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Rechercher dans la documentation..."
              className="w-full pl-12 pr-4 py-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm"
            />
          </div>

          {/* Quick access pills */}
          <div className="flex flex-wrap justify-center gap-2">
            {['API REST', 'Webhooks', 'SDK Flutter', 'Playground', 'Authentification', 'Multi-tenant'].map((tag) => (
              <button
                key={tag}
                onClick={() => setSearch(tag)}
                className="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-medium hover:bg-emerald-100 dark:hover:bg-emerald-900/30 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors"
              >
                {tag}
              </button>
            ))}
          </div>
        </div>
      </section>

      {/* API Quick Start — code samples */}
      <section id="api-quickstart" className="py-12 px-4 bg-slate-50 dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
              <Terminal className="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">API Quick Start</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">Exemples prets a copier-coller</p>
            </div>
          </div>
          <div className="grid sm:grid-cols-3 gap-4">
            {apiSamples.map((sample, idx) => (
              <div key={idx} className="relative rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-900 dark:bg-slate-950 overflow-hidden">
                <div className="flex items-center justify-between px-4 py-2 bg-slate-800 dark:bg-slate-800 border-b border-slate-700">
                  <span className="text-xs font-mono text-slate-300">{sample.label}</span>
                  <button
                    onClick={() => copyCode(idx, sample.code)}
                    className="flex items-center gap-1 text-xs text-slate-400 hover:text-white transition-colors"
                  >
                    <Copy className="w-3 h-3" />
                    {copiedIdx === idx ? 'Copié!' : 'Copier'}
                  </button>
                </div>
                <pre className="p-4 text-xs text-emerald-300 dark:text-emerald-300 overflow-x-auto whitespace-pre-wrap leading-relaxed">
                  {sample.code}
                </pre>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Webhooks overview */}
      <section id="webhooks-overview" className="py-12 px-4">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-pink-50 dark:bg-pink-900/20 flex items-center justify-center">
              <Webhook className="w-5 h-5 text-pink-600 dark:text-pink-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">Webhooks en temps reel</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">Recevez les evenements RH directement dans vos systemes</p>
            </div>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {[
              { group: 'Pointage', events: ['attendance.checked_in', 'attendance.checked_out', 'attendance.auto_closed'] },
              { group: 'Absences', events: ['leave.requested', 'leave.approved', 'leave.rejected'] },
              { group: 'Paie & avances', events: ['salary_advance.requested', 'salary_advance.paid', 'payroll.run_completed'] },
              { group: 'Notifications', events: ['notification.sent', 'notification.failed'] },
            ].map((grp) => (
              <div key={grp.group} className="p-4 rounded-xl border border-pink-200 dark:border-pink-800 bg-pink-50 dark:bg-pink-900/20">
                <p className="text-xs font-bold text-pink-700 dark:text-pink-300 mb-2 uppercase tracking-wide">{grp.group}</p>
                {grp.events.map((e) => (
                  <p key={e} className="text-xs font-mono text-slate-600 dark:text-slate-400 py-0.5">{e}</p>
                ))}
              </div>
            ))}
          </div>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-4">
            Chaque payload est signe avec <code className="bg-slate-100 dark:bg-slate-800 px-1 rounded text-xs">X-Leopardo-Signature</code> (HMAC-SHA256).{' '}
            <Link href="/docs#webhooks-security" className="text-emerald-600 dark:text-emerald-400 hover:underline">Voir la doc →</Link>
          </p>
        </div>
      </section>

      {/* SDK Mobiles overview */}
      <section id="sdk-overview" className="py-12 px-4 bg-violet-50 dark:bg-violet-900/10 border-y border-violet-100 dark:border-violet-900/30">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
              <Package className="w-5 h-5 text-violet-600 dark:text-violet-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">SDK Mobiles Flutter</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">leopardo_core — le package partage entre les 3 apps</p>
            </div>
          </div>
          <div className="grid sm:grid-cols-3 gap-4">
            {[
              {
                name: 'leopardo_employee',
                desc: "App employe : pointage, bulletin, demandes d'absence, notifications",
                color: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
              },
              {
                name: 'leopardo_manager',
                desc: "App manager : equipe, horaires, taches, validation avances, paie",
                color: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
              },
              {
                name: 'leopardo_platform_admin',
                desc: "Super-admin : creation tenants, provisioning, 2FA, monitoring",
                color: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
              },
            ].map((app) => (
              <div key={app.name} className="p-4 rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-slate-900">
                <span className={`inline-block px-2 py-0.5 rounded text-xs font-mono font-bold mb-2 ${app.color}`}>{app.name}</span>
                <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{app.desc}</p>
                <Link href="/mobile" className="inline-flex items-center gap-1 text-xs text-violet-600 dark:text-violet-400 hover:underline mt-3">
                  En savoir plus <ExternalLink className="w-3 h-3" />
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Doc categories */}
      <section className="py-16 px-4">
        <div className="max-w-7xl mx-auto space-y-12">
          {filtered.length === 0 && (
            <div className="text-center py-16 text-slate-400">
              <FileText className="w-12 h-12 mx-auto mb-3 opacity-30" />
              <p className="text-lg">Aucun resultat pour « {search} »</p>
              <button onClick={() => setSearch('')} className="mt-3 text-sm text-emerald-600 hover:underline">
                Effacer la recherche
              </button>
            </div>
          )}
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
              { icon: Key, label: "Guide d'authentification", desc: 'Bearer tokens, Google OAuth, scopes', href: '/docs#api-auth' },
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
