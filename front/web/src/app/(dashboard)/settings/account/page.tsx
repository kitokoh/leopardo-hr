'use client';

import { useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { KeyRound, ShieldCheck, UserCircle } from 'lucide-react';

import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { getDisplayName, getPreferredLocale, getStoredUser, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const inputClassName =
  'w-full rounded-xl border border-app-border bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';

const emptyPasswords = { current: '', next: '', confirm: '' };

/**
 * Mon compte (audit 2026-09-13).
 *
 * `/settings` redirigeait vers `/settings/developer` — un écran de développeur —
 * et **aucune surface web ne permettait de changer son mot de passe**, alors que
 * l'API expose `POST /auth/change-password` et que le catalogue i18n contenait
 * déjà toutes les chaînes (`settingsPage.password*`). Le menu utilisateur de la
 * barre du haut pointe ici.
 */
export default function AccountPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  const [user] = useState(() => getStoredUser());
  const [form, setForm] = useState({ ...emptyPasswords });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
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

  return (
    <ModulePageShell
      title={i18nT(locale, 'settingsPage.title')}
      subtitle={i18nT(locale, 'settingsPage.tenantSubtitle')}
      icon={UserCircle}
      accentClassName="bg-gradient-to-br from-slate-50 via-white to-white"
    >
      {notice ? (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
          {notice}
        </div>
      ) : null}

      <section className="grid gap-6 lg:grid-cols-2">
        {/* Profil — données de session, non modifiables ici. */}
        <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
            <UserCircle className="h-4 w-4 text-slate-400" aria-hidden="true" />
            {i18nT(locale, 'settingsPage.profileTitle')}
          </h2>
          <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'settingsPage.profileSubtitle')}</p>

          <dl className="mt-5 space-y-4">
            <div>
              <dt className="text-xs font-bold uppercase tracking-widest text-slate-400">{i18nT(locale, 'settingsPage.fullName')}</dt>
              <dd className="mt-1 text-sm font-bold text-slate-950">{user ? getDisplayName(user) : '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-bold uppercase tracking-widest text-slate-400">{i18nT(locale, 'settingsPage.email')}</dt>
              <dd className="mt-1 text-sm font-medium text-slate-700">{user?.email ?? '—'}</dd>
            </div>
            {user?.company?.name ? (
              <div>
                <dt className="text-xs font-bold uppercase tracking-widest text-slate-400">{i18nT(locale, 'settingsPage.company')}</dt>
                <dd className="mt-1 text-sm font-medium text-slate-700">{user.company.name}</dd>
              </div>
            ) : null}
          </dl>
        </div>

        {/* Mot de passe. */}
        <div className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
            <KeyRound className="h-4 w-4 text-slate-400" aria-hidden="true" />
            {i18nT(locale, 'settingsPage.passwordTitle')}
          </h2>
          <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'settingsPage.passwordSubtitle')}</p>

          <form onSubmit={submit} className="mt-5 space-y-4">
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

      {/* Sécurité — renvoie vers l'écran 2FA existant. */}
      <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
          <ShieldCheck className="h-4 w-4 text-slate-400" aria-hidden="true" />
          {i18nT(locale, 'settingsPage.twoFactorTitle')}
        </h2>
        <p className="mt-2 text-sm leading-6 text-slate-500">{i18nT(locale, 'settingsPage.twoFactorSubtitle')}</p>
        <Link
          href="/settings/security/2fa"
          className="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700"
        >
          {i18nT(locale, 'settingsPage.manage2fa')}
        </Link>
      </section>
    </ModulePageShell>
  );
}
