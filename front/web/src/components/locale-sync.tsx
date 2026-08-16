'use client';

import { useEffect } from 'react';
import { applyDocumentLocale, getPreferredLocale, getStoredUser, normalizeLocale } from '@/lib/i18n';

export function LocaleSync() {
  useEffect(() => {
    // #4173 : la locale portée par l'URL (?lang=) prime sur localStorage —
    // un visiteur partageant /?lang=en doit voir du EN même si une préférence
    // locale plus ancienne est stockée côté client.
    const urlLang = new URLSearchParams(window.location.search).get('lang');
    const user = getStoredUser();
    const locale = normalizeLocale(urlLang ?? user?.language ?? getPreferredLocale());
    applyDocumentLocale(locale, user?.is_rtl);
  }, []);

  return null;
}
