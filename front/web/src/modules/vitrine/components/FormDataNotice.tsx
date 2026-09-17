'use client';

import Link from 'next/link';

import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Mention d'information sous les formulaires publics (#7593).
 *
 * Avant : les formulaires de contact, de démonstration et d'inscription à la
 * newsletter ne portaient **aucune mention** de finalité ni de base légale —
 * seuls le tunnel d'inscription et le paiement citaient CGU et confidentialité.
 * Le principe de transparence demande de dire, au moment où l'on collecte,
 * pourquoi et sur quel fondement.
 */
export function FormDataNotice({ purpose }: { purpose: 'contact' | 'demo' | 'newsletter' }) {
  const { locale } = useVitrineLocale();
  const key = `consent.formNotice${purpose.charAt(0).toUpperCase()}${purpose.slice(1)}`;

  return (
    <p className="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
      {t(locale, key)}{' '}
      <Link
        href="/privacy"
        className="underline decoration-slate-300 underline-offset-2 hover:text-slate-700 dark:hover:text-slate-200"
      >
        {t(locale, 'consent.privacyLink')}
      </Link>
    </p>
  );
}
