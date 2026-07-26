'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { CalendarDays, ListChecks } from 'lucide-react';

/**
 * Module Marketing — Phase 4 (PA2-MKT-011).
 *
 * Dedicated layout for the Marketing surfaces nested under the
 * authenticated dashboard shell (`(dashboard)/layout.tsx` still handles
 * auth guard, sidebar and the feature-locked panel — this layout only
 * adds the Marketing-specific sub-navigation shared by:
 *  - `/social` — the new calendar view (this issue's main deliverable).
 *  - `/social-marketing` — the existing account-connect + list view.
 *
 * Both routes share the same underlying API (`/marketing/social-*`), so
 * this tab bar lets a marketer switch between "plan visually" (calendar)
 * and "manage everything" (list + account connection) without losing
 * context.
 */
const MARKETING_TABS = [
  { href: '/social', label: 'Calendrier', icon: CalendarDays },
  { href: '/social-marketing', label: 'Liste & compte', icon: ListChecks },
];

export default function MarketingLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  return (
    <div className="space-y-6">
      <nav className="flex w-fit gap-1 rounded-2xl border border-app-border bg-white p-1 shadow-sm" aria-label="Navigation Marketing">
        {MARKETING_TABS.map((tab) => {
          const active = pathname === tab.href;
          return (
            <Link
              key={tab.href}
              href={tab.href}
              className={`flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition ${
                active ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
              }`}
            >
              <tab.icon className="h-4 w-4" aria-hidden="true" />
              {tab.label}
            </Link>
          );
        })}
      </nav>
      {children}
    </div>
  );
}
