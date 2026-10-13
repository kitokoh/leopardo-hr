'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Check,
  Copy,
  ExternalLink,
  Gift,
  Loader2,
  Pause,
  Play,
  Plus,
  Settings2,
  Users,
  Wallet,
  XCircle,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';

/**
 * FUND-090 (#8412) — Espace tenant « Cagnottes solidaires ».
 *
 * Page de gestion du module pour le responsable du tenant (API privée
 * `/fundraising/*`, gate `module.fundraising` + `api.manager:principal,rh`) :
 *  - onglet CAGNOTTES : création (brouillon), publication, pause, clôture,
 *    lien public à partager, suivi des collectes, demande de **reversement**
 *    au bénéficiaire (règle de solde côté API) et suivi des reversements ;
 *  - onglet PARAMÉTRAGE : moyens de paiement offerts, garde-fous montants
 *    par défaut, montants suggérés, options publiques (anonymat, messages,
 *    mur des soutiens) — consommé en temps réel par la page publique
 *    `/cagnottes/{slug}` (FUND-100).
 */

type FundraiserStatus = 'draft' | 'active' | 'paused' | 'completed' | 'closed' | 'cancelled';

type Fundraiser = {
  id: number;
  slug: string;
  title: string;
  description: string | null;
  beneficiary_name: string;
  category: string;
  category_label: string;
  goal_amount: number | null;
  collected_amount: number;
  contributions_count: number;
  available_balance: number;
  currency: string;
  status: FundraiserStatus;
  status_label: string;
  public_url: string;
  ends_at: string | null;
};

type Payout = {
  id: number;
  reference: string;
  amount: number;
  currency: string;
  method: string;
  method_label: string;
  recipient_name: string;
  status: string;
  status_label: string;
  created_at: string | null;
};

type Contribution = {
  id: number;
  reference: string;
  amount: number;
  currency: string;
  payment_method: string;
  payment_method_label: string;
  provider: string;
  status: string;
  status_label: string;
  contributor_name: string | null;
  is_anonymous: boolean;
  message: string | null;
  paid_at: string | null;
  created_at: string | null;
};

type Settings = {
  enabled_methods: string[];
  default_min_amount: number | null;
  default_max_amount: number | null;
  default_suggested_amounts: number[] | null;
  allow_anonymous: boolean;
  allow_messages: boolean;
  show_supporters: boolean;
};

const CATEGORIES = ['medical', 'education', 'emergency', 'community', 'project', 'other'] as const;
const METHODS = ['card', 'mobile_money', 'bank_transfer', 'cash'] as const;
const PAYOUT_METHODS = ['mobile_money', 'bank_transfer'] as const;

function money(amount: number, currency: string, locale: string): string {
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency, maximumFractionDigits: 2 }).format(amount);
  } catch {
    return `${amount.toFixed(2)} ${currency}`;
  }
}

function parseAmounts(raw: string): number[] | null {
  const values = raw
    .split(/[;,]/)
    .map((chunk) => Number(chunk.trim().replace(',', '.')))
    .filter((value) => Number.isFinite(value) && value > 0);
  return values.length > 0 ? values : null;
}

export default function CagnottesPage() {
  const locale = getPreferredLocale();
  const dir = catalogDirection(locale);

  const [tab, setTab] = useState<'cagnottes' | 'settings'>('cagnottes');
  const [loading, setLoading] = useState(true);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const [fundraisers, setFundraisers] = useState<Fundraiser[]>([]);
  const [showCreate, setShowCreate] = useState(false);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({
    title: '',
    beneficiary_name: '',
    beneficiary_contact: '',
    description: '',
    category: 'other',
    goal_amount: '',
    currency: 'XOF',
    suggested_amounts: '',
    min_amount: '',
    max_amount: '',
    ends_at: '',
  });

  const [actionBusy, setActionBusy] = useState<string | null>(null);
  const [copiedSlug, setCopiedSlug] = useState<string | null>(null);
  const [payoutFor, setPayoutFor] = useState<number | null>(null);
  const [payoutForm, setPayoutForm] = useState({ amount: '', method: 'mobile_money', recipient_name: '', recipient_account: '' });
  const [payouts, setPayouts] = useState<Record<number, Payout[]>>({});
  const [expandedPayouts, setExpandedPayouts] = useState<number | null>(null);
  const [contributions, setContributions] = useState<Record<number, Contribution[]>>({});
  const [expandedContributions, setExpandedContributions] = useState<number | null>(null);

  const [settings, setSettings] = useState<Settings | null>(null);
  const [settingsForm, setSettingsForm] = useState({
    enabled_methods: ['card', 'mobile_money'] as string[],
    default_min_amount: '',
    default_max_amount: '',
    default_suggested_amounts: '',
    allow_anonymous: true,
    allow_messages: true,
    show_supporters: true,
  });
  const [savingSettings, setSavingSettings] = useState(false);

  const genericError = useMemo(() => t(locale, 'cagnottesModule.errorGeneric'), [locale]);

  const loadFundraisers = useCallback(async () => {
    const response = await apiFetch('/fundraising/fundraisers');
    const payload = (await response.json()) as { data: Fundraiser[] };
    setFundraisers(payload.data ?? []);
  }, []);

  const loadSettings = useCallback(async () => {
    const response = await apiFetch('/fundraising/settings');
    const payload = (await response.json()) as { data: Settings };
    const data = payload.data;
    setSettings(data);
    setSettingsForm({
      enabled_methods: data.enabled_methods ?? ['card', 'mobile_money'],
      default_min_amount: data.default_min_amount !== null ? String(data.default_min_amount) : '',
      default_max_amount: data.default_max_amount !== null ? String(data.default_max_amount) : '',
      default_suggested_amounts: (data.default_suggested_amounts ?? []).join(', '),
      allow_anonymous: data.allow_anonymous,
      allow_messages: data.allow_messages,
      show_supporters: data.show_supporters,
    });
  }, []);

  useEffect(() => {
    void (async () => {
      setLoading(true);
      try {
        await Promise.all([loadFundraisers(), loadSettings()]);
      } catch (exception) {
        const status = (exception as { status?: number }).status;
        if (status === 403 || status === 404) {
          setForbidden(true);
        } else {
          setError(genericError);
        }
      } finally {
        setLoading(false);
      }
    })();
  }, [genericError, loadFundraisers, loadSettings]);

  const loadPayouts = useCallback(async (fundraiserId: number) => {
    const response = await apiFetch(`/fundraising/fundraisers/${fundraiserId}/payouts`);
    const payload = (await response.json()) as { data: Payout[] };
    setPayouts((current) => ({ ...current, [fundraiserId]: payload.data ?? [] }));
  }, []);

  // #8428 — Contributions de la cagnotte : le responsable doit voir qui a
  // donné et confirmer les dons manuels (espèces / virement), sinon ils
  // restent `pending` à vie et ne sont jamais comptabilisés.
  const loadContributions = useCallback(async (fundraiserId: number) => {
    const response = await apiFetch(`/fundraising/fundraisers/${fundraiserId}/contributions`);
    const payload = (await response.json()) as { data: Contribution[] };
    setContributions((current) => ({ ...current, [fundraiserId]: payload.data ?? [] }));
  }, []);

  const confirmContribution = useCallback(
    async (contribution: Contribution, fundraiserId: number) => {
      setActionBusy(`contribution:${contribution.id}`);
      setError(null);
      try {
        await apiFetch(`/fundraising/contributions/${contribution.id}/confirm`, { method: 'POST' });
        setNotice(t(locale, 'cagnottesModule.contributionList.confirmed'));
        await Promise.all([loadFundraisers(), loadContributions(fundraiserId)]);
      } catch (exception) {
        setError((exception as { message?: string }).message ?? genericError);
      } finally {
        setActionBusy(null);
      }
    },
    [genericError, loadContributions, loadFundraisers, locale],
  );

  const createFundraiser = useCallback(async () => {
    setCreating(true);
    setError(null);
    try {
      await apiFetch('/fundraising/fundraisers', {
        method: 'POST',
        body: JSON.stringify({
          title: form.title.trim(),
          beneficiary_name: form.beneficiary_name.trim(),
          beneficiary_contact: form.beneficiary_contact.trim() !== '' ? form.beneficiary_contact.trim() : null,
          description: form.description.trim() !== '' ? form.description.trim() : null,
          category: form.category,
          goal_amount: form.goal_amount.trim() !== '' ? Number(form.goal_amount.replace(',', '.')) : null,
          currency: form.currency.trim() !== '' ? form.currency.trim().toUpperCase() : null,
          suggested_amounts: parseAmounts(form.suggested_amounts),
          min_amount: form.min_amount.trim() !== '' ? Number(form.min_amount.replace(',', '.')) : null,
          max_amount: form.max_amount.trim() !== '' ? Number(form.max_amount.replace(',', '.')) : null,
          ends_at: form.ends_at !== '' ? form.ends_at : null,
        }),
      });
      setShowCreate(false);
      setForm({ title: '', beneficiary_name: '', beneficiary_contact: '', description: '', category: 'other', goal_amount: '', currency: 'XOF', suggested_amounts: '', min_amount: '', max_amount: '', ends_at: '' });
      setNotice(t(locale, 'cagnottesModule.created'));
      await loadFundraisers();
    } catch (exception) {
      setError((exception as { message?: string }).message ?? genericError);
    } finally {
      setCreating(false);
    }
  }, [form, genericError, loadFundraisers, locale]);

  const transition = useCallback(
    async (fundraiser: Fundraiser, action: 'publish' | 'pause' | 'close') => {
      setActionBusy(`${fundraiser.id}:${action}`);
      setError(null);
      try {
        await apiFetch(`/fundraising/fundraisers/${fundraiser.id}/${action}`, { method: 'POST' });
        await loadFundraisers();
      } catch (exception) {
        setError((exception as { message?: string }).message ?? genericError);
      } finally {
        setActionBusy(null);
      }
    },
    [genericError, loadFundraisers],
  );

  const requestPayout = useCallback(
    async (fundraiser: Fundraiser) => {
      setActionBusy(`${fundraiser.id}:payout`);
      setError(null);
      try {
        await apiFetch(`/fundraising/fundraisers/${fundraiser.id}/payouts`, {
          method: 'POST',
          body: JSON.stringify({
            amount: Number(payoutForm.amount.replace(',', '.')),
            method: payoutForm.method,
            recipient_name: payoutForm.recipient_name.trim(),
            recipient_account: payoutForm.recipient_account.trim(),
          }),
        });
        setPayoutFor(null);
        setPayoutForm({ amount: '', method: 'mobile_money', recipient_name: '', recipient_account: '' });
        setNotice(t(locale, 'cagnottesModule.payoutRequested'));
        await Promise.all([loadFundraisers(), loadPayouts(fundraiser.id)]);
        setExpandedPayouts(fundraiser.id);
      } catch (exception) {
        setError((exception as { message?: string }).message ?? genericError);
      } finally {
        setActionBusy(null);
      }
    },
    [genericError, loadFundraisers, loadPayouts, locale, payoutForm],
  );

  const markPaid = useCallback(
    async (payout: Payout, fundraiserId: number) => {
      setActionBusy(`payout:${payout.id}`);
      setError(null);
      try {
        await apiFetch(`/fundraising/payouts/${payout.id}/mark-paid`, { method: 'POST' });
        await Promise.all([loadFundraisers(), loadPayouts(fundraiserId)]);
      } catch (exception) {
        setError((exception as { message?: string }).message ?? genericError);
      } finally {
        setActionBusy(null);
      }
    },
    [genericError, loadFundraisers, loadPayouts],
  );

  const saveSettings = useCallback(async () => {
    setSavingSettings(true);
    setError(null);
    try {
      await apiFetch('/fundraising/settings', {
        method: 'PUT',
        body: JSON.stringify({
          enabled_methods: settingsForm.enabled_methods,
          default_min_amount: settingsForm.default_min_amount.trim() !== '' ? Number(settingsForm.default_min_amount.replace(',', '.')) : null,
          default_max_amount: settingsForm.default_max_amount.trim() !== '' ? Number(settingsForm.default_max_amount.replace(',', '.')) : null,
          default_suggested_amounts: parseAmounts(settingsForm.default_suggested_amounts),
          allow_anonymous: settingsForm.allow_anonymous,
          allow_messages: settingsForm.allow_messages,
          show_supporters: settingsForm.show_supporters,
        }),
      });
      setNotice(t(locale, 'cagnottesModule.settingsSaved'));
      await loadSettings();
    } catch (exception) {
      setError((exception as { message?: string }).message ?? genericError);
    } finally {
      setSavingSettings(false);
    }
  }, [genericError, loadSettings, locale, settingsForm]);

  const copyPublicLink = useCallback((fundraiser: Fundraiser) => {
    if (typeof window === 'undefined') return;
    const url = `${window.location.origin}${fundraiser.public_url}`;
    void navigator.clipboard?.writeText(url).then(() => {
      setCopiedSlug(fundraiser.slug);
      setTimeout(() => setCopiedSlug(null), 2000);
    });
  }, []);

  if (loading) {
    return (
      <main dir={dir} className="flex min-h-[60vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-teal-600" />
      </main>
    );
  }

  if (forbidden) {
    return (
      <main dir={dir} className="mx-auto max-w-2xl px-4 py-16 text-center">
        <Gift className="mx-auto h-10 w-10 text-slate-300" />
        <h1 className="mt-3 text-xl font-black text-slate-950">{t(locale, 'cagnottesModule.inactive.title')}</h1>
        <p className="mt-2 text-sm text-slate-500">{t(locale, 'cagnottesModule.inactive.body')}</p>
      </main>
    );
  }

  const inputClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 outline-none focus:border-teal-400';
  const labelClass = 'text-xs font-bold uppercase tracking-wide text-slate-500';

  return (
    <main dir={dir} className="mx-auto max-w-5xl px-4 py-8">
      <div className="mb-6 flex flex-wrap items-center gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-600 text-white shadow-lg shadow-teal-600/20">
          <Gift className="h-5 w-5" />
        </div>
        <div className="me-auto">
          <h1 className="text-xl font-black tracking-tight text-slate-950">{t(locale, 'cagnottesModule.title')}</h1>
          <p className="text-sm text-slate-500">{t(locale, 'cagnottesModule.subtitle')}</p>
        </div>
        <button
          type="button"
          onClick={() => setTab('cagnottes')}
          className={`rounded-xl px-4 py-2 text-sm font-bold ${tab === 'cagnottes' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-700'}`}
        >
          {t(locale, 'cagnottesModule.tabs.cagnottes')}
        </button>
        <button
          type="button"
          onClick={() => setTab('settings')}
          className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold ${tab === 'settings' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-700'}`}
        >
          <Settings2 className="h-4 w-4" />
          {t(locale, 'cagnottesModule.tabs.settings')}
        </button>
      </div>

      {notice && <p className="mb-4 rounded-xl bg-teal-50 px-4 py-2 text-sm font-bold text-teal-800">{notice}</p>}
      {error && <p className="mb-4 rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-700">{error}</p>}

      {tab === 'cagnottes' && (
        <>
          <div className="mb-4 flex justify-end">
            <button
              type="button"
              onClick={() => setShowCreate((current) => !current)}
              className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-black text-white shadow-lg shadow-teal-600/20 hover:bg-teal-700"
            >
              <Plus className="h-4 w-4" />
              {t(locale, 'cagnottesModule.newCagnotte')}
            </button>
          </div>

          {showCreate && (
            <div className="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <h2 className="text-base font-black text-slate-950">{t(locale, 'cagnottesModule.create.title')}</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.titleLabel')} *</label>
                  <input className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder={t(locale, 'cagnottesModule.create.titlePlaceholder')} />
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.beneficiary')} *</label>
                  <input className={inputClass} value={form.beneficiary_name} onChange={(e) => setForm({ ...form, beneficiary_name: e.target.value })} placeholder={t(locale, 'cagnottesModule.create.beneficiaryPlaceholder')} />
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.contact')}</label>
                  <input className={inputClass} value={form.beneficiary_contact} onChange={(e) => setForm({ ...form, beneficiary_contact: e.target.value })} placeholder={t(locale, 'cagnottesModule.create.contactPlaceholder')} />
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.category')}</label>
                  <select className={inputClass} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
                    {CATEGORIES.map((category) => (
                      <option key={category} value={category}>{t(locale, `cagnottesModule.categories.${category}`, category)}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.goal')}</label>
                  <input className={inputClass} inputMode="decimal" value={form.goal_amount} onChange={(e) => setForm({ ...form, goal_amount: e.target.value })} placeholder="500000" />
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.currency')}</label>
                  <input className={inputClass} value={form.currency} onChange={(e) => setForm({ ...form, currency: e.target.value })} maxLength={3} placeholder="XOF" />
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.endsAt')}</label>
                  <input className={inputClass} type="date" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} />
                </div>
                <div className="sm:col-span-2">
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.suggested')}</label>
                  <input className={inputClass} inputMode="decimal" value={form.suggested_amounts} onChange={(e) => setForm({ ...form, suggested_amounts: e.target.value })} placeholder="1000, 5000, 10000" />
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.min')}</label>
                  <input className={inputClass} inputMode="decimal" value={form.min_amount} onChange={(e) => setForm({ ...form, min_amount: e.target.value })} placeholder="100" />
                </div>
                <div>
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.max')}</label>
                  <input className={inputClass} inputMode="decimal" value={form.max_amount} onChange={(e) => setForm({ ...form, max_amount: e.target.value })} placeholder="1000000" />
                </div>
                <div className="sm:col-span-2">
                  <label className={labelClass}>{t(locale, 'cagnottesModule.create.description')}</label>
                  <textarea className={inputClass} rows={3} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder={t(locale, 'cagnottesModule.create.descriptionPlaceholder')} />
                </div>
              </div>
              <div className="mt-4 flex gap-2">
                <button
                  type="button"
                  onClick={() => void createFundraiser()}
                  disabled={creating || form.title.trim() === '' || form.beneficiary_name.trim() === ''}
                  className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-black text-white hover:bg-teal-700 disabled:opacity-40"
                >
                  {creating && <Loader2 className="h-4 w-4 animate-spin" />}
                  {t(locale, 'cagnottesModule.create.submit')}
                </button>
                <button type="button" onClick={() => setShowCreate(false)} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">
                  {t(locale, 'cagnottesModule.cancel')}
                </button>
              </div>
            </div>
          )}

          {fundraisers.length === 0 && !showCreate && (
            <div className="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
              <Gift className="mx-auto h-10 w-10 text-slate-300" />
              <p className="mt-3 text-sm font-bold text-slate-500">{t(locale, 'cagnottesModule.empty')}</p>
            </div>
          )}

          <div className="space-y-4">
            {fundraisers.map((fundraiser) => (
              <div key={fundraiser.id} className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex flex-wrap items-start gap-3">
                  <div className="me-auto">
                    <div className="flex items-center gap-2">
                      <h3 className="text-base font-black text-slate-950">{fundraiser.title}</h3>
                      <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-black ${fundraiser.status === 'active' ? 'bg-teal-100 text-teal-800' : fundraiser.status === 'draft' ? 'bg-slate-100 text-slate-600' : fundraiser.status === 'paused' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'}`}>
                        {fundraiser.status_label}
                      </span>
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                      {fundraiser.category_label} · {t(locale, 'cagnottesModule.for')} {fundraiser.beneficiary_name}
                    </p>
                  </div>
                  <div className="text-end">
                    <p className="text-lg font-black text-slate-950">
                      {money(fundraiser.collected_amount, fundraiser.currency, locale)}
                      {fundraiser.goal_amount !== null && <span className="text-xs font-bold text-slate-400"> / {money(fundraiser.goal_amount, fundraiser.currency, locale)}</span>}
                    </p>
                    <p className="text-[11px] font-bold text-slate-400">
                      {fundraiser.contributions_count} {t(locale, 'cagnottesModule.contributions')} · {t(locale, 'cagnottesModule.available')} : {money(fundraiser.available_balance, fundraiser.currency, locale)}
                    </p>
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                  {fundraiser.status === 'draft' && (
                    <button type="button" onClick={() => void transition(fundraiser, 'publish')} disabled={actionBusy !== null} className="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-3 py-2 text-xs font-black text-white hover:bg-teal-700">
                      {actionBusy === `${fundraiser.id}:publish` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Play className="h-3.5 w-3.5" />}
                      {t(locale, 'cagnottesModule.actions.publish')}
                    </button>
                  )}
                  {fundraiser.status === 'active' && (
                    <button type="button" onClick={() => void transition(fundraiser, 'pause')} disabled={actionBusy !== null} className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:border-amber-300">
                      {actionBusy === `${fundraiser.id}:pause` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Pause className="h-3.5 w-3.5" />}
                      {t(locale, 'cagnottesModule.actions.pause')}
                    </button>
                  )}
                  {fundraiser.status === 'paused' && (
                    <button type="button" onClick={() => void transition(fundraiser, 'publish')} disabled={actionBusy !== null} className="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-3 py-2 text-xs font-black text-white hover:bg-teal-700">
                      {actionBusy === `${fundraiser.id}:publish` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Play className="h-3.5 w-3.5" />}
                      {t(locale, 'cagnottesModule.actions.resume')}
                    </button>
                  )}
                  {(fundraiser.status === 'active' || fundraiser.status === 'paused' || fundraiser.status === 'completed') && (
                    <button type="button" onClick={() => void transition(fundraiser, 'close')} disabled={actionBusy !== null} className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:border-red-300">
                      <XCircle className="h-3.5 w-3.5" />
                      {t(locale, 'cagnottesModule.actions.close')}
                    </button>
                  )}
                  {(fundraiser.status === 'active' || fundraiser.status === 'paused' || fundraiser.status === 'completed') && (
                    <>
                      <button type="button" onClick={() => copyPublicLink(fundraiser)} className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:border-teal-300">
                        {copiedSlug === fundraiser.slug ? <Check className="h-3.5 w-3.5 text-teal-600" /> : <Copy className="h-3.5 w-3.5" />}
                        {copiedSlug === fundraiser.slug ? t(locale, 'cagnottesModule.copied') : t(locale, 'cagnottesModule.copyLink')}
                      </button>
                      <a href={fundraiser.public_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:border-teal-300">
                        <ExternalLink className="h-3.5 w-3.5" />
                        {t(locale, 'cagnottesModule.viewPublic')}
                      </a>
                    </>
                  )}
                  {fundraiser.available_balance > 0 && (
                    <button
                      type="button"
                      onClick={() => {
                        setPayoutFor(payoutFor === fundraiser.id ? null : fundraiser.id);
                        setPayoutForm({ ...payoutForm, amount: String(fundraiser.available_balance) });
                      }}
                      className="inline-flex items-center gap-1.5 rounded-xl bg-slate-950 px-3 py-2 text-xs font-black text-white"
                    >
                      <Wallet className="h-3.5 w-3.5" />
                      {t(locale, 'cagnottesModule.actions.payout')}
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => {
                      const next = expandedContributions === fundraiser.id ? null : fundraiser.id;
                      setExpandedContributions(next);
                      if (next !== null && contributions[fundraiser.id] === undefined) void loadContributions(fundraiser.id);
                    }}
                    className="ms-auto inline-flex items-center gap-1 text-xs font-bold text-slate-400 underline"
                  >
                    <Users className="h-3.5 w-3.5" />
                    {expandedContributions === fundraiser.id ? t(locale, 'cagnottesModule.hideContributions') : t(locale, 'cagnottesModule.showContributions')}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const next = expandedPayouts === fundraiser.id ? null : fundraiser.id;
                      setExpandedPayouts(next);
                      if (next !== null && payouts[fundraiser.id] === undefined) void loadPayouts(fundraiser.id);
                    }}
                    className="text-xs font-bold text-slate-400 underline"
                  >
                    {expandedPayouts === fundraiser.id ? t(locale, 'cagnottesModule.hidePayouts') : t(locale, 'cagnottesModule.showPayouts')}
                  </button>
                </div>

                {payoutFor === fundraiser.id && (
                  <div className="mt-4 rounded-2xl bg-slate-50 p-4">
                    <h4 className="text-sm font-black text-slate-950">{t(locale, 'cagnottesModule.payout.title')}</h4>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                      <div>
                        <label className={labelClass}>{t(locale, 'cagnottesModule.payout.amount')} *</label>
                        <input className={inputClass} inputMode="decimal" value={payoutForm.amount} onChange={(e) => setPayoutForm({ ...payoutForm, amount: e.target.value })} />
                      </div>
                      <div>
                        <label className={labelClass}>{t(locale, 'cagnottesModule.payout.method')}</label>
                        <select className={inputClass} value={payoutForm.method} onChange={(e) => setPayoutForm({ ...payoutForm, method: e.target.value })}>
                          {PAYOUT_METHODS.map((method) => (
                            <option key={method} value={method}>{t(locale, `cagnottesModule.payoutMethods.${method}`, method)}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className={labelClass}>{t(locale, 'cagnottesModule.payout.recipient')} *</label>
                        <input className={inputClass} value={payoutForm.recipient_name} onChange={(e) => setPayoutForm({ ...payoutForm, recipient_name: e.target.value })} />
                      </div>
                      <div>
                        <label className={labelClass}>{t(locale, 'cagnottesModule.payout.account')} *</label>
                        <input className={inputClass} value={payoutForm.recipient_account} onChange={(e) => setPayoutForm({ ...payoutForm, recipient_account: e.target.value })} placeholder={t(locale, 'cagnottesModule.payout.accountPlaceholder')} />
                      </div>
                    </div>
                    <div className="mt-3 flex gap-2">
                      <button
                        type="button"
                        onClick={() => void requestPayout(fundraiser)}
                        disabled={actionBusy !== null || payoutForm.amount.trim() === '' || payoutForm.recipient_name.trim() === '' || payoutForm.recipient_account.trim() === ''}
                        className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-xs font-black text-white hover:bg-teal-700 disabled:opacity-40"
                      >
                        {actionBusy === `${fundraiser.id}:payout` && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        {t(locale, 'cagnottesModule.payout.submit')}
                      </button>
                      <button type="button" onClick={() => setPayoutFor(null)} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700">
                        {t(locale, 'cagnottesModule.cancel')}
                      </button>
                    </div>
                  </div>
                )}

                {expandedPayouts === fundraiser.id && (
                  <div className="mt-4">
                    {(payouts[fundraiser.id] ?? []).length === 0 ? (
                      <p className="text-xs text-slate-400">{t(locale, 'cagnottesModule.payout.empty')}</p>
                    ) : (
                      <ul className="space-y-2">
                        {(payouts[fundraiser.id] ?? []).map((payout) => (
                          <li key={payout.id} className="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs">
                            <span className="font-black text-slate-950">{money(payout.amount, payout.currency, locale)}</span>
                            <span className="text-slate-500">{payout.method_label} · {payout.recipient_name}</span>
                            <span className="rounded-full bg-slate-200 px-2 py-0.5 font-black text-slate-600">{payout.status_label}</span>
                            {payout.status === 'processing' && (
                              <button
                                type="button"
                                onClick={() => void markPaid(payout, fundraiser.id)}
                                disabled={actionBusy !== null}
                                className="ms-auto rounded-lg bg-teal-600 px-2.5 py-1 font-black text-white hover:bg-teal-700"
                              >
                                {actionBusy === `payout:${payout.id}` ? <Loader2 className="h-3 w-3 animate-spin" /> : t(locale, 'cagnottesModule.payout.markPaid')}
                              </button>
                            )}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )}

                {expandedContributions === fundraiser.id && (
                  <div className="mt-4">
                    {(contributions[fundraiser.id] ?? []).length === 0 ? (
                      <p className="text-xs text-slate-400">{t(locale, 'cagnottesModule.contributionList.empty')}</p>
                    ) : (
                      <ul className="space-y-2" data-testid={`contributions-${fundraiser.id}`}>
                        {(contributions[fundraiser.id] ?? []).map((contribution) => (
                          <li key={contribution.id} className="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs">
                            <span className="font-black text-slate-950">{money(contribution.amount, contribution.currency, locale)}</span>
                            <span className="text-slate-500">
                              {contribution.is_anonymous
                                ? t(locale, 'cagnottesModule.contributionList.anonymous')
                                : contribution.contributor_name ?? t(locale, 'cagnottesModule.contributionList.anonymous')}
                              {' · '}{contribution.payment_method_label}{' · '}{contribution.reference}
                            </span>
                            <span className={`rounded-full px-2 py-0.5 font-black ${contribution.status === 'completed' ? 'bg-teal-100 text-teal-800' : contribution.status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600'}`}>
                              {contribution.status_label}
                            </span>
                            {contribution.status === 'pending' && contribution.provider === 'manual' && (
                              <button
                                type="button"
                                onClick={() => void confirmContribution(contribution, fundraiser.id)}
                                disabled={actionBusy !== null}
                                className="ms-auto rounded-lg bg-teal-600 px-2.5 py-1 font-black text-white hover:bg-teal-700"
                              >
                                {actionBusy === `contribution:${contribution.id}` ? <Loader2 className="h-3 w-3 animate-spin" /> : t(locale, 'cagnottesModule.contributionList.confirm')}
                              </button>
                            )}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>
        </>
      )}

      {tab === 'settings' && settings !== null && (
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-base font-black text-slate-950">{t(locale, 'cagnottesModule.settings.title')}</h2>
          <p className="mt-1 text-xs text-slate-500">{t(locale, 'cagnottesModule.settings.hint')}</p>

          <div className="mt-5">
            <label className={labelClass}>{t(locale, 'cagnottesModule.settings.methods')}</label>
            <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
              {METHODS.map((method) => {
                const enabled = settingsForm.enabled_methods.includes(method);
                return (
                  <button
                    key={method}
                    type="button"
                    onClick={() =>
                      setSettingsForm({
                        ...settingsForm,
                        enabled_methods: enabled
                          ? settingsForm.enabled_methods.filter((current) => current !== method)
                          : [...settingsForm.enabled_methods, method],
                      })
                    }
                    className={`rounded-xl px-3 py-2.5 text-sm font-bold shadow-sm ${enabled ? 'bg-teal-600 text-white' : 'border border-slate-200 bg-white text-slate-500'}`}
                  >
                    {t(locale, `cagnottesModule.methods.${method}`, method)}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="mt-5 grid gap-3 sm:grid-cols-3">
            <div>
              <label className={labelClass}>{t(locale, 'cagnottesModule.settings.minDefault')}</label>
              <input className={inputClass} inputMode="decimal" value={settingsForm.default_min_amount} onChange={(e) => setSettingsForm({ ...settingsForm, default_min_amount: e.target.value })} placeholder="100" />
            </div>
            <div>
              <label className={labelClass}>{t(locale, 'cagnottesModule.settings.maxDefault')}</label>
              <input className={inputClass} inputMode="decimal" value={settingsForm.default_max_amount} onChange={(e) => setSettingsForm({ ...settingsForm, default_max_amount: e.target.value })} placeholder="10000000" />
            </div>
            <div>
              <label className={labelClass}>{t(locale, 'cagnottesModule.settings.suggestedDefault')}</label>
              <input className={inputClass} inputMode="decimal" value={settingsForm.default_suggested_amounts} onChange={(e) => setSettingsForm({ ...settingsForm, default_suggested_amounts: e.target.value })} placeholder="1000, 5000, 10000" />
            </div>
          </div>

          <div className="mt-5 space-y-2">
            {([
              ['allow_anonymous', 'cagnottesModule.settings.allowAnonymous'],
              ['allow_messages', 'cagnottesModule.settings.allowMessages'],
              ['show_supporters', 'cagnottesModule.settings.showSupporters'],
            ] as const).map(([key, i18nKey]) => (
              <label key={key} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                <input
                  type="checkbox"
                  checked={settingsForm[key]}
                  onChange={(e) => setSettingsForm({ ...settingsForm, [key]: e.target.checked })}
                  className="h-4 w-4 rounded border-slate-300"
                />
                <span className="text-sm font-bold text-slate-700">{t(locale, i18nKey)}</span>
              </label>
            ))}
          </div>

          <button
            type="button"
            onClick={() => void saveSettings()}
            disabled={savingSettings || settingsForm.enabled_methods.length === 0}
            className="mt-5 inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-black text-white hover:bg-teal-700 disabled:opacity-40"
          >
            {savingSettings && <Loader2 className="h-4 w-4 animate-spin" />}
            {t(locale, 'cagnottesModule.settings.save')}
          </button>
        </div>
      )}
    </main>
  );
}
