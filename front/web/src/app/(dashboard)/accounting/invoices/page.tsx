'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { FileText, Plus, RefreshCw, Send, X, Ban, Download } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * #9380 — Écrans de facturation côté portail tenant.
 *
 * Le défaut audité : le portail tenant Next.js n'avait **aucun écran de
 * facturation**. La liste et la création de factures n'existaient que dans le
 * cockpit super-admin Vue et dans l'app mobile — un client du tenant ne pouvait
 * donc ni consulter ses factures, ni en émettre, depuis l'application qu'il
 * utilise réellement.
 *
 * Cette page couvre le parcours complet sur les endpoints déjà livrés par le
 * module Comptabilité :
 *   - GET  /accounting/documents            (liste, filtres type/statut)
 *   - POST /accounting/documents            (création d'un brouillon)
 *   - POST /accounting/documents/{id}/send  (émission)
 *   - POST /accounting/documents/{id}/cancel (annulation — refusée si payée)
 *   - GET  /accounting/documents/{id}       (détail + PDF)
 *
 * Aucune donnée n'est simulée : chaque action appelle l'API et affiche l'erreur
 * réelle renvoyée par le backend (dont le 403 « document payé » de #9379).
 */

interface DocumentLine {
  id?: number;
  description: string;
  quantity: number;
  unit_price: number;
  discount?: number;
}

interface AccountingDocument {
  id: number;
  type: string;
  number: string;
  status: string;
  issue_date: string;
  due_date?: string | null;
  currency?: string | null;
  subtotal_ht: number;
  tax_amount: number;
  total_ttc: number;
  paid_amount?: number;
  contact?: { id: number; name: string } | null;
  lines?: DocumentLine[];
}

interface Contact {
  id: number;
  name: string;
}

const fmt = (n: number) =>
  new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

const today = () => new Date().toISOString().slice(0, 10);

const STATUS_STYLES: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-700',
  sent: 'bg-sky-100 text-sky-700',
  partially_paid: 'bg-amber-100 text-amber-700',
  paid: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-red-100 text-red-700',
  overdue: 'bg-rose-100 text-rose-700',
};

export default function InvoicesPage() {
  const locale = getPreferredLocale();

  const [documents, setDocuments] = useState<AccountingDocument[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const [showForm, setShowForm] = useState(false);
  const [contactId, setContactId] = useState('');
  const [issueDate, setIssueDate] = useState(today());
  const [tvaRate, setTvaRate] = useState('19');
  const [lines, setLines] = useState<DocumentLine[]>([{ description: '', quantity: 1, unit_price: 0 }]);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const query = new URLSearchParams({ type: 'invoice', per_page: '50' });
      if (statusFilter) query.set('status', statusFilter);

      const res = await apiFetch(`/accounting/documents?${query.toString()}`);
      const body = await res.json();
      setDocuments((body.data || []) as AccountingDocument[]);
    } catch {
      setError(t(locale, 'accountingModule.invoicesError'));
    } finally {
      setLoading(false);
    }
  }, [locale, statusFilter]);

  const loadContacts = useCallback(async () => {
    try {
      const res = await apiFetch('/accounting/contacts?type=customer&per_page=100');
      const body = await res.json();
      setContacts((body.data || []) as Contact[]);
    } catch {
      // Le sélecteur reste vide : la création exige un client, l'utilisateur
      // verra le message de validation du backend.
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void loadContacts();
  }, [loadContacts]);

  const totals = useMemo(() => {
    const ht = documents.reduce((sum, d) => sum + Number(d.subtotal_ht || 0), 0);
    const ttc = documents.reduce((sum, d) => sum + Number(d.total_ttc || 0), 0);
    const paid = documents.reduce((sum, d) => sum + Number(d.paid_amount || 0), 0);
    return { ht, ttc, paid, outstanding: ttc - paid };
  }, [documents]);

  const resetForm = () => {
    setContactId('');
    setIssueDate(today());
    setTvaRate('19');
    setLines([{ description: '', quantity: 1, unit_price: 0 }]);
    setFormError(null);
  };

  const submit = async () => {
    setSaving(true);
    setFormError(null);
    try {
      const payload = {
        type: 'invoice',
        contact_id: contactId ? Number(contactId) : null,
        issue_date: issueDate,
        tva_rate: Number(tvaRate),
        lines: lines
          .filter((line) => line.description.trim() !== '')
          .map((line) => ({
            description: line.description,
            quantity: Number(line.quantity) || 0,
            unit_price: Number(line.unit_price) || 0,
            discount: Number(line.discount) || 0,
          })),
      };

      const res = await apiFetch('/accounting/documents', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setFormError(body.message || t(locale, 'accountingModule.invoicesCreateError'));
        return;
      }

      setShowForm(false);
      resetForm();
      await load();
    } catch {
      setFormError(t(locale, 'accountingModule.invoicesCreateError'));
    } finally {
      setSaving(false);
    }
  };

  const act = async (document: AccountingDocument, action: 'send' | 'cancel') => {
    setBusyId(document.id);
    setError(null);
    try {
      const res = await apiFetch(`/accounting/documents/${document.id}/${action}`, { method: 'POST' });

      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        // Le backend renvoie 403 « document payé » (#9379) : on affiche son
        // message plutôt qu'un texte générique.
        setError(body.message || t(locale, 'accountingModule.invoicesActionError'));
        return;
      }

      await load();
    } catch {
      setError(t(locale, 'accountingModule.invoicesActionError'));
    } finally {
      setBusyId(null);
    }
  };

  const downloadPdf = async (document: AccountingDocument) => {
    try {
      const res = await apiFetch(`/accounting/documents/${document.id}/pdf`);
      if (!res.ok) {
        setError(t(locale, 'accountingModule.invoicesPdfError'));
        return;
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = window.document.createElement('a');
      a.href = url;
      a.download = `${document.number}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError(t(locale, 'accountingModule.invoicesPdfError'));
    }
  };

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.invoicesTitle')}
      subtitle={t(locale, 'accountingModule.invoicesSubtitle')}
      accentClassName="bg-gradient-to-br from-sky-100 via-white to-white"
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label className="flex items-center gap-2 text-sm font-bold text-slate-600">
          {t(locale, 'accountingModule.invoicesStatusFilter')}
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
          >
            <option value="">{t(locale, 'accountingModule.invoicesAllStatuses')}</option>
            <option value="draft">{t(locale, 'accountingModule.invoicesStatusDraft')}</option>
            <option value="sent">{t(locale, 'accountingModule.invoicesStatusSent')}</option>
            <option value="paid">{t(locale, 'accountingModule.invoicesStatusPaid')}</option>
            <option value="overdue">{t(locale, 'accountingModule.invoicesStatusOverdue')}</option>
            <option value="cancelled">{t(locale, 'accountingModule.invoicesStatusCancelled')}</option>
          </select>
        </label>

        <button
          onClick={() => {
            resetForm();
            setShowForm((v) => !v);
          }}
          className="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-sky-700"
        >
          <Plus className="h-4 w-4" />
          {t(locale, 'accountingModule.invoicesNew')}
        </button>

        <button
          onClick={() => void load()}
          className="inline-flex items-center gap-2 rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
        >
          <RefreshCw className="h-4 w-4" />
          {t(locale, 'accountingModule.retry')}
        </button>
      </div>

      {error && (
        <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm font-bold text-red-700">
          {error}
        </div>
      )}

      {/* Indicateurs de la sélection courante */}
      <div className="grid gap-3 sm:grid-cols-3">
        <div className="rounded-2xl border border-app-border bg-white px-5 py-4">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesTotalHt')}</p>
          <p className="mt-1 font-mono text-lg font-bold text-slate-800">{fmt(totals.ht)}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white px-5 py-4">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesTotalTtc')}</p>
          <p className="mt-1 font-mono text-lg font-bold text-slate-800">{fmt(totals.ttc)}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white px-5 py-4">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesOutstanding')}</p>
          <p className="mt-1 font-mono text-lg font-bold text-amber-700">{fmt(totals.outstanding)}</p>
        </div>
      </div>

      {showForm && (
        <section className="rounded-3xl border border-app-border bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-bold text-slate-800">{t(locale, 'accountingModule.invoicesNew')}</h2>
            <button
              onClick={() => setShowForm(false)}
              aria-label={t(locale, 'accountingModule.invoicesClose')}
              className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="mt-4 grid gap-3 sm:grid-cols-3">
            <label className="flex flex-col gap-1 text-xs font-bold text-slate-600">
              {t(locale, 'accountingModule.invoicesCustomer')}
              <select
                value={contactId}
                onChange={(e) => setContactId(e.target.value)}
                className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-normal"
              >
                <option value="">{t(locale, 'accountingModule.invoicesSelectCustomer')}</option>
                {contacts.map((contact) => (
                  <option key={contact.id} value={contact.id}>
                    {contact.name}
                  </option>
                ))}
              </select>
            </label>

            <label className="flex flex-col gap-1 text-xs font-bold text-slate-600">
              {t(locale, 'accountingModule.invoicesIssueDate')}
              <input
                type="date"
                value={issueDate}
                onChange={(e) => setIssueDate(e.target.value)}
                className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-normal"
              />
            </label>

            <label className="flex flex-col gap-1 text-xs font-bold text-slate-600">
              {t(locale, 'accountingModule.invoicesVatRate')}
              <input
                type="number"
                min={0}
                max={100}
                step="0.01"
                value={tvaRate}
                onChange={(e) => setTvaRate(e.target.value)}
                className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-normal"
              />
            </label>
          </div>

          <div className="mt-4 space-y-2">
            {lines.map((line, index) => (
              <div key={index} className="grid gap-2 sm:grid-cols-[1fr_100px_130px_40px]">
                <input
                  placeholder={t(locale, 'accountingModule.invoicesLineDescription')}
                  value={line.description}
                  onChange={(e) => {
                    const next = [...lines];
                    next[index] = { ...next[index], description: e.target.value };
                    setLines(next);
                  }}
                  className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
                />
                <input
                  type="number"
                  min={0}
                  step="0.01"
                  aria-label={t(locale, 'accountingModule.invoicesLineQuantity')}
                  value={line.quantity}
                  onChange={(e) => {
                    const next = [...lines];
                    next[index] = { ...next[index], quantity: Number(e.target.value) };
                    setLines(next);
                  }}
                  className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
                />
                <input
                  type="number"
                  min={0}
                  step="0.01"
                  aria-label={t(locale, 'accountingModule.invoicesLineUnitPrice')}
                  value={line.unit_price}
                  onChange={(e) => {
                    const next = [...lines];
                    next[index] = { ...next[index], unit_price: Number(e.target.value) };
                    setLines(next);
                  }}
                  className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
                />
                <button
                  onClick={() => setLines(lines.filter((_, i) => i !== index))}
                  disabled={lines.length === 1}
                  aria-label={t(locale, 'accountingModule.invoicesRemoveLine')}
                  className="rounded-xl border border-app-border text-slate-500 transition hover:bg-slate-50 disabled:opacity-40"
                >
                  <X className="mx-auto h-4 w-4" />
                </button>
              </div>
            ))}
          </div>

          <div className="mt-3 flex flex-wrap items-center gap-3">
            <button
              onClick={() => setLines([...lines, { description: '', quantity: 1, unit_price: 0 }])}
              className="inline-flex items-center gap-1.5 rounded-xl border border-app-border px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
            >
              <Plus className="h-3.5 w-3.5" />
              {t(locale, 'accountingModule.invoicesAddLine')}
            </button>

            <button
              onClick={() => void submit()}
              disabled={saving}
              className="ml-auto inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-sky-700 disabled:opacity-60"
            >
              {saving ? t(locale, 'accountingModule.invoicesSaving') : t(locale, 'accountingModule.invoicesSave')}
            </button>
          </div>

          {formError && (
            <p role="alert" className="mt-3 text-xs font-bold text-red-600">
              {formError}
            </p>
          )}
        </section>
      )}

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border">
                <th scope="col" className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesNumber')}</th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesCustomer')}</th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesIssueDate')}</th>
                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesTotalTtc')}</th>
                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesStatus')}</th>
                <th scope="col" className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.invoicesActions')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {loading ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-500">{t(locale, 'accountingModule.loading')}</td></tr>
              ) : documents.length === 0 ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-500">{t(locale, 'accountingModule.invoicesEmpty')}</td></tr>
              ) : (
                documents.map((document) => (
                  <tr key={document.id} className="transition-colors hover:bg-slate-50/60">
                    <td className="px-6 py-3 font-mono text-xs font-bold text-slate-800">{document.number}</td>
                    <td className="px-4 py-3 text-slate-600">{document.contact?.name ?? '—'}</td>
                    <td className="px-4 py-3 text-slate-600">{document.issue_date}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs font-bold text-slate-800">
                      {fmt(document.total_ttc)} {document.currency ?? ''}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ${STATUS_STYLES[document.status] ?? 'bg-slate-100 text-slate-700'}`}>
                        {t(locale, `accountingModule.invoicesStatus_${document.status}`)}
                      </span>
                    </td>
                    <td className="px-6 py-3">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => void downloadPdf(document)}
                          aria-label={t(locale, 'accountingModule.invoicesDownloadPdf')}
                          className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100"
                        >
                          <Download className="h-4 w-4" />
                        </button>
                        {document.status === 'draft' && (
                          <button
                            onClick={() => void act(document, 'send')}
                            disabled={busyId === document.id}
                            aria-label={t(locale, 'accountingModule.invoicesSend')}
                            className="rounded-lg p-1.5 text-sky-600 transition hover:bg-sky-50 disabled:opacity-40"
                          >
                            <Send className="h-4 w-4" />
                          </button>
                        )}
                        {document.status !== 'cancelled' && document.status !== 'paid' && (
                          <button
                            onClick={() => void act(document, 'cancel')}
                            disabled={busyId === document.id}
                            aria-label={t(locale, 'accountingModule.invoicesCancel')}
                            className="rounded-lg p-1.5 text-red-600 transition hover:bg-red-50 disabled:opacity-40"
                          >
                            <Ban className="h-4 w-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      <p className="flex items-center gap-2 text-xs text-slate-500">
        <FileText className="h-3.5 w-3.5" />
        {t(locale, 'accountingModule.invoicesFooterNote')}
      </p>
    </ModulePageShell>
  );
}
