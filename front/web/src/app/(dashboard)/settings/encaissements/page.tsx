'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { Banknote, CreditCard, Loader2, Plus, ShieldCheck, Smartphone, Trash2, X } from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, getStoredUser, type AppLocale, type StoredAuthUser } from '@/lib/i18n';
import {
  isPrincipalUser,
  paymentProfilesT,
  type PaymentProfilesKey,
} from '@/lib/i18n/payment-profiles';

const emptySubscribe = () => () => {};

type ProfileType = 'stripe_keys' | 'bank_account' | 'mobile_money';

type SecretState = { configured?: boolean; mask?: string | null };

type PaymentProfile = {
  id: number;
  type: ProfileType;
  label: string;
  status: 'draft' | 'verified' | 'active' | string;
  is_default: boolean;
  details?: Record<string, string> | null;
  secrets?: Record<string, SecretState> | null;
};

const SECRET_FIELDS: Record<ProfileType, Array<{ name: string; labelKey: PaymentProfilesKey }>> = {
  stripe_keys: [
    { name: 'secret_key', labelKey: 'fieldStripeSecretKey' },
    { name: 'publishable_key', labelKey: 'fieldStripePublishableKey' },
    { name: 'webhook_secret', labelKey: 'fieldStripeWebhookSecret' },
  ],
  bank_account: [{ name: 'iban', labelKey: 'fieldIban' }],
  mobile_money: [{ name: 'phone_number', labelKey: 'fieldPhoneNumber' }],
};

const DETAIL_FIELDS: Record<ProfileType, Array<{ name: string; labelKey: PaymentProfilesKey }>> = {
  stripe_keys: [],
  bank_account: [
    { name: 'account_holder', labelKey: 'fieldAccountHolder' },
    { name: 'bank_name', labelKey: 'fieldBankName' },
    { name: 'bic', labelKey: 'fieldBic' },
  ],
  mobile_money: [
    { name: 'operator', labelKey: 'fieldOperator' },
    { name: 'account_holder', labelKey: 'fieldAccountHolder' },
  ],
};

const TYPE_LABEL_KEYS: Record<ProfileType, PaymentProfilesKey> = {
  stripe_keys: 'typeStripe',
  bank_account: 'typeBank',
  mobile_money: 'typeMobile',
};

const STATUS_LABEL_KEYS: Record<string, PaymentProfilesKey> = {
  draft: 'statusDraft',
  verified: 'statusVerified',
  active: 'statusActive',
};

const STATUS_CLASSES: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-700',
  verified: 'bg-amber-50 text-amber-700',
  active: 'bg-emerald-50 text-emerald-700',
};

function typeIcon(type: ProfileType) {
  if (type === 'stripe_keys') return CreditCard;
  if (type === 'bank_account') return Banknote;
  return Smartphone;
}

export default function PaymentProfilesPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const tt = useCallback((key: PaymentProfilesKey) => paymentProfilesT(locale, key), [locale]);
  const [user] = useState<StoredAuthUser | null>(() => getStoredUser());

  const [profiles, setProfiles] = useState<PaymentProfile[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [editing, setEditing] = useState<PaymentProfile | null>(null);
  const [formType, setFormType] = useState<ProfileType>('stripe_keys');
  const [formLabel, setFormLabel] = useState('');
  const [formSecrets, setFormSecrets] = useState<Record<string, string>>({});
  const [formDetails, setFormDetails] = useState<Record<string, string>>({});
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiFetch('/billing/payment-profiles', { _cacheBust: true });
      const payload = await response.json();
      setProfiles(Array.isArray(payload?.data?.items) ? payload.data.items : []);
    } catch {
      setError(tt('loadError'));
    } finally {
      setLoading(false);
    }
  }, [tt]);

  useEffect(() => {
    if (isPrincipalUser(user)) {
      void load();
    } else {
      setLoading(false);
    }
  }, [user, load]);

  const openCreate = () => {
    setEditing(null);
    setFormType('stripe_keys');
    setFormLabel('');
    setFormSecrets({});
    setFormDetails({});
    setFormError(null);
    setFormOpen(true);
  };

  const openEdit = (profile: PaymentProfile) => {
    setEditing(profile);
    setFormType(profile.type);
    setFormLabel(profile.label);
    setFormSecrets({});
    setFormDetails({ ...(profile.details || {}) });
    setFormError(null);
    setFormOpen(true);
  };

  const save = async () => {
    setSaving(true);
    setFormError(null);
    try {
      // Write-only : seuls les secrets saisis (non vides) partent au serveur.
      const secrets = Object.fromEntries(
        Object.entries(formSecrets).filter(([, value]) => value.trim() !== '')
      );
      const body = {
        ...(editing ? {} : { type: formType }),
        label: formLabel,
        details: formDetails,
        ...(Object.keys(secrets).length ? { secrets } : {}),
      };
      const response = await apiFetch(
        editing ? `/billing/payment-profiles/${editing.id}` : '/billing/payment-profiles',
        { method: editing ? 'PUT' : 'POST', body: JSON.stringify(body) }
      );
      if (!response.ok) throw new Error('save_failed');
      setNotice(tt('saved'));
      setFormOpen(false);
      await load();
    } catch {
      setFormError(tt('saveError'));
    } finally {
      setSaving(false);
    }
  };

  const activate = async (profile: PaymentProfile) => {
    setBusyId(profile.id);
    try {
      const response = await apiFetch(`/billing/payment-profiles/${profile.id}/activate`, {
        method: 'POST',
      });
      if (!response.ok) throw new Error('activate_failed');
      setNotice(tt('activated'));
      await load();
    } catch {
      setError(tt('saveError'));
    } finally {
      setBusyId(null);
    }
  };

  const remove = async (id: number) => {
    setBusyId(id);
    try {
      const response = await apiFetch(`/billing/payment-profiles/${id}`, { method: 'DELETE' });
      if (!response.ok && response.status !== 204) throw new Error('delete_failed');
      setNotice(tt('deleted'));
      setConfirmDeleteId(null);
      await load();
    } catch {
      setError(tt('saveError'));
    } finally {
      setBusyId(null);
    }
  };

  if (!isPrincipalUser(user)) {
    return (
      <ModulePageShell title={tt('title')} subtitle={tt('subtitle')}>
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center">
          <ShieldCheck className="mx-auto h-8 w-8 text-slate-400" aria-hidden="true" />
          <h2 className="mt-3 text-lg font-bold text-slate-900">{tt('reservedTitle')}</h2>
          <p className="mt-1 text-sm text-slate-600">{tt('reservedBody')}</p>
        </div>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell title={tt('title')} subtitle={tt('subtitle')}>
      <div className="space-y-6">
        <div className="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
          <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
          <p className="text-sm text-emerald-900">{tt('securityNotice')}</p>
        </div>

        {notice ? (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800" role="status">
            {notice}
          </div>
        ) : null}
        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">
            {error}{' '}
            <button type="button" className="font-semibold underline" onClick={() => void load()}>
              {tt('retry')}
            </button>
          </div>
        ) : null}

        <div className="flex justify-end">
          <button
            type="button"
            onClick={openCreate}
            className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-700"
          >
            <Plus className="h-4 w-4" aria-hidden="true" />
            {tt('addProfile')}
          </button>
        </div>

        {loading ? (
          <div className="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            {tt('loading')}
          </div>
        ) : profiles.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            {tt('listEmpty')}
          </div>
        ) : (
          <ul className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {profiles.map((profile) => {
              const Icon = typeIcon(profile.type);
              return (
                <li key={profile.id} className="rounded-2xl border border-slate-200 bg-white p-5">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100">
                        <Icon className="h-5 w-5 text-slate-600" aria-hidden="true" />
                      </div>
                      <div>
                        <p className="text-sm font-bold text-slate-900">{profile.label}</p>
                        <p className="text-xs text-slate-500">{tt(TYPE_LABEL_KEYS[profile.type])}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_CLASSES[profile.status] ?? 'bg-slate-100 text-slate-700'}`}
                      >
                        {tt(STATUS_LABEL_KEYS[profile.status] ?? 'statusDraft')}
                      </span>
                      {profile.is_default ? (
                        <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                          {tt('defaultBadge')}
                        </span>
                      ) : null}
                    </div>
                  </div>

                  <dl className="mt-4 space-y-1 text-xs text-slate-600">
                    {SECRET_FIELDS[profile.type].map((field) => {
                      const state = profile.secrets?.[field.name];
                      return (
                        <div key={field.name} className="flex justify-between gap-2">
                          <dt>{tt(field.labelKey)}</dt>
                          <dd className="font-mono">
                            {state?.configured ? state.mask || tt('configured') : tt('notConfigured')}
                          </dd>
                        </div>
                      );
                    })}
                  </dl>

                  <div className="mt-4 flex flex-wrap items-center justify-end gap-2">
                    {profile.status !== 'active' ? (
                      <button
                        type="button"
                        disabled={busyId === profile.id}
                        onClick={() => void activate(profile)}
                        className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-500 disabled:opacity-50"
                      >
                        {tt('activate')}
                      </button>
                    ) : null}
                    <button
                      type="button"
                      onClick={() => openEdit(profile)}
                      className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                    >
                      {tt('editAction')}
                    </button>
                    <button
                      type="button"
                      disabled={busyId === profile.id}
                      onClick={() => setConfirmDeleteId(profile.id)}
                      className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-50"
                    >
                      <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                      {tt('deleteAction')}
                    </button>
                  </div>

                  {confirmDeleteId === profile.id ? (
                    <div className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800">
                      <p>{tt('deleteConfirm')}</p>
                      <div className="mt-2 flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => setConfirmDeleteId(null)}
                          className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-bold text-slate-700"
                        >
                          {tt('cancel')}
                        </button>
                        <button
                          type="button"
                          disabled={busyId === profile.id}
                          onClick={() => void remove(profile.id)}
                          className="rounded-lg bg-red-600 px-3 py-1.5 font-bold text-white disabled:opacity-50"
                        >
                          {tt('deleteConfirmAction')}
                        </button>
                      </div>
                    </div>
                  ) : null}
                </li>
              );
            })}
          </ul>
        )}

        {formOpen ? (
          <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-8">
            <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
              <div className="flex items-center justify-between">
                <h2 className="text-lg font-bold text-slate-900">
                  {editing ? editing.label : tt('addProfile')}
                </h2>
                <button type="button" onClick={() => setFormOpen(false)} aria-label={tt('cancel')}>
                  <X className="h-5 w-5 text-slate-400" aria-hidden="true" />
                </button>
              </div>

              <div className="mt-5 space-y-4">
                {!editing ? (
                  <label className="block text-sm">
                    <span className="font-semibold text-slate-700">{tt('typeLabel')}</span>
                    <select
                      value={formType}
                      onChange={(event) => {
                        setFormType(event.target.value as ProfileType);
                        setFormSecrets({});
                        setFormDetails({});
                      }}
                      className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2"
                    >
                      <option value="stripe_keys">{tt('typeStripe')}</option>
                      <option value="bank_account">{tt('typeBank')}</option>
                      <option value="mobile_money">{tt('typeMobile')}</option>
                    </select>
                  </label>
                ) : null}

                <label className="block text-sm">
                  <span className="font-semibold text-slate-700">{tt('labelField')}</span>
                  <input
                    type="text"
                    value={formLabel}
                    maxLength={120}
                    placeholder={tt('labelPlaceholder')}
                    onChange={(event) => setFormLabel(event.target.value)}
                    className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2"
                  />
                </label>

                {DETAIL_FIELDS[formType].map((field) => (
                  <label key={field.name} className="block text-sm">
                    <span className="font-semibold text-slate-700">{tt(field.labelKey)}</span>
                    <input
                      type="text"
                      value={formDetails[field.name] ?? ''}
                      onChange={(event) =>
                        setFormDetails((prev) => ({ ...prev, [field.name]: event.target.value }))
                      }
                      className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2"
                    />
                  </label>
                ))}

                {SECRET_FIELDS[formType].map((field) => (
                  <label key={field.name} className="block text-sm">
                    <span className="font-semibold text-slate-700">{tt(field.labelKey)}</span>
                    <input
                      type="password"
                      autoComplete="off"
                      value={formSecrets[field.name] ?? ''}
                      placeholder={
                        editing?.secrets?.[field.name]?.configured
                          ? editing.secrets[field.name].mask || ''
                          : ''
                      }
                      onChange={(event) =>
                        setFormSecrets((prev) => ({ ...prev, [field.name]: event.target.value }))
                      }
                      className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 font-mono"
                    />
                    {editing ? (
                      <span className="mt-1 block text-xs text-slate-500">{tt('secretKeepHint')}</span>
                    ) : null}
                  </label>
                ))}

                {formError ? <p className="text-sm text-red-600">{formError}</p> : null}

                <div className="flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setFormOpen(false)}
                    className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700"
                  >
                    {tt('cancel')}
                  </button>
                  <button
                    type="button"
                    disabled={saving || formLabel.trim().length === 0}
                    onClick={() => void save()}
                    className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                  >
                    {saving ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : null}
                    {tt('save')}
                  </button>
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </ModulePageShell>
  );
}
