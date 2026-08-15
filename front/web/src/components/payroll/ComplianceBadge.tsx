'use client';

import type { ComponentType } from 'react';
import { ShieldCheck, ShieldAlert, ShieldQuestion } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { interpolate, t } from '@/lib/i18n/locale-catalog';

/**
 * Issue #2116 — bloc `compliance` du contrat paie (#1872) : niveau de
 * confiance des règles pays, avertissement, source légale, date de
 * vérification experte. Même contrat que `PayrollCalculationPresenter`
 * côté API.
 */
export interface ComplianceBlock {
  level?: string;
  warning?: string | null;
  warning_key?: string | null;
  source?: string | null;
  verification_date?: string | null;
}

const LEVEL_STYLES: Record<string, { pill: string; icon: ComponentType<{ className?: string }> }> = {
  production: { pill: 'bg-emerald-50 text-emerald-700 ring-emerald-200', icon: ShieldCheck },
  pilot: { pill: 'bg-amber-50 text-amber-700 ring-amber-200', icon: ShieldAlert },
  placeholder: { pill: 'bg-red-50 text-red-700 ring-red-200', icon: ShieldAlert },
  unknown: { pill: 'bg-slate-100 text-slate-500 ring-slate-200', icon: ShieldQuestion },
};

interface ComplianceBadgeProps {
  compliance: ComplianceBlock;
  countryCode?: string | null;
  locale: AppLocale;
}

/**
 * Badge discret de conformité : niveau localisé (`payroll.confidence.*`),
 * avertissement spécifique du contrat s'il diffère du message générique,
 * puis source + date de vérification en sous-texte. Rétro-compatible :
 * le parent n'affiche ce composant que si le payload expose `compliance`.
 */
export default function ComplianceBadge({ compliance, countryCode, locale }: ComplianceBadgeProps) {
  const level = compliance.level && LEVEL_STYLES[compliance.level] ? compliance.level : 'unknown';
  const { pill, icon: Icon } = LEVEL_STYLES[level];

  const label = t(locale, `payroll.confidence.level_${level}`, level);
  const genericMessage = interpolate(
    t(locale, `payroll.confidence.${level}.message`, ''),
    { country: countryCode ?? '' },
  );
  const warning =
    compliance.warning && compliance.warning !== genericMessage ? compliance.warning : null;
  const subText = [compliance.source, compliance.verification_date]
    .filter((part): part is string => Boolean(part))
    .join(' · ');

  return (
    <div className="flex max-w-[240px] flex-col items-start gap-0.5">
      <span
        role="status"
        title={warning ?? (genericMessage || label)}
        className={`inline-flex max-w-full items-center gap-1 truncate rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider ring-1 ring-inset ${pill}`}
      >
        <Icon className="h-3 w-3 shrink-0" />
        <span className="truncate">{label}</span>
      </span>
      {warning ? (
        <span className="max-w-full truncate text-[10px] leading-tight text-slate-400" title={warning}>
          {warning}
        </span>
      ) : null}
      {subText ? (
        <span className="max-w-full truncate text-[10px] leading-tight text-slate-400" title={subText}>
          {subText}
        </span>
      ) : null}
    </div>
  );
}
