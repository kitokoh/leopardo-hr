'use client';

/**
 * COULOIR 16 — M3-bis · Vue de l'onglet « Profil ».
 *
 * ## Ce que cette vue règle
 *
 * La **langue** est écrite sur le COMPTE (`PATCH /user/public-profile`), pas
 * dans le stockage local : c'est ce qui la fait suivre le client d'un appareil
 * à l'autre. C'est la raison d'être de `public_profiles`.
 *
 * ## Aucun état de succès déduit localement
 *
 * « Langue enregistrée » n'apparaît qu'après un `2xx`. Un `optimistic update`
 * afficherait un succès sur un refus serveur (422 sur une locale non servie,
 * réseau coupé) — exactement le mensonge que la règle de vérité interdit.
 *
 * ## L'export RGPD est réel
 *
 * Le bouton télécharge le JSON renvoyé par `/user/data-export`. Il ne dit pas
 * « bientôt disponible » : la route existe et est testée. La portée de
 * l'export est affichée à l'écran, y compris ce qu'il ne contient pas.
 */

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import {
  fetchMyDataExport,
  fetchMyIdentity,
  fetchMyProfileSettings,
  logoutAllDevices,
  updateMyProfileSettings,
  type PublicAccountIdentity,
  type PublicProfileSettings,
} from '@/lib/public-account-api';
import { getPublicAccountCopy } from '@/modules/public-portal/data/account-copy';

type LoadState = 'loading' | 'unauthenticated' | 'error' | 'ok';

interface ProfileViewProps {
  locale: string;
}

export default function ProfileView({ locale }: ProfileViewProps) {
  const copy = getPublicAccountCopy(locale);

  const [state, setState] = useState<LoadState>('loading');
  const [identity, setIdentity] = useState<PublicAccountIdentity | null>(null);
  const [settings, setSettings] = useState<PublicProfileSettings | null>(null);

  const [savingLocale, setSavingLocale] = useState(false);
  const [localeSaved, setLocaleSaved] = useState(false);
  const [localeError, setLocaleError] = useState(false);

  const [savingConsent, setSavingConsent] = useState(false);
  const [consentError, setConsentError] = useState(false);

  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState(false);

  const [loggingOut, setLoggingOut] = useState(false);
  const [logoutError, setLogoutError] = useState(false);

  const load = useCallback(async () => {
    setState('loading');

    const [identityResult, settingsResult] = await Promise.all([
      fetchMyIdentity(),
      fetchMyProfileSettings(),
    ]);

    if (identityResult.status === 'unauthenticated' || settingsResult.status === 'unauthenticated') {
      setState('unauthenticated');
      return;
    }

    if (identityResult.status !== 'ok' || settingsResult.status !== 'ok') {
      setState('error');
      return;
    }

    setIdentity(identityResult.data);
    setSettings(settingsResult.data);
    setState('ok');
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleLocaleChange(nextLocale: string) {
    setSavingLocale(true);
    setLocaleSaved(false);
    setLocaleError(false);

    const result = await updateMyProfileSettings({ preferred_locale: nextLocale });

    if (result.status === 'ok') {
      setSettings(result.data);
      setLocaleSaved(true);
    } else {
      setLocaleError(true);
    }

    setSavingLocale(false);
  }

  async function handleConsentToggle(nextValue: boolean) {
    setSavingConsent(true);
    setConsentError(false);

    const result = await updateMyProfileSettings({ notifications_opt_in: nextValue });

    if (result.status === 'ok') {
      setSettings(result.data);
    } else {
      setConsentError(true);
    }

    setSavingConsent(false);
  }

  async function handleExport() {
    setExporting(true);
    setExportError(false);

    const result = await fetchMyDataExport();

    if (result.status === 'ok') {
      // Téléchargement réel du JSON renvoyé par le serveur.
      const blob = new Blob([JSON.stringify(result.data, null, 2)], {
        type: 'application/json',
      });
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `leopardo-donnees-${new Date().toISOString().slice(0, 10)}.json`;
      anchor.click();
      URL.revokeObjectURL(url);
    } else {
      setExportError(true);
    }

    setExporting(false);
  }

  async function handleLogoutAll() {
    setLoggingOut(true);
    setLogoutError(false);

    const result = await logoutAllDevices();

    if (result.status !== 'ok') {
      setLogoutError(true);
    }

    setLoggingOut(false);
  }

  if (state === 'loading') {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-8" aria-busy="true">
        <h1 className="text-2xl font-semibold">{copy.profile.title}</h1>
        <p className="mt-2 text-sm text-muted-foreground" role="status">
          {copy.profile.loading}
        </p>
        <div className="mt-6 space-y-3">
          {[0, 1, 2].map((index) => (
            <div key={index} className="h-20 w-full animate-pulse rounded-lg bg-muted" />
          ))}
        </div>
      </main>
    );
  }

  if (state === 'unauthenticated') {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold">{copy.profile.title}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{copy.profile.unauthenticated}</p>
        <Link
          href="/login"
          className="mt-4 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
        >
          {copy.profile.signIn}
        </Link>
      </main>
    );
  }

  if (state === 'error') {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold">{copy.profile.title}</h1>
        <p className="mt-2 text-sm text-destructive" role="alert">
          {copy.profile.loadError}
        </p>
        <button
          type="button"
          onClick={() => void load()}
          className="mt-4 inline-flex min-h-11 items-center rounded-md border px-4 text-sm font-medium"
        >
          {copy.profile.retry}
        </button>
      </main>
    );
  }

  const displayName =
    [identity?.first_name, identity?.last_name].filter(Boolean).join(' ') || '—';

  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-8">
      <h1 className="text-2xl font-semibold">{copy.profile.title}</h1>
      <p className="mt-1 text-sm text-muted-foreground">{copy.profile.subtitle}</p>

      {/* ── Identité ──────────────────────────────────────────────────────── */}
      <section className="mt-8 rounded-lg border p-4" aria-labelledby="profile-identity">
        <h2 id="profile-identity" className="text-lg font-medium">
          {copy.profile.identity.title}
        </h2>
        <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-muted-foreground">{copy.profile.identity.name}</dt>
            <dd className="font-medium">{displayName}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{copy.profile.identity.email}</dt>
            <dd className="font-medium">{identity?.email ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{copy.profile.identity.phone}</dt>
            <dd className="font-medium">
              {identity?.phone_e164 ?? identity?.phone ?? '—'}{' '}
              <span className="text-xs text-muted-foreground">
                {identity?.phone_verified_at
                  ? copy.profile.identity.phoneVerified
                  : copy.profile.identity.phoneUnverified}
              </span>
            </dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{copy.profile.identity.memberSince}</dt>
            <dd className="font-medium">
              {identity?.created_at ? (
                <time dateTime={identity.created_at}>
                  {new Date(identity.created_at).toLocaleDateString(locale)}
                </time>
              ) : (
                '—'
              )}
            </dd>
          </div>
        </dl>
      </section>

      {/* ── Langue : suit le COMPTE ───────────────────────────────────────── */}
      <section className="mt-6 rounded-lg border p-4" aria-labelledby="profile-language">
        <h2 id="profile-language" className="text-lg font-medium">
          {copy.profile.language.title}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">{copy.profile.language.hint}</p>

        <div className="mt-3 flex flex-wrap gap-2" role="group" aria-labelledby="profile-language">
          {(settings?.supported_locales ?? ['fr', 'en', 'ar', 'tr']).map((code) => {
            const active = settings?.preferred_locale === code;

            return (
              <button
                key={code}
                type="button"
                onClick={() => void handleLocaleChange(code)}
                disabled={savingLocale || active}
                aria-pressed={active}
                className={`inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border px-3 text-sm ${
                  active ? 'bg-primary text-primary-foreground' : ''
                } disabled:opacity-60`}
              >
                {code.toUpperCase()}
              </button>
            );
          })}
        </div>

        <p className="mt-2 text-sm" role="status">
          {savingLocale ? copy.profile.language.saving : null}
          {!savingLocale && localeSaved ? copy.profile.language.saved : null}
        </p>
        {localeError ? (
          <p className="mt-1 text-sm text-destructive" role="alert">
            {copy.profile.language.saveError}
          </p>
        ) : null}
      </section>

      {/* ── Notifications ─────────────────────────────────────────────────── */}
      <section className="mt-6 rounded-lg border p-4" aria-labelledby="profile-notifications">
        <h2 id="profile-notifications" className="text-lg font-medium">
          {copy.profile.notifications.title}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">{copy.profile.notifications.hint}</p>

        <button
          type="button"
          onClick={() => void handleConsentToggle(!settings?.notifications_opt_in)}
          disabled={savingConsent}
          aria-pressed={settings?.notifications_opt_in ?? false}
          className="mt-3 inline-flex min-h-11 items-center rounded-md border px-4 text-sm disabled:opacity-50"
        >
          {settings?.notifications_opt_in
            ? copy.profile.notifications.on
            : copy.profile.notifications.off}
        </button>

        {settings?.notifications_consent_at ? (
          <p className="mt-2 text-xs text-muted-foreground">
            {copy.profile.notifications.consentRecorded}{' '}
            <time dateTime={settings.notifications_consent_at}>
              {new Date(settings.notifications_consent_at).toLocaleDateString(locale)}
            </time>
          </p>
        ) : null}

        {consentError ? (
          <p className="mt-1 text-sm text-destructive" role="alert">
            {copy.profile.notifications.saveError}
          </p>
        ) : null}
      </section>

      {/* ── Données personnelles (RGPD) ───────────────────────────────────── */}
      <section className="mt-6 rounded-lg border p-4" aria-labelledby="profile-data">
        <h2 id="profile-data" className="text-lg font-medium">
          {copy.profile.data.title}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">{copy.profile.data.hint}</p>

        <button
          type="button"
          onClick={() => void handleExport()}
          disabled={exporting}
          className="mt-3 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50"
        >
          {exporting ? copy.profile.data.exporting : copy.profile.data.exportCta}
        </button>

        {/* La portée est dite à l'écran : un client qui cherche ses commandes
            ne doit pas croire qu'elles ont disparu. */}
        <p className="mt-2 text-xs text-muted-foreground">{copy.profile.data.exportScope}</p>

        {exportError ? (
          <p className="mt-1 text-sm text-destructive" role="alert">
            {copy.profile.data.exportError}
          </p>
        ) : null}

        <p className="mt-4 text-xs text-muted-foreground">{copy.profile.data.deleteHint}</p>
      </section>

      {/* ── Sessions ──────────────────────────────────────────────────────── */}
      <section className="mt-6 rounded-lg border p-4" aria-labelledby="profile-sessions">
        <h2 id="profile-sessions" className="text-lg font-medium">
          {copy.profile.sessions.title}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">{copy.profile.sessions.hint}</p>

        <button
          type="button"
          onClick={() => void handleLogoutAll()}
          disabled={loggingOut}
          className="mt-3 inline-flex min-h-11 items-center rounded-md border px-4 text-sm disabled:opacity-50"
        >
          {loggingOut ? copy.profile.sessions.loggingOut : copy.profile.sessions.logoutAll}
        </button>

        {logoutError ? (
          <p className="mt-1 text-sm text-destructive" role="alert">
            {copy.profile.sessions.logoutAllError}
          </p>
        ) : null}
      </section>
    </main>
  );
}
