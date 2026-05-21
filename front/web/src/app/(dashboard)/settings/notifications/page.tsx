'use client';

import { useEffect, useState } from 'react';
import { Bell, Mail, MessageSquareText, Smartphone, Save, ShieldCheck } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';

type NotificationPreferences = {
  app_enabled: boolean;
  email_enabled: boolean;
  push_enabled: boolean;
  sms_enabled: boolean;
  whatsapp_enabled: boolean;
  locale?: string | null;
  timezone?: string | null;
  categories?: Record<string, boolean> | null;
  quiet_hours?: {
    enabled?: boolean;
    start?: string;
    end?: string;
  } | null;
};

const CHANNELS = [
  { key: 'app_enabled', label: 'Dans l app', description: 'Centre de notifications web et mobile.', icon: Bell },
  { key: 'email_enabled', label: 'Email', description: 'Messages importants et confirmations.', icon: Mail },
  { key: 'push_enabled', label: 'Push mobile/web', description: 'Alertes rapides sur les appareils enregistres.', icon: Smartphone },
  { key: 'sms_enabled', label: 'SMS', description: 'Canal court pour urgences, active apres opt-in.', icon: MessageSquareText },
  { key: 'whatsapp_enabled', label: 'WhatsApp', description: 'Canal conversationnel futur, avec opt-in explicite.', icon: MessageSquareText },
] as const;

const CATEGORIES = [
  { key: 'hr', label: 'RH' },
  { key: 'payroll', label: 'Paie' },
  { key: 'security', label: 'Securite' },
  { key: 'system', label: 'Systeme' },
  { key: 'marketing', label: 'Conseils produit' },
] as const;

export default function NotificationSettingsPage() {
  const [preferences, setPreferences] = useState<NotificationPreferences | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadPreferences() {
      try {
        const response = await apiFetch('/notification-preferences');
        const payload = await response.json() as { data?: NotificationPreferences };

        if (!cancelled) {
          setPreferences(normalizePreferences(payload.data));
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void loadPreferences();

    return () => {
      cancelled = true;
    };
  }, []);

  const updateChannel = (key: keyof NotificationPreferences, value: boolean) => {
    setPreferences((current) => current ? { ...current, [key]: value } : current);
  };

  const updateCategory = (key: string, value: boolean) => {
    setPreferences((current) => current ? {
      ...current,
      categories: {
        ...(current.categories ?? {}),
        [key]: value,
      },
    } : current);
  };

  const updateQuietHours = (field: 'enabled' | 'start' | 'end', value: boolean | string) => {
    setPreferences((current) => current ? {
      ...current,
      quiet_hours: {
        enabled: false,
        start: '20:00',
        end: '07:00',
        ...(current.quiet_hours ?? {}),
        [field]: value,
      },
    } : current);
  };

  const save = async () => {
    if (!preferences) {
      return;
    }

    setSaving(true);
    setMessage(null);

    try {
      const response = await apiFetch('/notification-preferences', {
        method: 'PATCH',
        body: JSON.stringify({
          app_enabled: preferences.app_enabled,
          email_enabled: preferences.email_enabled,
          push_enabled: preferences.push_enabled,
          sms_enabled: preferences.sms_enabled,
          whatsapp_enabled: preferences.whatsapp_enabled,
          locale: preferences.locale ?? 'fr',
          timezone: preferences.timezone ?? 'Africa/Algiers',
          categories: preferences.categories ?? {},
          quiet_hours: preferences.quiet_hours ?? { enabled: false, start: '20:00', end: '07:00' },
        }),
      });
      const payload = await response.json() as { data?: NotificationPreferences };
      setPreferences(normalizePreferences(payload.data));
      setMessage('Preferences enregistrees.');
    } catch {
      setMessage('Impossible d enregistrer les preferences pour le moment.');
    } finally {
      setSaving(false);
    }
  };

  if (loading || !preferences) {
    return (
      <div className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <div className="h-6 w-48 animate-pulse rounded bg-slate-100" />
        <div className="mt-6 grid gap-4 md:grid-cols-2">
          {[0, 1, 2, 3].map((item) => (
            <div key={item} className="h-28 animate-pulse rounded-2xl bg-slate-100" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Communication interne</p>
            <h1 className="mt-2 text-3xl font-black text-slate-950">Mes preferences de notifications</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
              Choisissez les canaux utiles sans perdre les alertes critiques. Les messages courts restent limites aux contenus non sensibles.
            </p>
          </div>
          <button
            type="button"
            onClick={() => void save()}
            disabled={saving}
            className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Save className="h-4 w-4" aria-hidden="true" />
            {saving ? 'Enregistrement...' : 'Enregistrer'}
          </button>
        </div>
        {message ? (
          <p className="mt-4 rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-800">{message}</p>
        ) : null}
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {CHANNELS.map((channel) => {
          const Icon = channel.icon;
          const enabled = Boolean(preferences[channel.key]);

          return (
            <button
              key={channel.key}
              type="button"
              onClick={() => updateChannel(channel.key, !enabled)}
              className={`rounded-2xl border p-5 text-left shadow-sm transition ${
                enabled ? 'border-teal-300 bg-teal-50' : 'border-slate-200 bg-white hover:border-slate-300'
              }`}
            >
              <div className="flex items-start justify-between gap-4">
                <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${enabled ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600'}`}>
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </div>
                <span className={`rounded-full px-3 py-1 text-xs font-bold ${enabled ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-500'}`}>
                  {enabled ? 'Actif' : 'Desactive'}
                </span>
              </div>
              <h2 className="mt-4 text-lg font-black text-slate-950">{channel.label}</h2>
              <p className="mt-2 text-sm leading-6 text-slate-600">{channel.description}</p>
            </button>
          );
        })}
      </section>

      <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-xl font-black text-slate-950">Categories</h2>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {CATEGORIES.map((category) => {
              const enabled = preferences.categories?.[category.key] ?? true;

              return (
                <label key={category.key} className="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                  <span className="text-sm font-bold text-slate-800">{category.label}</span>
                  <input
                    type="checkbox"
                    checked={enabled}
                    onChange={(event) => updateCategory(category.key, event.target.checked)}
                    className="h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                  />
                </label>
              );
            })}
          </div>
        </div>

        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-950 text-white">
              <ShieldCheck className="h-5 w-5" aria-hidden="true" />
            </div>
            <div>
              <h2 className="text-lg font-black text-slate-950">Heures calmes</h2>
              <p className="text-xs text-slate-500">Les alertes critiques restent possibles.</p>
            </div>
          </div>
          <label className="mt-5 flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800">
            Activer
            <input
              type="checkbox"
              checked={Boolean(preferences.quiet_hours?.enabled)}
              onChange={(event) => updateQuietHours('enabled', event.target.checked)}
              className="h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
            />
          </label>
          <div className="mt-4 grid grid-cols-2 gap-3">
            <label className="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">
              Debut
              <input
                type="time"
                value={preferences.quiet_hours?.start ?? '20:00'}
                onChange={(event) => updateQuietHours('start', event.target.value)}
                className="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800"
              />
            </label>
            <label className="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">
              Fin
              <input
                type="time"
                value={preferences.quiet_hours?.end ?? '07:00'}
                onChange={(event) => updateQuietHours('end', event.target.value)}
                className="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800"
              />
            </label>
          </div>
        </div>
      </section>
    </div>
  );
}

function normalizePreferences(input?: NotificationPreferences): NotificationPreferences {
  return {
    app_enabled: input?.app_enabled ?? true,
    email_enabled: input?.email_enabled ?? true,
    push_enabled: input?.push_enabled ?? true,
    sms_enabled: input?.sms_enabled ?? false,
    whatsapp_enabled: input?.whatsapp_enabled ?? false,
    locale: input?.locale ?? 'fr',
    timezone: input?.timezone ?? 'Africa/Algiers',
    categories: {
      hr: true,
      payroll: true,
      security: true,
      system: true,
      marketing: false,
      ...(input?.categories ?? {}),
    },
    quiet_hours: {
      enabled: false,
      start: '20:00',
      end: '07:00',
      ...(input?.quiet_hours ?? {}),
    },
  };
}
