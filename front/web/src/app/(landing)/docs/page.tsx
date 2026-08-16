'use client';

import { useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
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
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import {
  docsPageCopy,
  type DocsCategoryId,
  type DocsCategoryItem,
} from '@/modules/vitrine/data/docs';

/**
 * Page /docs localisée ×4 locales (issue #4215) : le contenu vit dans
 * docsPageCopy (modules/vitrine/data/docs.ts), les icônes/couleurs restent ici.
 */

const CATEGORY_ICONS: Record<DocsCategoryId, typeof Zap> = {
  quickstart: Zap,
  manager: LayoutDashboard,
  mobile: Smartphone,
  api: Code2,
  webhooks: Webhook,
  sdk: Package,
  playground: Play,
  admin: Shield,
  integrations: Webhook,
};

const CATEGORY_COLORS: Record<DocsCategoryId, string> = {
  quickstart: 'emerald',
  manager: 'blue',
  mobile: 'violet',
  api: 'amber',
  webhooks: 'pink',
  sdk: 'orange',
  playground: 'teal',
  admin: 'red',
  integrations: 'cyan',
};

type DocCategory = {
  title: string;
  icon: typeof Zap;
  color: string;
  items: DocsCategoryItem[];
};

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
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale } = useVitrineLocale();
  const copy = docsPageCopy[locale] ?? docsPageCopy.fr;
  const [search, setSearch] = useState('');
  const [copiedIdx, setCopiedIdx] = useState<number | null>(null);
  useScrollReveal();

  const docCategories: DocCategory[] = copy.categories.map((cat) => ({
    title: cat.title,
    icon: CATEGORY_ICONS[cat.id],
    color: CATEGORY_COLORS[cat.id],
    items: cat.items,
  }));

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
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      {/* Hero */}
      <section id="intro" className="pt-32 pb-16 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
            <BookOpen className="w-3.5 h-3.5" />
            {copy.hero.badge}
          </div>
          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white tracking-tight mb-6">
            {copy.hero.headline}{' '}
            <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
              {copy.hero.highlight}
            </span>
          </h1>
          <p className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-8">
            {copy.hero.subheadline}
          </p>

          {/* Search */}
          <div className="relative max-w-lg mx-auto mb-6">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={copy.hero.searchPlaceholder}
              className="w-full pl-12 pr-4 py-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm"
            />
          </div>

          {/* Quick access pills */}
          <div className="flex flex-wrap justify-center gap-2">
            {copy.hero.quickPills.map((tag) => (
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
      <section id="api-quickstart" className="py-12 px-4 bg-transparent dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
              <Terminal className="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">{copy.apiQuickStart.title}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">{copy.apiQuickStart.subtitle}</p>
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
                    {copiedIdx === idx ? copy.apiQuickStart.copied : copy.apiQuickStart.copy}
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
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">{copy.webhooks.title}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">{copy.webhooks.subtitle}</p>
            </div>
          </div>
          <div id="webhooks-events" className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {copy.webhooks.groups.map((grp) => (
              <div key={grp.group} className="p-4 rounded-xl border border-pink-200 dark:border-pink-800 bg-pink-50 dark:bg-pink-900/20">
                <p className="text-xs font-bold text-pink-700 dark:text-pink-300 mb-2 uppercase tracking-wide">{grp.group}</p>
                {grp.events.map((e) => (
                  <p key={e} className="text-xs font-mono text-slate-600 dark:text-slate-400 py-0.5">{e}</p>
                ))}
              </div>
            ))}
          </div>
          <p id="webhooks-security" className="text-sm text-slate-500 dark:text-slate-400 mt-4">
            {copy.webhooks.signatureNote}{' '}
            <Link href="/docs#webhooks-overview" className="text-emerald-600 dark:text-emerald-400 hover:underline">{copy.webhooks.seeDoc}</Link>
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
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">{copy.sdk.title}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">{copy.sdk.subtitle}</p>
            </div>
          </div>
          <div className="grid sm:grid-cols-3 gap-4">
            {copy.sdk.apps.map((app) => (
              <div key={app.name} className="p-4 rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-slate-900">
                <span className="inline-block px-2 py-0.5 rounded text-xs font-mono font-bold mb-2 bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">{app.name}</span>
                <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{app.desc}</p>
                <Link href="/mobile" className="inline-flex items-center gap-1 text-xs text-violet-600 dark:text-violet-400 hover:underline mt-3">
                  {copy.sdk.learnMore} <ExternalLink className="w-3 h-3" />
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Kiosk terrain */}
      <section id="kiosk" className="py-12 px-4 bg-emerald-50 dark:bg-emerald-900/10 border-y border-emerald-100 dark:border-emerald-900/30">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
              <Terminal className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">{copy.kiosk.title}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">{copy.kiosk.subtitle}</p>
            </div>
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            <div className="p-5 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-900">
              <h3 className="font-semibold text-slate-900 dark:text-white text-sm mb-2">{copy.kiosk.installTitle}</h3>
              <ol className="list-decimal list-inside space-y-1.5 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {copy.kiosk.installSteps.map((step) => (
                  <li key={step}>{step}</li>
                ))}
              </ol>
            </div>
            <div className="p-5 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-900">
              <h3 className="font-semibold text-slate-900 dark:text-white text-sm mb-2">{copy.kiosk.howTitle}</h3>
              <ul className="space-y-1.5 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {copy.kiosk.howItems.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </div>
          </div>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-4">
            {copy.kiosk.sourceNote} <code className="bg-slate-100 dark:bg-slate-800 px-1 rounded text-xs">front/zkteco-kiosk/</code>.{' '}
            <Link href="/download#kiosk" className="text-emerald-600 dark:text-emerald-400 hover:underline">{copy.kiosk.seeDownload}</Link>
          </p>
        </div>
      </section>

      {/* Sécurité & RGPD */}
      <section id="security" className="py-12 px-4 bg-slate-50 dark:bg-slate-900/60 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
              <Shield className="w-5 h-5 text-red-600 dark:text-red-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">{copy.security.title}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">{copy.security.subtitle}</p>
            </div>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {copy.security.items.map((item) => (
              <div key={item.title} className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900">
                <h3 className="font-semibold text-slate-900 dark:text-white text-sm mb-1.5">{item.title}</h3>
                <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Applications mobiles — installation */}
      <section id="mobile-install" className="py-12 px-4 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
              <Smartphone className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">{copy.mobileInstall.title}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">{copy.mobileInstall.subtitle}</p>
            </div>
          </div>
          <div className="grid sm:grid-cols-3 gap-4">
            {copy.mobileInstall.apps.map((app) => (
              <div key={app.name} className="p-4 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-900">
                <h3 className="font-semibold text-slate-900 dark:text-white text-sm mb-1.5">{app.name}</h3>
                <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-3">{app.desc}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mb-3">
                  {copy.mobileInstall.storeNote} <span className="font-semibold text-emerald-600 dark:text-emerald-400">{copy.mobileInstall.soon}</span>.
                </p>
                <Link href={app.href} className="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 hover:underline">
                  {copy.mobileInstall.joinTesters} <ExternalLink className="w-3 h-3" />
                </Link>
              </div>
            ))}
          </div>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-4">
            {copy.mobileInstall.moreDetails} <Link href="/mobile" className="text-emerald-600 dark:text-emerald-400 hover:underline">{copy.mobileInstall.mobilePage}</Link>.
          </p>
        </div>
      </section>

      {/* Doc catégories */}
      <section className="py-16 px-4">
        <div className="max-w-7xl mx-auto space-y-12">
          {filtered.length === 0 && (
            <div className="text-center py-16 text-slate-400">
              <FileText className="w-12 h-12 mx-auto mb-3 opacity-30" />
              <p className="text-lg">{copy.search.noResults.replace('{query}', search)}</p>
              <button onClick={() => setSearch('')} className="mt-3 text-sm text-emerald-600 hover:underline">
                {copy.search.clear}
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
      <section className="py-16 bg-transparent dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
        <div className="max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-8">{copy.quickLinks.title}</h2>
          <div className="grid sm:grid-cols-3 gap-4">
            {copy.quickLinks.links.map((link, idx) => {
              const icons = [Terminal, Key, Server];
              const Icon = icons[idx] ?? Server;
              return (
                <Link
                  key={link.label}
                  href={link.href}
                  className="p-6 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors group"
                >
                  <Icon className="w-6 h-6 text-emerald-600 dark:text-emerald-400 mb-3" />
                  <h3 className="font-semibold text-slate-900 dark:text-white text-sm">{link.label}</h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{link.desc}</p>
                </Link>
              );
            })}
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
