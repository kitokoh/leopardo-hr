'use client';

import { useCallback, useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Plus, RefreshCw, Trash2, Power, Loader2, BookOpen } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface ChartAccount {
  code: string;
  label: string;
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
  class: number;
  is_system: boolean;
  is_active: boolean;
}

const TYPE_LABEL_KEY: Record<string, string> = {
  asset: 'accountingModule.chartTypeAsset',
  liability: 'accountingModule.chartTypeLiability',
  equity: 'accountingModule.chartTypeEquity',
  revenue: 'accountingModule.chartTypeRevenue',
  expense: 'accountingModule.chartTypeExpense',
};

/**
 * #5534 — Plan comptable : liste paramétrable (classes 1-8), comptes système
 * non supprimables (désactivation seule), CRUD /accounting/chart*.
 */
export default function ChartPage() {
  const locale = getPreferredLocale();
  const [accounts, setAccounts] = useState<ChartAccount[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [typeFilter, setTypeFilter] = useState('all');
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ code: '', label: '', type: 'asset', class: '6' });

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/accounting/chart');
      const body = await res.json();
      setAccounts(body.data || []);
    } catch {
      setError(t(locale, 'accountingModule.chartError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const createAccount = async () => {
    setSaving(true);
    setError(null);
    try {
      await apiFetch('/accounting/chart', {
        method: 'POST',
        body: JSON.stringify({
          code: form.code,
          label: form.label,
          type: form.type,
          class: Number(form.class),
        }),
      });
      setShowForm(false);
      setForm({ code: '', label: '', type: 'asset', class: '6' });
      await load();
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    } finally {
      setSaving(false);
    }
  };

  const toggleActive = async (account: ChartAccount) => {
    try {
      await apiFetch(`/accounting/chart/${account.code}`, {
        method: 'PUT',
        body: JSON.stringify({ is_active: !account.is_active }),
      });
      await load();
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    }
  };

  const deleteAccount = async (account: ChartAccount) => {
    if (account.is_system) {
      return;
    }
    try {
      await apiFetch(`/accounting/chart/${account.code}`, { method: 'DELETE' });
      await load();
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    }
  };

  const filtered = typeFilter === 'all' ? accounts : accounts.filter((a) => a.type === typeFilter);

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.chartTitle')}
      subtitle={t(locale, 'accountingModule.chartSubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      {error && (
        <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          <RefreshCw className="h-4 w-4 shrink-0" />
          <span>{error}</span>
          <button onClick={() => void load()} className="ml-auto rounded-lg bg-red-100 px-3 py-1 text-xs font-bold text-red-700 transition hover:bg-red-200">
            {t(locale, 'accountingModule.retry')}
          </button>
        </div>
      )}

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <select
          value={typeFilter}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-medium text-slate-700"
        >
          <option value="all">{t(locale, 'accountingModule.allTypes')}</option>
          {Object.entries(TYPE_LABEL_KEY).map(([value, key]) => (
            <option key={value} value={value}>
              {t(locale, key)}
            </option>
          ))}
        </select>
        <button
          onClick={() => setShowForm((v) => !v)}
          className="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-amber-600"
        >
          <Plus className="h-4 w-4" />
          {t(locale, 'accountingModule.chartAdd')}
        </button>
      </div>

      {showForm && (
        <motion.section
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-2xl border border-amber-200 bg-amber-50/50 p-5"
        >
          <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
            <BookOpen className="h-4 w-4 text-amber-600" />
            {t(locale, 'accountingModule.chartAddTitle')}
          </h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input
              value={form.code}
              onChange={(e) => setForm({ ...form, code: e.target.value })}
              placeholder={t(locale, 'accountingModule.chartCode')}
              className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
            />
            <input
              value={form.label}
              onChange={(e) => setForm({ ...form, label: e.target.value })}
              placeholder={t(locale, 'accountingModule.chartLabel')}
              className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
            />
            <select
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value })}
              className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
            >
              {Object.entries(TYPE_LABEL_KEY).map(([value, key]) => (
                <option key={value} value={value}>
                  {t(locale, key)}
                </option>
              ))}
            </select>
            <select
              value={form.class}
              onChange={(e) => setForm({ ...form, class: e.target.value })}
              className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
            >
              {[1, 2, 3, 4, 5, 6, 7, 8].map((c) => (
                <option key={c} value={c}>
                  {t(locale, 'accountingModule.chartClass')} {c}
                </option>
              ))}
            </select>
          </div>
          <div className="mt-4 flex gap-2">
            <button
              onClick={() => void createAccount()}
              disabled={saving || !form.code || !form.label}
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-600 disabled:opacity-50"
            >
              {saving && <Loader2 className="h-4 w-4 animate-spin" />}
              {t(locale, 'accountingModule.chartSave')}
            </button>
            <button onClick={() => setShowForm(false)} className="rounded-xl border border-app-border bg-white px-4 py-2 text-sm font-bold text-slate-600">
              {t(locale, 'accountingModule.chartCancel')}
            </button>
          </div>
        </motion.section>
      )}

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border bg-transparent/50">
                <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.chartCode')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.chartLabel')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.chartType')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.chartClass')}</th>
                <th className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.chartStatus')}</th>
                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500"> </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {loading ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.loading')}</td></tr>
              ) : filtered.length === 0 ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.chartEmpty')}</td></tr>
              ) : (
                filtered.map((account) => (
                  <tr key={account.code} className="transition-colors hover:bg-transparent/60">
                    <td className="px-6 py-3 font-mono text-xs font-bold text-slate-800">{account.code}</td>
                    <td className="px-4 py-3 font-medium text-slate-700">
                      {account.label}
                      {account.is_system && (
                        <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                          {t(locale, 'accountingModule.chartSystemNote')}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, TYPE_LABEL_KEY[account.type] ?? 'accountingModule.chartTypeAsset')}</td>
                    <td className="px-4 py-3 text-slate-500">{account.class}</td>
                    <td className="px-4 py-3 text-center">
                      <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${account.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                        {account.is_active ? t(locale, 'accountingModule.chartActive') : t(locale, 'accountingModule.chartInactive')}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-right">
                      <button
                        onClick={() => void toggleActive(account)}
                        title={t(locale, 'accountingModule.chartToggle')}
                        className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-amber-600"
                      >
                        <Power className="h-4 w-4" />
                      </button>
                      {!account.is_system && (
                        <button
                          onClick={() => void deleteAccount(account)}
                          title={t(locale, 'accountingModule.chartDelete')}
                          className="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
    </ModulePageShell>
  );
}
