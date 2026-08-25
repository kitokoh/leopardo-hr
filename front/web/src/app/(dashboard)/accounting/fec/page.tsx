'use client';

import { useState } from 'react';
import { Download, FileDown, Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

const currentPeriod = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

/**
 * #5534 — Export FEC DGFiP : téléchargement CSV du journal normalisé
 * (13 colonnes) pour une période donnée.
 */
export default function FecPage() {
  const locale = getPreferredLocale();
  const [period, setPeriod] = useState(currentPeriod());
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const download = async () => {
    setDownloading(true);
    setError(null);
    try {
      const res = await apiFetch(`/accounting/journal/export-fec?period=${period}`);
      if (!res.ok) {
        setError(t(locale, 'accountingModule.fecError'));
        return;
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `FEC-${period}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError(t(locale, 'accountingModule.fecError'));
    } finally {
      setDownloading(false);
    }
  };

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.navFec')}
      subtitle={t(locale, 'accountingModule.balanceSubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      <section className="max-w-lg rounded-3xl border border-app-border bg-white p-6 shadow-sm">
        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50">
          <FileDown className="h-6 w-6 text-indigo-600" />
        </div>
        <label className="mb-2 block text-sm font-bold text-slate-600">{t(locale, 'accountingModule.periodLabel')}</label>
        <input
          type="month"
          value={period}
          onChange={(e) => setPeriod(e.target.value)}
          className="w-full rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
        />
        <button
          onClick={() => void download()}
          disabled={downloading}
          className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-indigo-700 disabled:opacity-60"
        >
          {downloading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
          {t(locale, 'accountingModule.ledgerExportFec')}
        </button>
        {error && (
          <p className="mt-3 text-sm font-medium text-red-600" role="alert">
            {error}
          </p>
        )}
      </section>
    </ModulePageShell>
  );
}
