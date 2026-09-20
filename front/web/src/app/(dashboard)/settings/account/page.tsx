'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { CreditCard, ExternalLink, KeyRound, ShieldCheck, UserCircle } from 'lucide-react';

import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { TwoFactorPanel } from '@/components/settings/two-factor-panel';
import { Button } from '@/components/ui/Button';
import {
  getCopy,
  getPreferredLocale,
  getStoredUser,
  storeAuthSession,
  type AppLocale,
  type StoredAuthUser,
} from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const inputClassName =
  'w-full rounded-xl border border-app-border bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400';

const emptySubscribe = () => () => {};

const emptyPasswords = { current: '', next: '', confirm: '' };
const emptyProfile = { first_name: '', last_name: '', phone: '' };

type ProfileForm = typeof emptyProfile;

type Subscription = {
  id: number | string;
  plan: string;
  status: string;
  current_period_end?: string | null;
};

// Codes canoniques PlanCode (ADR-0014) + alias legacy — même mapping que
// /billing (noms de produits, pas des littéraux à traduire).
const PLAN_LABELS: Record<string, string> = {
  free: 'Free',
  pilot: 'Pilot',
  operations: 'Operations',
  enterprise: 'Enterprise',
  starter: 'Pilot',
  business: 'Operations',
};

/**
 * Mon compte (issue #7861, remplace l'écran lecture seule de l'audit 2026-09-13).
 *
 * - Profil ÉDITABLE (prénom, nom, téléphone) : pré-rempli via GET /auth/me,
 *   sauvegardé via PATCH /auth/profile, session locale resynchronisée
 *   (`storeAuthSession`) pour que le nom affiché dans la barre se rafraîchisse.
 *   L'email (identifiant de connexion) reste volontairement non modifiable ici.
 * - Sécurité : changement de mot de passe (POST /auth/change-password) + 2FA
 *   intégrée via le composant partagé `TwoFactorPanel` (la route
 *   /settings/security/2fa reste fonctionnelle et rend le même composant).
 * - Abonnement & facturation : résumé du plan courant (GET /billing/subscription,
 *   silencieux si 403/absent) avec lien vers /billing.
 */
export default function AccountPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const copy = getCopy(locale);
  const [user, setUser] = useState<StoredAuthUser | null>(() => getStoredUser());

  // Profil éditable.
  const [profile, setProfile] = useState<ProfileForm>({ ...emptyProfile });
  const [profileLoading, setProfileLoading] = useState(true);
  const [profileSaving, setProfileSaving] = useState(false);
  const [profileError, setProfileError] = useState<string | null>(null);

  // Mot de passe.
  const [form, setForm] = useState({ ...emptyPasswords });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [notice, setNotice] = useState<string | null>(null);

  // Abonnement (résumé, silencieux si non accessible).
  const [subscription, setSubscription] = useState<Subscription | null>(null);
  const [billingVisible, setBillingVisible] = useState(false);

  const load = useCallback(async () => {
    // 1. Profil frais depuis l'API (fallback : session locale déjà affichée).
    try {
      const response = await apiFetch('/auth/me');
      const payload = (await response.json()) as { data?: StoredAuthUser };
      if (payload.data) {
        setUser(payload.data);
        setProfile({
          first_name: payload.data.first_name ?? '',
          last_name: payload.data.last_name ?? '',
          phone: payload.data.phone ?? '',
        });
      }
    } catch {
      const stored = getStoredUser();
      if (stored) {
        setProfile({
          first_name: stored.first_name ?? '',
          last_name: stored.last_name ?? '',
          phone: stored.phone ?? '',
        });
      }
      setProfileError(i18nT(locale, 'settingsPage.profileLoadError'));
    } finally {
      setProfileLoading(false);
    }

    // 2. Abonnement — strictement facultatif : 403 (accès réservé) ou 404
    //    (aucun abonnement) ne produisent AUCUNE erreur visible.
    try {
      const response = await apiFetch('/billing/subscription');
      const payload = (await response.json()) as { data?: Subscription | null };
      setSubscription(payload.data ?? null);
      setBillingVisible(true);
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) {
        setSubscription(null);
        setBillingVisible(true);
      } else {
        setBillingVisible(false);
      }
    }
  }, [locale]);

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const submitProfile = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setProfileError(null);
    setNotice(null);
    setProfileSaving(true);

    try {
      const response = await apiFetch('/auth/profile', {
        method: 'PATCH',
        body: JSON.stringify({
          first_name: profile.first_name.trim(),
          last_name: profile.last_name.trim(),
          phone: profile.phone.trim() === '' ? null : profile.phone.trim(),
        }),
      });

      const payload = (await response.json()) as { data?: StoredAuthUser };
      const stored = getStoredUser();
      const refreshed: StoredAuthUser = {
        ...(stored ?? {}),
        ...(payload.data ?? {}),
        first_name: payload.data?.first_name ?? profile.first_name.trim(),
        last_name: payload.data?.last_name ?? profile.last_name.trim(),
        phone: payload.data?.phone ?? (profile.phone.trim() || null),
      };

      // Resynchronise la session locale → le nom affiché dans la barre du
      // haut se met à jour au prochain rendu du layout.
      storeAuthSession(null, refreshed);
      setUser(refreshed);
      setNotice(i18nT(locale, 'settingsPage.profileUpdated'));
    } catch (err) {
      setProfileError(
        err instanceof ApiError ? err.message : i18nT(locale, 'settingsPage.profileLoadError'),
      );
    } finally {
      setProfileSaving(false);
    }
  };

  const submitPassword = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setNotice(null);

    if (form.next.length < 8) {
      setError(i18nT(locale, 'settingsPage.minLengthHint'));
      return;
    }

    if (form.next !== form.confirm) {
      setError(i18nT(locale, 'settingsPage.passwordsMismatch'));
      return;
    }

    setSaving(true);

    try {
      // Route handler dédié : il repose le nouveau jeton Sanctum dans le cookie
      // httpOnly (le changement révoque tous les jetons, y compris le nôtre).
      await apiFetch('/auth/change-password', {
        method: 'POST',
        body: JSON.stringify({
          current_password: form.current,
          new_password: form.next,
          new_password_confirmation: form.confirm,
        }),
      });

      setForm({ ...emptyPasswords });
      setNotice(i18nT(locale, 'settingsPage.passwordUpdated'));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : i18nT(locale, 'settingsPage.passwordsMismatch'));
    } finally {
      setSaving(false);
    }
  };

  const subscriptionStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
      active: copy.billing.statusActive,
      cancelled: copy.billing.statusCancelled,
      past_due: copy.billing.statusPastDue,
      pending: copy.billing.statusPending,
    };
    return labels[status] ?? status;
  };

  return (
    <ModulePageShell
      title={i18nT(locale, 'settingsPage.title')}
      subtitle={i18nT(locale, 'settingsPage.tenantSubtitle')}
      icon={UserCircle}
      accentClassName="bg-gradient-to-br from-slate-50 via-white to-white"
    >
      {notice ? (
        <div
          className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
          role="status"
        >
          {notice}
        </div>
      ) : null}

      <section className="grid gap-6 lg:grid-cols-2">
        {/* Profil — éditable (#7861). */}
        <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
            <UserCircle className="h-4 w-4 text-slate-400" aria-hidden="true" />
            {i18nT(locale, 'settingsPage.profileTitle')}
          </h2>
          <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'settingsPage.profileSubtitle')}</p>

          <form onSubmit={submitProfile} className="mt-5 space-y-4" aria-busy={profileLoading}>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-slate-700">
                  {i18nT(locale, 'settingsPage.firstName')}
                </span>
                <input
                  type="text"
                  autoComplete="given-name"
                  value={profile.first_name}
                  onChange={(event) => setProfile((value) => ({ ...value, first_name: event.target.value }))}
                  disabled={profileLoading}
                  maxLength={100}
                  data-testid="account-first-name"
                  className={inputClassName}
                />
              </label>

              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-slate-700">
                  {i18nT(locale, 'settingsPage.lastName')}
                </span>
                <input
                  type="text"
                  autoComplete="family-name"
                  value={profile.last_name}
                  onChange={(event) => setProfile((value) => ({ ...value, last_name: event.target.value }))}
                  disabled={profileLoading}
                  maxLength={100}
                  data-testid="account-last-name"
                  className={inputClassName}
                />
              </label>
            </div>

            <label className="block">
              <span className="mb-2 block text-sm font-semibold text-slate-700">
                {i18nT(locale, 'settingsPage.phone')}
              </span>
              <input
                type="tel"
                autoComplete="tel"
                value={profile.phone}
                onChange={(event) => setProfile((value) => ({ ...value, phone: event.target.value }))}
                disabled={profileLoading}
                maxLength={30}
                data-testid="account-phone"
                className={inputClassName}
              />
            </label>

            <label className="block">
              <span className="mb-2 block text-sm font-semibold text-slate-700">
                {i18nT(locale, 'settingsPage.email')}
              </span>
              <input type="email" value={user?.email ?? ''} disabled readOnly className={inputClassName} />
              <span className="mt-1 block text-xs text-slate-400">{i18nT(locale, 'settingsPage.emailLocked')}</span>
            </label>

            {user?.company?.name ? (
              <p className="text-xs font-medium text-slate-500">
                <span className="font-bold uppercase tracking-widest text-slate-400">
                  {i18nT(locale, 'settingsPage.company')}
                </span>{' '}
                — {user.company.name}
              </p>
            ) : null}

            {profileError ? (
              <p role="alert" className="text-sm font-medium text-red-600">
                {profileError}
              </p>
            ) : null}

            <Button
              type="submit"
              loading={profileSaving}
              disabled={profileSaving || profileLoading}
              data-testid="account-save-profile"
            >
              {i18nT(locale, 'settingsPage.saveChanges')}
            </Button>
          </form>
        </div>

        {/* Sécurité — mot de passe. */}
        <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
            <KeyRound className="h-4 w-4 text-slate-400" aria-hidden="true" />
            {i18nT(locale, 'settingsPage.passwordTitle')}
          </h2>
          <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'settingsPage.passwordSubtitle')}</p>

          <form onSubmit={submitPassword} className="mt-5 space-y-4">
            <label className="block">
              <span className="mb-2 block text-sm font-semibold text-slate-700">{i18nT(locale, 'settingsPage.currentPassword')}</span>
              <input
                type="password"
                autoComplete="current-password"
                value={form.current}
                onChange={(event) => setForm((value) => ({ ...value, current: event.target.value }))}
                required
                className={inputClassName}
              />
            </label>

            <label className="block">
              <span className="mb-2 block text-sm font-semibold text-slate-700">{i18nT(locale, 'settingsPage.newPassword')}</span>
              <input
                type="password"
                autoComplete="new-password"
                value={form.next}
                onChange={(event) => setForm((value) => ({ ...value, next: event.target.value }))}
                required
                minLength={8}
                className={inputClassName}
              />
              <span className="mt-1 block text-xs text-slate-400">{i18nT(locale, 'settingsPage.minLengthHint')}</span>
            </label>

            <label className="block">
              <span className="mb-2 block text-sm font-semibold text-slate-700">{i18nT(locale, 'settingsPage.confirmPassword')}</span>
              <input
                type="password"
                autoComplete="new-password"
                value={form.confirm}
                onChange={(event) => setForm((value) => ({ ...value, confirm: event.target.value }))}
                required
                minLength={8}
                className={inputClassName}
              />
            </label>

            {error ? (
              <p role="alert" className="text-sm font-medium text-red-600">
                {error}
              </p>
            ) : null}

            <Button type="submit" loading={saving} disabled={saving} data-testid="account-change-password">
              {i18nT(locale, 'settingsPage.updatePassword')}
            </Button>
          </form>
        </div>
      </section>

      {/* Sécurité — 2FA intégrée (composant partagé avec /settings/security/2fa). */}
      <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
          <ShieldCheck className="h-4 w-4 text-slate-400" aria-hidden="true" />
          {i18nT(locale, 'settingsPage.twoFactorTitle')}
        </h2>
        <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'settingsPage.twoFactorSubtitle')}</p>
        <div className="mt-5 max-w-xl">
          <TwoFactorPanel locale={locale} />
        </div>
      </section>

      {/* Abonnement & facturation — résumé silencieux si non accessible. */}
      {billingVisible ? (
        <section
          className="rounded-3xl border border-app-border bg-white p-6 shadow-sm"
          data-testid="account-billing-card"
        >
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
            <CreditCard className="h-4 w-4 text-slate-400" aria-hidden="true" />
            {i18nT(locale, 'settingsPage.billing.title')}
          </h2>
          <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'settingsPage.billing.subtitle')}</p>

          {subscription ? (
            <dl className="mt-5 flex flex-wrap items-center gap-x-10 gap-y-4">
              <div>
                <dt className="text-xs font-bold uppercase tracking-widest text-slate-400">
                  {i18nT(locale, 'settingsPage.billing.plan')}
                </dt>
                <dd className="mt-1 text-sm font-bold text-slate-950" data-testid="account-billing-plan">
                  {PLAN_LABELS[subscription.plan] ?? subscription.plan}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold uppercase tracking-widest text-slate-400">
                  {i18nT(locale, 'settingsPage.billing.status')}
                </dt>
                <dd className="mt-1">
                  <span
                    className={[
                      'inline-flex rounded-lg px-2.5 py-1 text-xs font-bold',
                      subscription.status === 'active'
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-700',
                    ].join(' ')}
                  >
                    {subscriptionStatusLabel(subscription.status)}
                  </span>
                </dd>
              </div>
            </dl>
          ) : (
            <p className="mt-5 text-sm font-medium text-slate-500">{i18nT(locale, 'settingsPage.billing.none')}</p>
          )}

          <Link
            href="/billing"
            className="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700"
            data-testid="account-billing-link"
          >
            {i18nT(locale, 'settingsPage.billing.manage')}
            <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
          </Link>
        </section>
      ) : null}
    </ModulePageShell>
  );
}
