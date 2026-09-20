'use client';

/**
 * HealthManager — actes & facturation des soins (HC-007, #7791 / web #7792).
 * Cartes de statistiques (`GET /invoices/stats`), catalogue d'actes CRUD
 * (HealthCrudTable), liste des factures avec actions émettre / encaisser /
 * annuler, et création de facture brouillon avec lignes d'actes (prix figés
 * serveur, total recalculé serveur).
 */
import { useCallback, useEffect, useState } from 'react';
import { Receipt } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { HealthCrudTable, type HealthCrudConfig } from '@/components/health/HealthCrudTable';
import {
  cancelInvoice,
  createInvoice,
  getInvoiceStats,
  issueInvoice,
  listCareActs,
  listInvoices,
  listPatients,
  payInvoice,
  type CareAct,
  type Invoice,
  type InvoiceStats,
  type Patient,
  type PaymentMethod,
} from '@/lib/health-api';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type DraftLine = { care_act_id: string; label: string; unit_price: string; quantity: string };

const EMPTY_LINE: DraftLine = { care_act_id: '', label: '', unit_price: '0', quantity: '1' };

const PAYMENT_METHODS: PaymentMethod[] = ['cash', 'card', 'transfer', 'mobile', 'insurance', 'other'];

function formatAmount(value: number | string | undefined, locale: string, currency?: string): string {
  if (value === undefined || value === null || value === '') return '—';
  const numeric = typeof value === 'string' ? Number(value) : value;
  if (Number.isNaN(numeric)) return String(value);
  return `${numeric.toLocaleString(locale)}${currency ? ` ${currency}` : ''}`;
}

export default function HealthBillingPage() {
  const locale = getPreferredLocale();
  const [stats, setStats] = useState<InvoiceStats | null>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [acting, setActing] = useState<number | null>(null);

  const [showDraft, setShowDraft] = useState(false);
  const [patients, setPatients] = useState<Patient[]>([]);
  const [careActs, setCareActs] = useState<CareAct[]>([]);
  const [draftPatientId, setDraftPatientId] = useState('');
  const [draftDiscount, setDraftDiscount] = useState('0');
  const [draftLines, setDraftLines] = useState<DraftLine[]>([{ ...EMPTY_LINE }]);
  const [saving, setSaving] = useState(false);
  const [draftError, setDraftError] = useState('');

  const [payTarget, setPayTarget] = useState<Invoice | null>(null);
  const [payAmount, setPayAmount] = useState('');
  const [payMethod, setPayMethod] = useState<PaymentMethod>('cash');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [statsPayload, invoicesPayload] = await Promise.all([getInvoiceStats(), listInvoices()]);
      setStats(statsPayload);
      setInvoices(invoicesPayload.data);
    } catch {
      setError(t(locale, 'health.common.loadError', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const openDraft = async () => {
    setDraftPatientId('');
    setDraftDiscount('0');
    setDraftLines([{ ...EMPTY_LINE }]);
    setDraftError('');
    setShowDraft(true);
    try {
      const [patientsPayload, actsList] = await Promise.all([listPatients({ per_page: 200 }), listCareActs()]);
      setPatients(patientsPayload.data);
      setCareActs(actsList.filter((act) => act.active));
    } catch {
      // le formulaire reste utilisable
    }
  };

  const updateLine = (index: number, patch: Partial<DraftLine>) =>
    setDraftLines((prev) => prev.map((line, i) => (i === index ? { ...line, ...patch } : line)));

  const pickAct = (index: number, actId: string) => {
    const act = careActs.find((candidate) => String(candidate.id) === actId);
    updateLine(index, {
      care_act_id: actId,
      label: act ? act.label : '',
      unit_price: act ? String(act.price) : '0',
    });
  };

  const submitDraft = async () => {
    setSaving(true);
    setDraftError('');
    try {
      await createInvoice({
        patient_id: Number(draftPatientId),
        discount: Number(draftDiscount) || 0,
        items: draftLines
          .filter((line) => line.label.trim() !== '')
          .map((line) => ({
            care_act_id: line.care_act_id ? Number(line.care_act_id) : undefined,
            label: line.label,
            unit_price: Number(line.unit_price) || 0,
            quantity: Number(line.quantity) || 1,
          })),
      });
      setShowDraft(false);
      await load();
    } catch (e) {
      setDraftError(e instanceof Error ? e.message : t(locale, 'health.crud.saveError', 'Erreur lors de la sauvegarde.'));
    } finally {
      setSaving(false);
    }
  };

  const issue = async (invoice: Invoice) => {
    setActing(invoice.id);
    try {
      await issueInvoice(invoice.id);
      await load();
    } catch (e) {
      window.alert(e instanceof Error ? e.message : t(locale, 'health.billing.issueError', 'Émission impossible.'));
    } finally {
      setActing(null);
    }
  };

  const cancel = async (invoice: Invoice) => {
    if (!window.confirm(t(locale, 'health.billing.cancelConfirm', 'Annuler cette facture ?'))) return;
    setActing(invoice.id);
    try {
      await cancelInvoice(invoice.id);
      await load();
    } catch (e) {
      window.alert(e instanceof Error ? e.message : t(locale, 'health.billing.cancelError', 'Annulation impossible.'));
    } finally {
      setActing(null);
    }
  };

  const confirmPay = async () => {
    if (!payTarget) return;
    setActing(payTarget.id);
    try {
      await payInvoice(payTarget.id, { amount: Number(payAmount) || 0, method: payMethod });
      setPayTarget(null);
      await load();
    } catch (e) {
      window.alert(e instanceof Error ? e.message : t(locale, 'health.billing.payError', 'Encaissement impossible.'));
    } finally {
      setActing(null);
    }
  };

  const careActsConfig: HealthCrudConfig = {
    endpoint: '/health-manager/care-acts',
    title: t(locale, 'health.billing.careActsTitle', "Catalogue d'actes"),
    searchKeys: ['code', 'label', 'category'],
    columns: [
      { key: 'code', label: t(locale, 'health.billing.actCode', 'Code') },
      { key: 'label', label: t(locale, 'health.billing.actLabel', 'Libellé') },
      {
        key: 'category',
        label: t(locale, 'health.billing.actCategory', 'Catégorie'),
        render: (row) => t(locale, `health.billing.categoryValue.${String(row.category)}`, String(row.category)),
      },
      {
        key: 'price',
        label: t(locale, 'health.billing.actPrice', 'Prix'),
        render: (row) => formatAmount(row.price as number | string, locale, String(row.currency ?? '')),
      },
      { key: 'active', label: t(locale, 'health.billing.actActive', 'Actif') },
    ],
    fields: [
      { name: 'code', label: t(locale, 'health.billing.actCode', 'Code'), type: 'text', required: true },
      { name: 'label', label: t(locale, 'health.billing.actLabel', 'Libellé'), type: 'text', required: true },
      {
        name: 'category',
        label: t(locale, 'health.billing.actCategory', 'Catégorie'),
        type: 'select',
        required: true,
        options: (['consultation', 'exam', 'surgery', 'hospitalization', 'other'] as const).map((category) => ({
          value: category,
          label: t(locale, `health.billing.categoryValue.${category}`, category),
        })),
      },
      { name: 'price', label: t(locale, 'health.billing.actPrice', 'Prix'), type: 'number', required: true, min: 0, step: '0.01' },
      { name: 'currency', label: t(locale, 'health.billing.actCurrency', 'Devise'), type: 'text', required: true },
    ],
  };

  const statCards = [
    { label: t(locale, 'health.billing.statDraft', 'Brouillons'), value: String(stats?.draft_count ?? 0) },
    { label: t(locale, 'health.billing.statIssued', 'Émises'), value: String(stats?.issued_count ?? 0) },
    { label: t(locale, 'health.billing.statPaid', 'Payées'), value: String(stats?.paid_count ?? 0) },
    {
      label: t(locale, 'health.billing.statMonthRevenue', 'Recettes du mois'),
      value: formatAmount(stats?.month_revenue, locale, stats?.currency),
    },
    {
      label: t(locale, 'health.billing.statOutstanding', 'Reste à encaisser'),
      value: formatAmount(stats?.outstanding, locale, stats?.currency),
    },
  ];

  return (
    <ModulePageShell
      icon={Receipt}
      title={t(locale, 'health.billing.title', 'Facturation des soins')}
      description={t(locale, 'health.billing.subtitle', "Catalogue d'actes, factures et encaissements des soins.")}
    >
      <div className="space-y-8">
        <section aria-label={t(locale, 'health.billing.statsTitle', 'Statistiques')}>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {statCards.map((card) => (
              <div key={card.label} className="rounded-2xl border border-slate-200/60 bg-white/80 p-4 shadow-sm backdrop-blur-xl">
                <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">{card.label}</p>
                <p className="mt-2 text-xl font-black tracking-tight text-slate-950">{card.value}</p>
              </div>
            ))}
          </div>
        </section>

        <section aria-label={t(locale, 'health.billing.invoicesTitle', 'Factures')} className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-lg font-black tracking-tight text-slate-950">
              {t(locale, 'health.billing.invoicesTitle', 'Factures')}
            </h2>
            <button
              type="button"
              onClick={() => void openDraft()}
              className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            >
              {t(locale, 'health.billing.createDraft', 'Nouvelle facture (brouillon)')}
            </button>
          </div>

          {error ? <p className="text-sm font-semibold text-red-600">{error}</p> : null}

          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white/80 shadow-sm backdrop-blur-xl">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.billing.invoiceNumber', 'N° facture')}</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.billing.invoicePatient', 'Patient')}</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.billing.invoiceTotal', 'Total')}</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.billing.invoicePaid', 'Encaissé')}</th>
                  <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.billing.invoiceStatus', 'Statut')}</th>
                  <th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {loading ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                      {t(locale, 'health.crud.loading', 'Chargement...')}
                    </td>
                  </tr>
                ) : invoices.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                      {t(locale, 'health.billing.invoicesEmpty', 'Aucune facture.')}
                    </td>
                  </tr>
                ) : (
                  invoices.map((invoice) => (
                    <tr key={invoice.id} className="hover:bg-emerald-50/40">
                      <td className="px-4 py-3 font-mono text-xs font-bold text-slate-600">{invoice.number}</td>
                      <td className="px-4 py-3 font-bold text-slate-900">
                        {invoice.patient?.full_name ?? `#${invoice.patient_id}`}
                      </td>
                      <td className="px-4 py-3 text-slate-700">{formatAmount(invoice.total, locale, invoice.currency)}</td>
                      <td className="px-4 py-3 text-slate-700">{formatAmount(invoice.amount_paid, locale, invoice.currency)}</td>
                      <td className="px-4 py-3 text-slate-600">
                        {t(locale, `health.billing.invoiceStatusValue.${invoice.status}`, invoice.status)}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex flex-wrap justify-end gap-2">
                          {invoice.status === 'draft' ? (
                            <button
                              type="button"
                              disabled={acting === invoice.id}
                              onClick={() => void issue(invoice)}
                              className="rounded-lg border border-emerald-200 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                            >
                              {t(locale, 'health.billing.issue', 'Émettre')}
                            </button>
                          ) : null}
                          {invoice.status === 'issued' || invoice.status === 'partially_paid' ? (
                            <button
                              type="button"
                              disabled={acting === invoice.id}
                              onClick={() => {
                                setPayTarget(invoice);
                                setPayAmount('');
                                setPayMethod('cash');
                              }}
                              className="rounded-lg border border-cyan-200 px-2.5 py-1 text-xs font-bold text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                            >
                              {t(locale, 'health.billing.pay', 'Encaisser')}
                            </button>
                          ) : null}
                          {invoice.status !== 'cancelled' && invoice.status !== 'paid' ? (
                            <button
                              type="button"
                              disabled={acting === invoice.id}
                              onClick={() => void cancel(invoice)}
                              className="rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                            >
                              {t(locale, 'health.billing.cancel', 'Annuler')}
                            </button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>

        <section aria-label={t(locale, 'health.billing.careActsTitle', "Catalogue d'actes")}>
          <HealthCrudTable config={careActsConfig} />
        </section>

        {showDraft ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
            <div className="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
              <h3 className="mb-4 text-lg font-bold text-slate-900">
                {t(locale, 'health.billing.createDraft', 'Nouvelle facture (brouillon)')}
              </h3>
              {draftError ? <p className="mb-3 text-sm text-red-600">{draftError}</p> : null}
              <form
                className="space-y-4"
                onSubmit={(e) => {
                  e.preventDefault();
                  void submitDraft();
                }}
              >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.billing.invoicePatient', 'Patient')} <span className="text-red-500">*</span>
                    </span>
                    <select
                      required
                      value={draftPatientId}
                      onChange={(e) => setDraftPatientId(e.target.value)}
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="">—</option>
                      {patients.map((patient) => (
                        <option key={patient.id} value={patient.id}>
                          {patient.full_name} ({patient.mrn})
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.billing.discount', 'Remise')}
                    </span>
                    <input
                      type="number"
                      min={0}
                      step="0.01"
                      value={draftDiscount}
                      onChange={(e) => setDraftDiscount(e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  </label>
                </div>

                <div className="space-y-3">
                  <p className="text-sm font-semibold text-slate-700">{t(locale, 'health.billing.lines', 'Lignes')}</p>
                  {draftLines.map((line, index) => (
                    <div key={index} className="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_6rem_5rem_auto] sm:items-end">
                      <label className="block text-xs">
                        <span className="mb-1 block font-semibold text-slate-600">
                          {t(locale, 'health.billing.lineAct', 'Acte')}
                        </span>
                        <select
                          value={line.care_act_id}
                          onChange={(e) => pickAct(index, e.target.value)}
                          className="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 focus:border-emerald-500 focus:outline-none"
                        >
                          <option value="">{t(locale, 'health.billing.lineFree', 'Ligne libre')}</option>
                          {careActs.map((act) => (
                            <option key={act.id} value={act.id}>
                              {act.code} — {act.label}
                            </option>
                          ))}
                        </select>
                      </label>
                      <label className="block text-xs">
                        <span className="mb-1 block font-semibold text-slate-600">
                          {t(locale, 'health.billing.lineLabel', 'Libellé')}
                        </span>
                        <input
                          type="text"
                          value={line.label}
                          onChange={(e) => updateLine(index, { label: e.target.value })}
                          className="w-full rounded-lg border border-slate-200 px-2 py-1.5 focus:border-emerald-500 focus:outline-none"
                        />
                      </label>
                      <label className="block text-xs">
                        <span className="mb-1 block font-semibold text-slate-600">
                          {t(locale, 'health.billing.lineUnitPrice', 'Prix unitaire')}
                        </span>
                        <input
                          type="number"
                          min={0}
                          step="0.01"
                          value={line.unit_price}
                          onChange={(e) => updateLine(index, { unit_price: e.target.value })}
                          className="w-full rounded-lg border border-slate-200 px-2 py-1.5 focus:border-emerald-500 focus:outline-none"
                        />
                      </label>
                      <label className="block text-xs">
                        <span className="mb-1 block font-semibold text-slate-600">
                          {t(locale, 'health.billing.lineQuantity', 'Qté')}
                        </span>
                        <input
                          type="number"
                          min={1}
                          value={line.quantity}
                          onChange={(e) => updateLine(index, { quantity: e.target.value })}
                          className="w-full rounded-lg border border-slate-200 px-2 py-1.5 focus:border-emerald-500 focus:outline-none"
                        />
                      </label>
                      <button
                        type="button"
                        onClick={() => setDraftLines((prev) => prev.filter((_, i) => i !== index))}
                        disabled={draftLines.length <= 1}
                        className="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 disabled:opacity-40"
                      >
                        {t(locale, 'health.billing.removeLine', 'Retirer')}
                      </button>
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={() => setDraftLines((prev) => [...prev, { ...EMPTY_LINE }])}
                    className="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                  >
                    {t(locale, 'health.billing.addLine', 'Ajouter une ligne')}
                  </button>
                </div>

                <div className="flex justify-end gap-3 pt-2">
                  <button
                    type="button"
                    onClick={() => setShowDraft(false)}
                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                  >
                    {t(locale, 'health.crud.cancel', 'Annuler')}
                  </button>
                  <button
                    type="submit"
                    disabled={saving}
                    className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                  >
                    {saving
                      ? t(locale, 'health.crud.saving', 'Enregistrement...')
                      : t(locale, 'health.crud.save', 'Enregistrer')}
                  </button>
                </div>
              </form>
            </div>
          </div>
        ) : null}

        {payTarget ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
              <h3 className="mb-4 text-lg font-bold text-slate-900">
                {t(locale, 'health.billing.payTitle', 'Encaisser un paiement')} — {payTarget.number}
              </h3>
              <div className="space-y-4">
                <label className="block text-sm">
                  <span className="mb-1 block font-semibold text-slate-700">
                    {t(locale, 'health.billing.payAmount', 'Montant')} <span className="text-red-500">*</span>
                  </span>
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={payAmount}
                    onChange={(e) => setPayAmount(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block font-semibold text-slate-700">
                    {t(locale, 'health.billing.payMethod', 'Moyen de paiement')}
                  </span>
                  <select
                    value={payMethod}
                    onChange={(e) => setPayMethod(e.target.value as PaymentMethod)}
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  >
                    {PAYMENT_METHODS.map((method) => (
                      <option key={method} value={method}>
                        {t(locale, `health.billing.methodValue.${method}`, method)}
                      </option>
                    ))}
                  </select>
                </label>
              </div>
              <div className="mt-4 flex justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setPayTarget(null)}
                  className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                >
                  {t(locale, 'health.crud.cancel', 'Annuler')}
                </button>
                <button
                  type="button"
                  disabled={!payAmount || acting === payTarget.id}
                  onClick={() => void confirmPay()}
                  className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                >
                  {t(locale, 'health.billing.pay', 'Encaisser')}
                </button>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </ModulePageShell>
  );
}
