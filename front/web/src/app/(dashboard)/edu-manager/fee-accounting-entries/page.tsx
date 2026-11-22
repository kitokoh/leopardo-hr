'use client';

/**
 * Rapprochement comptable des frais scolaires — /edu-manager/fee-accounting-entries (#9790).
 *
 * `GET /edu-manager/fee-accounting-entries` existait (EDU-016, #5832) sans
 * page : la facturation était créée sans aucun moyen de contrôle côté client.
 * Le module EduManager ne tient PAS la comptabilité — il publie des lignes
 * équilibrées que le module Accounting consomme. Cet écran est la vue de
 * rapprochement : lecture seule, bornée au tenant.
 *
 * Le total débit/crédit est affiché en tête : c'est le contrôle qui compte
 * (les deux colonnes doivent s'égaler), et il est calculé sur les lignes
 * réellement reçues, pas sur une valeur serveur qui pourrait diverger.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Download, Scale } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button, Card, DataTable, SectionTitle, SelectInput, type Column } from '../_components/edu-ui';

type Row = Record<string, unknown>;

const SOURCE_TYPES = ['fee_charge', 'fee_payment', 'fee_waiver'];

function money(value: unknown): string {
  const amount = Number(value ?? 0);
  return Number.isFinite(amount) ? amount.toLocaleString() : '—';
}

export default function AccountingEntriesPage() {
  const locale = getPreferredLocale();

  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sourceFilter, setSourceFilter] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const query = sourceFilter ? `&source_type=${encodeURIComponent(sourceFilter)}` : '';
      const res = await apiFetch(`/edu-manager/fee-accounting-entries?per_page=200${query}`, {
        _cacheBust: true,
      });
      const json = (await res.json()) as { data?: Row[] };
      setRows(Array.isArray(json.data) ? json.data : []);
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale, sourceFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  /**
   * #9791 — export CSV du rapprochement.
   *
   * Le fichier porte ses propres totaux ET l'écart débit − crédit : le
   * contrôle d'équilibre survit à l'export, l'expert-comptable n'a pas à le
   * recalculer. Le filtre courant est transmis pour que le fichier couvre
   * exactement ce que l'écran affiche.
   */
  const exportCsv = async () => {
    try {
      const query = sourceFilter ? `?source_type=${encodeURIComponent(sourceFilter)}` : '';
      const res = await apiFetch(`/edu-manager/fee-accounting-entries/export.csv${query}`);
      if (!res.ok) {
        setError(t(locale, 'edu.accountingEntries.exportError'));
        return;
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'rapprochement-frais.csv';
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError(t(locale, 'edu.accountingEntries.exportError'));
    }
  };

  const totals = useMemo(() => {
    const debit = rows.reduce((sum, row) => sum + Number(row.debit ?? 0), 0);
    const credit = rows.reduce((sum, row) => sum + Number(row.credit ?? 0), 0);
    return { debit, credit, balanced: Math.abs(debit - credit) < 0.005 };
  }, [rows]);

  const columns: Column<Row>[] = useMemo(
    () => [
      {
        key: 'entry_date',
        header: t(locale, 'edu.accountingEntries.date'),
        render: (row) => <span className="text-slate-500">{String(row.entry_date ?? '—')}</span>,
      },
      {
        key: 'source_type',
        header: t(locale, 'edu.accountingEntries.source'),
        render: (row) => (
          <span className="font-mono text-xs font-bold text-slate-500">
            {String(row.source_type ?? '—')} #{String(row.source_id ?? '—')}
          </span>
        ),
      },
      {
        key: 'account',
        header: t(locale, 'edu.accountingEntries.account'),
        render: (row) => (
          <span className="text-slate-700">
            <span className="font-mono text-xs font-bold text-slate-500">
              {String(row.account_code ?? '—')}
            </span>{' '}
            {String(row.account_label ?? '')}
          </span>
        ),
      },
      {
        key: 'debit',
        header: t(locale, 'edu.accountingEntries.debit'),
        render: (row) => (
          <span className="tabular-nums text-slate-700">{money(row.debit)}</span>
        ),
      },
      {
        key: 'credit',
        header: t(locale, 'edu.accountingEntries.credit'),
        render: (row) => (
          <span className="tabular-nums text-slate-700">{money(row.credit)}</span>
        ),
      },
      {
        key: 'reference',
        header: t(locale, 'edu.accountingEntries.reference'),
        render: (row) => (
          <span className="font-mono text-xs text-slate-500">{String(row.reference ?? '—')}</span>
        ),
      },
    ],
    [locale],
  );

  return (
    <ModulePageShell
      title={t(locale, 'edu.accountingEntries.title')}
      subtitle={t(locale, 'edu.accountingEntries.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="space-y-6">
        <Card>
          <SectionTitle
            title={t(locale, 'edu.accountingEntries.balance')}
            subtitle={t(locale, 'edu.accountingEntries.balanceSubtitle')}
          />
          <div className="grid gap-4 sm:grid-cols-3">
            <div>
              <p className="text-xs font-black uppercase tracking-widest text-slate-400">
                {t(locale, 'edu.accountingEntries.debit')}
              </p>
              <p className="tabular-nums text-2xl font-black text-slate-950">{money(totals.debit)}</p>
            </div>
            <div>
              <p className="text-xs font-black uppercase tracking-widest text-slate-400">
                {t(locale, 'edu.accountingEntries.credit')}
              </p>
              <p className="tabular-nums text-2xl font-black text-slate-950">{money(totals.credit)}</p>
            </div>
            <div>
              <p className="text-xs font-black uppercase tracking-widest text-slate-400">
                {t(locale, 'edu.accountingEntries.state')}
              </p>
              <p
                className={`inline-flex items-center gap-2 text-sm font-black ${
                  totals.balanced ? 'text-emerald-700' : 'text-rose-600'
                }`}
              >
                <Scale className="h-4 w-4" aria-hidden="true" />
                {totals.balanced
                  ? t(locale, 'edu.accountingEntries.balanced')
                  : t(locale, 'edu.accountingEntries.unbalanced')}
              </p>
            </div>
          </div>
        </Card>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <SelectInput
            value={sourceFilter}
            onChange={(event) => setSourceFilter(event.target.value)}
            aria-label={t(locale, 'edu.accountingEntries.source')}
          >
            <option value="">{t(locale, 'edu.accountingEntries.allSources')}</option>
            {SOURCE_TYPES.map((source) => (
              <option key={source} value={source}>
                {source}
              </option>
            ))}
          </SelectInput>
          <Button variant="ghost" onClick={() => void exportCsv()}>
            <Download className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'edu.accountingEntries.exportCsv')}
          </Button>
        </div>

        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => String(row.id)}
          emptyLabel={t(locale, 'edu.accountingEntries.empty')}
          loading={loading}
          error={error}
          onRetry={() => void load()}
        />
      </div>
    </ModulePageShell>
  );
}
