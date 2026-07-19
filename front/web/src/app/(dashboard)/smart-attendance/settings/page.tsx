'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { Check } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';

// ─── Types ────────────────────────────────────────────────────────────────────

type ForcedMode = 'gps_auto' | 'qr_code' | 'manual' | null;

type ModeSettings = {
  forced_mode: ForcedMode;
  gps_enabled: boolean;
  latitude: number | null;
  longitude: number | null;
  radius: number | null;
};

type SettingsPayload = {
  data?: ModeSettings;
};

type FormState = {
  forced_mode: string; // '' = null
  gps_enabled: boolean;
  latitude: string;
  longitude: string;
  radius: string;
};

const MODE_LABELS: Record<string, string> = {
  '': 'Libre (pas de mode forcé)',
  gps_auto: 'GPS automatique',
  qr_code: 'QR Code',
  manual: 'Manuel',
};

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function SmartAttendanceSettingsPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [currentSettings, setCurrentSettings] = useState<ModeSettings | null>(null);

  const [form, setForm] = useState<FormState>({
    forced_mode: '',
    gps_enabled: false,
    latitude: '',
    longitude: '',
    radius: '',
  });

  const loadSettings = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch('/smart-attendance/mode-settings');
      const payload = await response.json() as SettingsPayload;
      const d = payload.data;
      if (d) {
        setCurrentSettings(d);
        setForm({
          forced_mode: d.forced_mode ?? '',
          gps_enabled: d.gps_enabled,
          latitude: d.latitude != null ? String(d.latitude) : '',
          longitude: d.longitude != null ? String(d.longitude) : '',
          radius: d.radius != null ? String(d.radius) : '',
        });
      }
      setError(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les paramètres.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadSettings();
  }, [loadSettings]);

  const handleSave = async () => {
    setSaving(true);
    setSuccessMsg(null);
    setError(null);
    try {
      const body: Record<string, unknown> = {
        forced_mode: form.forced_mode === '' ? null : form.forced_mode,
        gps_enabled: form.gps_enabled,
        latitude: form.gps_enabled && form.latitude !== '' ? parseFloat(form.latitude) : null,
        longitude: form.gps_enabled && form.longitude !== '' ? parseFloat(form.longitude) : null,
        radius: form.gps_enabled && form.radius !== '' ? parseInt(form.radius, 10) : null,
      };

      await apiFetch('/smart-attendance/mode-settings', {
        method: 'PUT',
        body: JSON.stringify(body),
      });

      setSuccessMsg('Paramètres enregistrés avec succès.');
      await loadSettings();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erreur lors de l\'enregistrement.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <ModulePageShell
      title="Paramètres Smart Attendance"
      subtitle="Configuration du mode de pointage et du géofence entreprise."
      accentClassName="bg-gradient-to-br from-slate-500/10 via-white to-white"
    >
      {/* Back */}
      <div>
        <Link
          href="/smart-attendance"
          className="text-sm font-bold text-slate-500 transition hover:text-slate-900"
        >
          ← Tableau de bord
        </Link>
      </div>

      {/* Mode actuel */}
      {currentSettings && !loading ? (
        <section className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Mode actuel</p>
          <p className="mt-1 text-lg font-black text-slate-900">
            {MODE_LABELS[currentSettings.forced_mode ?? ''] ?? (currentSettings.forced_mode ?? 'Libre')}
          </p>
          <div className="mt-2 flex flex-wrap gap-3 text-xs text-slate-600">
            <span className={`rounded-full px-2.5 py-0.5 font-bold ${currentSettings.gps_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'}`}>
              GPS : {currentSettings.gps_enabled ? 'Activé' : 'Désactivé'}
            </span>
            {currentSettings.latitude != null && currentSettings.longitude != null ? (
              <span className="rounded-full bg-security-light px-2.5 py-0.5 font-bold text-security-dark">
                {currentSettings.latitude.toFixed(4)}, {currentSettings.longitude.toFixed(4)}
              </span>
            ) : null}
            {currentSettings.radius != null ? (
              <span className="rounded-full bg-ia-light px-2.5 py-0.5 font-bold text-ia-dark">
                Rayon : {currentSettings.radius}m
              </span>
            ) : null}
          </div>
        </section>
      ) : null}

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {successMsg ? (
        <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
          <Check className="h-4 w-4" aria-hidden="true" /> {successMsg}
        </div>
      ) : null}

      {/* Formulaire */}
      <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
        <h2 className="mb-5 text-sm font-bold uppercase tracking-wider text-slate-800">
          Configuration
        </h2>

        {loading ? (
          <div className="space-y-4 animate-pulse">
            <div className="h-10 rounded-xl bg-slate-200" />
            <div className="h-10 rounded-xl bg-slate-200" />
          </div>
        ) : (
          <div className="space-y-5">
            {/* Mode forcé */}
            <div>
              <label className="block text-xs font-bold uppercase tracking-wider text-slate-500">
                Mode de pointage
              </label>
              <select
                className="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
                value={form.forced_mode}
                onChange={(e) => setForm((f) => ({ ...f, forced_mode: e.target.value }))}
                disabled={saving}
              >
                <option value="">Libre (pas de mode forcé)</option>
                <option value="gps_auto">GPS automatique</option>
                <option value="qr_code">QR Code</option>
                <option value="manual">Manuel</option>
              </select>
              <p className="mt-1 text-xs text-slate-500">
                &ldquo;Libre&rdquo; laisse l&apos;employé choisir la méthode disponible.
              </p>
            </div>

            {/* Toggle GPS */}
            <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div>
                <p className="text-sm font-bold text-slate-900">Géolocalisation GPS</p>
                <p className="text-xs text-slate-500">Activer la vérification de position</p>
              </div>
              <button
                type="button"
                role="switch"
                aria-checked={form.gps_enabled}
                onClick={() => setForm((f) => ({ ...f, gps_enabled: !f.gps_enabled }))}
                disabled={saving}
                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-security-light disabled:opacity-50 ${
                  form.gps_enabled ? 'bg-emerald-500' : 'bg-slate-300'
                }`}
              >
                <span
                  className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${
                    form.gps_enabled ? 'translate-x-6' : 'translate-x-1'
                  }`}
                />
              </button>
            </div>

            {/* Champs GPS conditionnels */}
            {form.gps_enabled ? (
              <div className="space-y-4 rounded-xl border border-security-light bg-security-light/40 p-4">
                <p className="text-xs font-bold uppercase tracking-wider text-security-dark">
                  Configuration du géofence
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label className="block text-xs font-bold text-slate-600">Latitude</label>
                    <input
                      type="number"
                      step="any"
                      className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
                      placeholder="ex: 48.8566"
                      value={form.latitude}
                      onChange={(e) => setForm((f) => ({ ...f, latitude: e.target.value }))}
                      disabled={saving}
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-slate-600">Longitude</label>
                    <input
                      type="number"
                      step="any"
                      className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
                      placeholder="ex: 2.3522"
                      value={form.longitude}
                      onChange={(e) => setForm((f) => ({ ...f, longitude: e.target.value }))}
                      disabled={saving}
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-600">Rayon (mètres)</label>
                  <input
                    type="number"
                    min={1}
                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
                    placeholder="ex: 200"
                    value={form.radius}
                    onChange={(e) => setForm((f) => ({ ...f, radius: e.target.value }))}
                    disabled={saving}
                  />
                  <p className="mt-1 text-xs text-slate-500">
                    Distance maximale autorisée depuis le lieu de travail.
                  </p>
                </div>
              </div>
            ) : null}

            {/* Save */}
            <div className="flex gap-3 pt-2">
              <button
                type="button"
                onClick={() => void handleSave()}
                disabled={saving || loading}
                className="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700 disabled:opacity-50"
              >
                {saving ? 'Enregistrement…' : 'Enregistrer'}
              </button>
              <button
                type="button"
                onClick={() => void loadSettings()}
                disabled={saving || loading}
                className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
              >
                Annuler
              </button>
            </div>
          </div>
        )}
      </section>
    </ModulePageShell>
  );
}
