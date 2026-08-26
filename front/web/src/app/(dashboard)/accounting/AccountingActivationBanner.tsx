'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { Rocket, X } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * #5626 — Bandeau d'activation Comptabilité sur la page d'accueil du module.
 *
 * Visible uniquement si GET /accounting/activation retourne completed=false.
 * Se masque dès que l'activation est complète (ou si l'API échoue — fail open,
 * pour ne pas bloquer l'accès au module en cas d'erreur réseau).
 */
export function AccountingActivationBanner() {
  const locale = getPreferredLocale();
  const [show, setShow] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function check() {
      try {
        const res = await apiFetch('/accounting/activation');
        const body = await res.json();
        if (!cancelled && body?.data?.completed === false) {
          setShow(true);
        }
      } catch {
        // Fail open: on error, don't show the banner (don't block module access).
      }
    }

    void check();
    return () => { cancelled = true; };
  }, []);

  if (!show || dismissed) return null;

  return (
    <div
      role="status"
      className="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 shadow-sm"
    >
      <Rocket className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" aria-hidden="true" />
      <div className="flex-1">
        <p className="font-semibold">{t(locale, 'accountingActivation.bannerTitle')}</p>
        <p className="mt-0.5 text-amber-700">{t(locale, 'accountingActivation.bannerBody')}</p>
        <Link
          href="/accounting/activation"
          className="mt-2 inline-flex items-center gap-1 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-amber-600"
        >
          {t(locale, 'accountingActivation.bannerCta')}
        </Link>
      </div>
      <button
        onClick={() => setDismissed(true)}
        className="rounded p-0.5 text-amber-500 transition hover:text-amber-700"
        aria-label={t(locale, 'common.dismiss')}
      >
        <X className="h-4 w-4" />
      </button>
    </div>
  );
}
