'use client';

/**
 * #8499 (I-12) — Écran d'enrôlement / révocation d'appareils (côté tenant).
 *
 * Le NOYAU (`lib/device/device-pairing.ts`) et le BACKEND tenant
 * (`GET/POST /api/v1/edge`, `POST /{nodeId}/sync`, `DELETE` via révocation)
 * existaient déjà ; il manquait l'ÉCRAN qui les relie pour un client.
 *
 * Exigences de la mission (Partie 6) rendues visibles ici :
 *  - enrôlement sécurisé : le jeton (`edge_token`) n'est affiché qu'UNE SEULE
 *    FOIS, à la création, avec un avertissement explicite — jamais re-montré ;
 *  - affectation à un établissement (site) et à un poste/fonction (rôle) ;
 *  - révocation d'appareil ;
 *  - état de synchronisation (en ligne / dernière synchro) ;
 *  - AUCUN secret réutilisable persisté dans l'interface.
 *
 * Aucun texte en dur : tout vient du catalogue i18n (`devices.*`, ×4 locales).
 */

import { useCallback, useEffect, useState } from 'react';
import { Copy, Loader2, MonitorSmartphone, Plus, ShieldAlert, Trash2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type EdgeNode = {
  id: string;
  name: string;
  site_address?: string | null;
  mode?: string | null;
  is_online?: boolean;
  license_valid?: boolean;
  last_sync_at?: string | null;
};

type EnrollmentResult = {
  data: EdgeNode;
  edge_token: string;
  install_command: string;
};

const ROLES = ['rolePos', 'roleKds', 'roleField', 'roleKiosk'] as const;

export default function DevicesSettingsPage() {
  const locale = getPreferredLocale();
  const [nodes, setNodes] = useState<EdgeNode[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [acting, setActing] = useState(false);

  const [name, setName] = useState('');
  const [site, setSite] = useState('');
  const [role, setRole] = useState<(typeof ROLES)[number]>('rolePos');
  const [enrollment, setEnrollment] = useState<EnrollmentResult | null>(null);
  const [copied, setCopied] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/edge', { _cacheBust: true });
      if (!res.ok) throw new Error('load_failed');
      const payload = (await res.json()) as { data: EdgeNode[] };
      setNodes(payload.data ?? []);
    } catch {
      setError(t(locale, 'devices.error'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const enroll = useCallback(async () => {
    if (name.trim() === '') return;
    setActing(true);
    setError(null);
    try {
      const res = await apiFetch('/edge', {
        method: 'POST',
        body: JSON.stringify({
          name: name.trim(),
          site_address: site.trim() === '' ? null : site.trim(),
          mode: 'hybrid',
          capabilities: [role],
        }),
      });
      if (!res.ok) throw new Error('enroll_failed');
      const payload = (await res.json()) as EnrollmentResult;
      setEnrollment(payload);
      setName('');
      setSite('');
      await load();
    } catch {
      setError(t(locale, 'devices.error'));
    } finally {
      setActing(false);
    }
  }, [name, site, role, load, locale]);

  const revoke = useCallback(
    async (nodeId: string) => {
      if (!window.confirm(t(locale, 'devices.revokeConfirm'))) return;
      setActing(true);
      try {
        const res = await apiFetch(`/edge/${nodeId}`, { method: 'DELETE' });
        if (!res.ok) throw new Error('revoke_failed');
        await load();
      } catch {
        setError(t(locale, 'devices.error'));
      } finally {
        setActing(false);
      }
    },
    [load, locale],
  );

  const copyCommand = useCallback(async () => {
    if (!enrollment) return;
    try {
      await navigator.clipboard.writeText(enrollment.install_command);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      /* clipboard indisponible : l'utilisateur peut sélectionner le texte */
    }
  }, [enrollment]);

  return (
    <ModulePageShell
      icon={MonitorSmartphone}
      title={t(locale, 'devices.title')}
      description={t(locale, 'devices.subtitle')}
    >
      {error ? (
        <p className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>
      ) : null}

      {/* Formulaire d'enrôlement */}
      <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="mb-4 text-lg font-bold text-slate-900">{t(locale, 'devices.enroll')}</h2>
        <div className="grid gap-3 sm:grid-cols-3">
          <label className="text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'devices.name')}</span>
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={t(locale, 'devices.namePlaceholder')}
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'devices.establishment')}</span>
            <input
              value={site}
              onChange={(e) => setSite(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'devices.role')}</span>
            <select
              value={role}
              onChange={(e) => setRole(e.target.value as (typeof ROLES)[number])}
              className="w-full rounded-lg border border-slate-300 px-3 py-2"
            >
              {ROLES.map((r) => (
                <option key={r} value={r}>
                  {t(locale, `devices.${r}`)}
                </option>
              ))}
            </select>
          </label>
        </div>
        <button
          type="button"
          onClick={() => void enroll()}
          disabled={acting || name.trim() === ''}
          className="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
        >
          {acting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
          {t(locale, 'devices.create')}
        </button>
      </section>

      {/* Résultat d'enrôlement : jeton affiché UNE SEULE FOIS */}
      {enrollment ? (
        <section className="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
          <div className="mb-2 flex items-center gap-2 text-amber-900">
            <ShieldAlert className="h-5 w-5" />
            <h2 className="text-lg font-bold">{t(locale, 'devices.codeTitle')}</h2>
          </div>
          <p className="mb-3 text-sm text-amber-900">{t(locale, 'devices.secretWarning')}</p>
          <p className="mb-1 text-xs font-semibold uppercase text-amber-800">{t(locale, 'devices.installCommand')}</p>
          <div className="flex items-start gap-2">
            <code className="flex-1 overflow-x-auto rounded-lg bg-slate-900 px-3 py-2 text-xs text-emerald-200">
              {enrollment.install_command}
            </code>
            <button
              type="button"
              onClick={() => void copyCommand()}
              className="inline-flex items-center gap-1 rounded-lg border border-amber-400 px-3 py-2 text-xs font-bold text-amber-900"
            >
              <Copy className="h-3.5 w-3.5" />
              {copied ? t(locale, 'devices.copied') : t(locale, 'devices.copy')}
            </button>
          </div>
          <p className="mt-2 text-xs text-amber-800">{t(locale, 'devices.installHint')}</p>
        </section>
      ) : null}

      {/* Liste des appareils */}
      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="mb-4 text-lg font-bold text-slate-900">{t(locale, 'devices.devices')}</h2>
        {loading ? (
          <p className="text-sm text-slate-500">{t(locale, 'devices.loading')}</p>
        ) : nodes.length === 0 ? (
          <p className="text-sm text-slate-500">{t(locale, 'devices.empty')}</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {nodes.map((node) => (
              <li key={node.id} className="flex items-center justify-between gap-3 py-3">
                <div>
                  <p className="font-semibold text-slate-900">{node.name}</p>
                  <p className="text-xs text-slate-500">
                    {node.site_address ?? '—'} ·{' '}
                    {node.is_online ? t(locale, 'devices.online') : t(locale, 'devices.offline')} ·{' '}
                    {t(locale, 'devices.lastSync')}: {node.last_sync_at ?? t(locale, 'devices.never')}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => void revoke(node.id)}
                  disabled={acting}
                  className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  {t(locale, 'devices.revoke')}
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>
    </ModulePageShell>
  );
}
