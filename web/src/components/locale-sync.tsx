'use client';

import { useEffect } from 'react';
import { applyDocumentLocale, getPreferredLocale, getStoredUser } from '@/lib/i18n';

export function LocaleSync() {
  useEffect(() => {
    const user = getStoredUser();
    applyDocumentLocale(user?.language ?? getPreferredLocale(), user?.is_rtl);
  }, []);

  return null;
}
