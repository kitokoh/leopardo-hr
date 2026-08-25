'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { Download, FileText, Lock, RefreshCw, ShieldCheck } from 'lucide-react';

import { ApiError, apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Vue du document partagé (issue #5233) — espace client web sécurisé.
 *
 * Le token de partage est la credential (pattern CabinetShare #1817) : la
 * page n'expose que les données du document partagé, via l'endpoint PUBLIC
 * `GET /api/v1/accounting/documents/shared/{token}` (backend #5225).
 * La locale est passée depuis le SSR (`x-vitrine-lang`) et réutilisée pour
 * l'en-tête `Accept-Language` du fetch — `type_label` est localisé par le
 * backend, l'UI par les catalogues `accountingPortal.*` (×4).
 */

type SharedDocumentInfo = {
  number: string;
  type: string;
  type_label: string;
  status: string;
  issue_date: string;
  currency: string;
  total_ttc: number;
  expires_at: string | null;
};

type ViewState = 'loading' | 'success' | 'notFound' | 'error';

const STATUS_KEY: Record<string, string> = {
  draft: 'statusDraft',
  sent: 'statusSent',
  partially_paid: 'statusPartiallyPaid',
  paid: 'statusPaid',
  cancelled: 'statusCancelled',
  overdue: 'statusOverdue',
};

const STATUS_STYLE: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-700 ring-slate-200',
  sent: 'bg-blue-50 text-blue-700 ring-blue-200',
  partially_paid: 'bg-amber-50 text-amber-700 ring-amber-200',
  paid: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  cancelled: 'bg-rose-50 text-rose-700 ring-rose-200',
  overdue: 'bg-red-50 text-red-700 ring-red-200',
};

const INTL_LOCALE: Record<AppLocale, string> = {
  fr: 'fr-FR',
  en: 'en-US',
  tr: 'tr-TR',
  ar: 'ar-EG',
};

/** Parse une date `YYYY-MM-DD` en minuit local (évite le décalage UTC). */
function parseDateOnly(value: string): Date {
  const [year, month, day] = value.split('-').map(Number);
  return new Date(year, month - 1, day);
}

function formatDate(value: string, locale: AppLocale): string {
  const date = value.length === 10 ? parseDateOnly(value) : new Date(value);
  return new Intl.DateTimeFormat(INTL_LOCALE[locale], { dateStyle: 'medium' }).format(date);
}

function formatMoney(value: number, locale: AppLocale, currency: string): string {
  try {
    return new Intl.NumberFormat(INTL_LOCALE[locale], {
      style: 'currency',
      currency,
      currencyDisplay: 'narrowSymbol',
    }).format(value);
  } catch {
    return `${value.toFixed(2)} ${currency}`;
  }
}

export function SharedDocumentView({ token, locale }: { token: string; locale: AppLocale }) {
  const [state, setState] = useState<ViewState>('loading');
  const [info, setInfo] = useState<SharedDocumentInfo | null>(null);
  const [downloading, setDownloading] = useState(false);
  const [downloadError, setDownloadError] = useState(false);

  const load = useCallback(async () => {
    setState('loading');
    setDownloadError(false);
    try {
      const response = await apiFetch(`/accounting/documents/shared/${token}`, {
        headers: { 'Accept-Language': locale },
      });
      const payload = (await response.json()) as { data: SharedDocumentInfo };
      setInfo(payload.data);
      setState('success');
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) {
        setState('notFound');
      } else {
        setState('error');
      }
    }
  }, [token, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleDownload = async () => {
    if (!info) return;
    setDownloading(true);
    setDownloadError(false);
    try {
      const response = await apiFetch(`/accounting/documents/shared/${token}/download`, {
        headers: { 'Accept-Language': locale },
      });
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `${info.type}-${info.number}.pdf`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      URL.revokeObjectURL(url);
    } catch {
      setDownloadError(true);
    } finally {
      setDownloading(false);
    }
  };

  const statusLabel = info ? t(locale, `accountingPortal.${STATUS_KEY[info.status] ?? 'statusSent'}`) : '';

  if (state === 'loading') {
    return (
      <main className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
        <p className="text-slate-500" role="status">
          {t(locale, 'accountingPortal.loading')}
        </p>
      </main>
    );
  }

  if (state === 'notFound') {
    return (
      <main className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
        <div className="max-w-md w-full bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-8 text-center">
          <Lock className="w-10 h-10 mx-auto text-slate-300 mb-4" aria-hidden="true" />
          <h1 className="text-xl font-bold text-slate-900">{t(locale, 'accountingPortal.notFoundTitle')}</h1>
          <p className="mt-2 text-sm text-slate-600">{t(locale, 'accountingPortal.notFoundBody')}</p>
          <Link
            href="/"
            className="mt-6 inline-flex items-center gap-2 text-sm font-medium text-teal-700 hover:text-teal-800"
          >
            {t(locale, 'accountingPortal.backToSite')}
          </Link>
        </div>
      </main>
    );
  }

  if (state === 'error') {
    return (
      <main className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
        <div className="max-w-md w-full bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-8 text-center">
          <FileText className="w-10 h-10 mx-auto text-slate-300 mb-4" aria-hidden="true" />
          <h1 className="text-xl font-bold text-slate-900">{t(locale, 'accountingPortal.errorTitle')}</h1>
          <p className="mt-2 text-sm text-slate-600">{t(locale, 'accountingPortal.errorBody')}</p>
          <button
            type="button"
            onClick={() => void load()}
            className="mt-6 inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
          >
            <RefreshCw className="w-4 h-4" aria-hidden="true" />
            {t(locale, 'accountingPortal.retry')}
          </button>
        </div>
      </main>
    );
  }

  if (!info) {
    return null;
  }

  return (
    <main className="min-h-screen bg-slate-50 flex items-center justify-center px-4 py-12">
      <div className="max-w-lg w-full">
        <div className="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
          <div className="bg-teal-700 px-6 py-5">
            <div className="flex items-center gap-2 text-teal-50">
              <ShieldCheck className="w-5 h-5" aria-hidden="true" />
              <h1 className="text-lg font-semibold">{t(locale, 'accountingPortal.title')}</h1>
            </div>
            <p className="mt-1 text-sm text-teal-100">{t(locale, 'accountingPortal.subtitle')}</p>
          </div>

          <dl className="px-6 py-6 space-y-4">
            <div className="flex items-start justify-between gap-4">
              <dt className="text-sm text-slate-500">{t(locale, 'accountingPortal.number')}</dt>
              <dd className="text-sm font-semibold text-slate-900 text-end">{info.number}</dd>
            </div>
            <div className="flex items-start justify-between gap-4">
              <dt className="text-sm text-slate-500">{t(locale, 'accountingPortal.type')}</dt>
              <dd className="text-sm font-medium text-slate-900 text-end">{info.type_label}</dd>
            </div>
            <div className="flex items-start justify-between gap-4">
              <dt className="text-sm text-slate-500">{t(locale, 'accountingPortal.status')}</dt>
              <dd>
                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ${STATUS_STYLE[info.status] ?? STATUS_STYLE.sent}`}>
                  {statusLabel}
                </span>
              </dd>
            </div>
            <div className="flex items-start justify-between gap-4">
              <dt className="text-sm text-slate-500">{t(locale, 'accountingPortal.issueDate')}</dt>
              <dd className="text-sm font-medium text-slate-900 text-end">{formatDate(info.issue_date, locale)}</dd>
            </div>
            <div className="flex items-start justify-between gap-4">
              <dt className="text-sm text-slate-500">{t(locale, 'accountingPortal.total')}</dt>
              <dd className="text-base font-bold text-slate-900 text-end">{formatMoney(info.total_ttc, locale, info.currency)}</dd>
            </div>
          </dl>

          <div className="px-6 pb-6">
            <button
              type="button"
              onClick={() => void handleDownload()}
              disabled={downloading}
              className="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-60"
              aria-label={t(locale, 'accountingPortal.downloadHint')}
            >
              <Download className="w-4 h-4" aria-hidden="true" />
              {t(locale, 'accountingPortal.download')}
            </button>
            {downloadError ? (
              <p className="mt-2 text-sm text-red-600" role="alert">
                {t(locale, 'accountingPortal.downloadError')}
              </p>
            ) : null}
          </div>
        </div>

        {info.expires_at ? (
          <p className="mt-4 text-center text-xs text-slate-500">
            {t(locale, 'accountingPortal.expiresAt').replace(/:date/, formatDate(info.expires_at, locale))}
          </p>
        ) : null}
        <p className="mt-2 text-center text-xs text-slate-400">{t(locale, 'accountingPortal.securityNote')}</p>
      </div>
    </main>
  );
}
