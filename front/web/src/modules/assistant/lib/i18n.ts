/**
 * BOS-035 (#8224) — accès typé au catalogue i18n du module Assistant.
 *
 * Même mécanique que `tc()` de `src/lib/communication.ts` : TOUTES les
 * chaînes visibles du module passent par le catalogue partagé
 * (`shared/i18n/locales`, section `assistant.*`) — garde PA2-I18N-014.
 */

import type { AppLocale } from '@/lib/i18n';
import { interpolate, t } from '@/lib/i18n/locale-catalog';

export function ta(locale: AppLocale, key: string, vars?: Record<string, string | number>): string {
  const value = t(locale, `assistant.${key}`, key);
  return vars ? interpolate(value, vars) : value;
}
