'use client';

import { useEffect } from 'react';
import { applyDocumentLocale, getPreferredLocale, getStoredUser, normalizeLocale } from '@/lib/i18n';

export function LocaleSync() {
  useEffect(() => {
    const user = getStoredUser();
    applyDocumentLocale(normalizeLocale(user?.language ?? getPreferredLocale()), user?.is_rtl);
  }, []);

  return null;
}
