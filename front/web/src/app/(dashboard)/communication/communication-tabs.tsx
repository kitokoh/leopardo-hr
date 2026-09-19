'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Inbox, MailCheck, Settings } from 'lucide-react';

import { getPreferredLocale } from '@/lib/i18n';
import { tc } from '@/lib/communication';

/**
 * BC-29 COMMUNICATION (R6, #7691) — onglets communs des trois écrans du
 * module : boîte connectée, file de confirmations, réglages.
 */
export function CommunicationTabs() {
  const locale = getPreferredLocale();
  const pathname = usePathname();

  const tabs = [
    { href: '/communication', label: tc(locale, 'tabs.inbox'), icon: Inbox },
    { href: '/communication/replies', label: tc(locale, 'tabs.replies'), icon: MailCheck },
    { href: '/communication/settings', label: tc(locale, 'tabs.settings'), icon: Settings },
  ];

  return (
    <nav
      aria-label={tc(locale, 'moduleTitle')}
      className="mb-6 flex flex-wrap gap-2"
    >
      {tabs.map((tab) => {
        const active = pathname === tab.href;
        const Icon = tab.icon;

        return (
          <Link
            key={tab.href}
            href={tab.href}
            aria-current={active ? 'page' : undefined}
            className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors ${
              active
                ? 'border-cyan-500 bg-cyan-500/10 text-cyan-700'
                : 'border-slate-200 bg-white/70 text-slate-600 hover:border-cyan-300 hover:text-cyan-700'
            }`}
          >
            <Icon className="h-4 w-4" />
            {tab.label}
          </Link>
        );
      })}
    </nav>
  );
}
