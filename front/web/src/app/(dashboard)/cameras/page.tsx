'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertCircle, Camera as CameraIcon, Copy, Link2, Loader2, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import {
  CAMERA_SHARE_DURATIONS,
  buildCameraViewerUrl,
  createCamera,
  createCameraAccessToken,
  deleteCamera,
  isRtspUrl,
  listCameraAccessLogs,
  listCameraAccessTokens,
  listCameraPermissions,
  listCameras,
  revokeCameraAccessToken,
  testRtspSource,
  type CameraAccessLog,
  type CameraAccessToken,
  type CameraPermission,
  type CameraPlanLimit,
  type CameraStream,
  type RtspTestResult,
} from '@/lib/cameras';

/**
 * #7425 (tranche 1) — surface client du module Caméras.
 *
 * Ce qui est livré : l'inventaire (liste, ajout avec URL RTSP, test de la
 * source, suppression), la limite de plan et la consultation des **accès**
 * (autorisations individuelles + journal) qui touchent à la vie privée.
 *
 * Ce qui n'est **pas** livré ici, et pourquoi : le **direct** (mur de caméras,
 * viewer temps réel) exige la chaîne vidéo décidée en ADR-0021 — un **nœud
 * Edge installé chez le client**. Tant qu'aucun flux n'est joignable, la page
 * l'annonce (bandeau) au lieu d'afficher un lecteur mort : c'est le seul
 * comportement honnête (cf. #7477, « une interface de caméras sans chaîne
 * vidéo serait un mensonge d'interface »). Le viewer tiers HTML (#7425 §3) et
 * l'audit a11y complet restent à faire.
 */

type CopyKey =
  | 'title'
  | 'subtitle'
  | 'loading'
  | 'loadError'
  | 'retry'
  | 'planUsed'
  | 'planMax'
  | 'planUnlimited'
  | 'edgeTitle'
  | 'edgeBody'
  | 'listTitle'
  | 'empty'
  | 'addTitle'
  | 'addCta'
  | 'adding'
  | 'fieldName'
  | 'fieldRtsp'
  | 'fieldRtspHint'
  | 'fieldLocation'
  | 'testCta'
  | 'testing'
  | 'testOk'
  | 'testFailed'
  | 'deleteCta'
  | 'deleting'
  | 'confirmDelete'
  | 'cancel'
  | 'accessesCta'
  | 'accessesTitle'
  | 'permissionsTitle'
  | 'permissionsEmpty'
  | 'permissionsView'
  | 'permissionsNoView'
  | 'permissionsManage'
  | 'logsTitle'
  | 'logsEmpty'
  | 'active'
  | 'inactive'
  | 'invalidRtsp'
  | 'genericError'
  | 'close'
  | 'shareCta'
  | 'shareCreateTitle'
  | 'shareLabel'
  | 'shareDuration'
  | 'shareCreate'
  | 'shareCreating'
  | 'shareLinksTitle'
  | 'shareEmpty'
  | 'shareCopy'
  | 'shareCopied'
  | 'shareRevoke'
  | 'shareRevoking'
  | 'shareExpires'
  | 'shareRevoked'
  | 'shareLinkHint'
  | 'shareDuration60'
  | 'shareDuration1440'
  | 'shareDuration10080'
  | 'shareDuration43200';

const COPY_KEYS: CopyKey[] = [
  'title', 'subtitle', 'loading', 'loadError', 'retry', 'planUsed', 'planMax', 'planUnlimited',
  'edgeTitle', 'edgeBody', 'listTitle', 'empty', 'addTitle', 'addCta', 'adding', 'fieldName',
  'fieldRtsp', 'fieldRtspHint', 'fieldLocation', 'testCta', 'testing', 'testOk', 'testFailed',
  'deleteCta', 'deleting', 'confirmDelete', 'cancel', 'accessesCta', 'accessesTitle',
  'permissionsTitle', 'permissionsEmpty', 'permissionsView', 'permissionsNoView', 'permissionsManage',
  'logsTitle', 'logsEmpty', 'active', 'inactive',
  'invalidRtsp', 'genericError', 'close',
  'shareCta', 'shareCreateTitle', 'shareLabel', 'shareDuration', 'shareCreate', 'shareCreating',
  'shareLinksTitle', 'shareEmpty', 'shareCopy', 'shareCopied', 'shareRevoke', 'shareRevoking',
  'shareExpires', 'shareRevoked', 'shareLinkHint', 'shareDuration60', 'shareDuration1440',
  'shareDuration10080', 'shareDuration43200',
];

function buildCopy(locale: AppLocale): Record<CopyKey, string> {
  const copy = {} as Record<CopyKey, string>;
  for (const key of COPY_KEYS) {
    copy[key] = t(locale, `cameras.${key}`);
  }
  return copy;
}

const inputClass =
  'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';

export default function CamerasModulePage() {
  const locale = getPreferredLocale();
  const c = useMemo(() => buildCopy(locale), [locale]);

  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [cameras, setCameras] = useState<CameraStream[]>([]);
  const [planLimit, setPlanLimit] = useState<CameraPlanLimit | null>(null);

  const [form, setForm] = useState({ name: '', rtsp_url: '', location: '' });
  const [formError, setFormError] = useState('');

  const [formTest, setFormTest] = useState<RtspTestResult | null>(null);
  const [permissions, setPermissions] = useState<Record<number, CameraPermission[]>>({});
  const [logs, setLogs] = useState<Record<number, CameraAccessLog[]>>({});
  const [openAccess, setOpenAccess] = useState<number | null>(null);
  const [shareOpen, setShareOpen] = useState<number | null>(null);
  const [shareTokens, setShareTokens] = useState<Record<number, CameraAccessToken[]>>({});
  const [shareForm, setShareForm] = useState<{ label: string; minutes: number }>({ label: '', minutes: 60 });
  const [copiedId, setCopiedId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const payload = await listCameras();
      setCameras(payload.cameras);
      setPlanLimit(payload.planLimit);
    } catch {
      setError(c.loadError);
    } finally {
      setLoading(false);
    }
  }, [c.loadError]);

  useEffect(() => {
    void load();
  }, [load]);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setFormError('');

    if (!isRtspUrl(form.rtsp_url)) {
      setFormError(c.invalidRtsp);
      return;
    }

    setBusy('create');
    try {
      const created = await createCamera({
        name: form.name.trim(),
        rtsp_url: form.rtsp_url.trim(),
        ...(form.location.trim() ? { location: form.location.trim() } : {}),
      });
      setCameras((current) => [...current, created]);
      setPlanLimit((current) => (current ? { ...current, current_count: current.current_count + 1 } : current));
      setForm({ name: '', rtsp_url: '', location: '' });
    } catch {
      setFormError(c.genericError);
    } finally {
      setBusy(null);
    }
  };

  const remove = async (camera: CameraStream) => {
    if (!window.confirm(`${c.confirmDelete}\n${camera.name}`)) {
      return;
    }
    setBusy(`delete-${camera.id}`);
    try {
      await deleteCamera(camera.id);
      setCameras((current) => current.filter((item) => item.id !== camera.id));
      setPlanLimit((current) => (current ? { ...current, current_count: Math.max(0, current.current_count - 1) } : current));
    } catch {
      setError(c.genericError);
    } finally {
      setBusy(null);
    }
  };

  /**
   * Test de la source RTSP. Volontairement placé **dans le formulaire** :
   * l'API ne renvoie jamais l'URL RTSP d'une caméra existante (elle est
   * chiffrée au repos), on teste donc l'adresse que l'on s'apprête à
   * enregistrer — c'est le moment utile (avant d'enregistrer une caméra
   * injoignable).
   */
  const testFormSource = async () => {
    if (!isRtspUrl(form.rtsp_url)) {
      setFormError(c.invalidRtsp);
      setFormTest(null);
      return;
    }
    setFormError('');
    setBusy('test');
    try {
      setFormTest(await testRtspSource(form.rtsp_url.trim()));
    } finally {
      setBusy(null);
    }
  };

  const openAccesses = async (camera: CameraStream) => {
    if (openAccess === camera.id) {
      setOpenAccess(null);
      return;
    }
    setOpenAccess(camera.id);
    if (permissions[camera.id] && logs[camera.id]) {
      return;
    }
    setBusy(`access-${camera.id}`);
    try {
      const [perms, entries] = await Promise.all([
        listCameraPermissions(camera.id),
        listCameraAccessLogs(camera.id),
      ]);
      setPermissions((current) => ({ ...current, [camera.id]: perms }));
      setLogs((current) => ({ ...current, [camera.id]: entries }));
    } catch {
      setError(c.genericError);
    } finally {
      setBusy(null);
    }
  };

  /**
   * #7425 (tranche 2) — ouvre le panneau de partage d'une caméra et charge ses
   * liens existants (l'API ne renvoie le jeton brut qu'à la création : un lien
   * déjà créé ne peut pas être réaffiché, il doit être régénéré).
   */
  const openShare = async (camera: CameraStream) => {
    if (shareOpen === camera.id) {
      setShareOpen(null);
      return;
    }
    setShareOpen(camera.id);
    setCopiedId(null);
    setBusy(`share-${camera.id}`);
    try {
      const tokens = await listCameraAccessTokens(camera.id);
      setShareTokens((current) => ({ ...current, [camera.id]: tokens }));
    } catch {
      setError(c.genericError);
    } finally {
      setBusy(null);
    }
  };

  const createShareLink = async (camera: CameraStream) => {
    setBusy(`share-create-${camera.id}`);
    try {
      const created = await createCameraAccessToken(camera.id, {
        ...(shareForm.label.trim() ? { label: shareForm.label.trim() } : {}),
        expires_in_minutes: shareForm.minutes,
      });
      setShareTokens((current) => ({ ...current, [camera.id]: [created, ...(current[camera.id] ?? [])] }));
      setShareForm({ label: '', minutes: 60 });

      // Le lien est copiable immédiatement : c'est le seul moment où le jeton
      // est disponible en clair.
      const link = created.share_url
        ?? (created.token ? buildCameraViewerUrl(window.location.origin, created.token) : '');
      if (link) {
        await navigator.clipboard?.writeText(link).catch(() => undefined);
        setCopiedId(created.id);
      }
    } catch {
      setError(c.genericError);
    } finally {
      setBusy(null);
    }
  };

  const revokeShareLink = async (camera: CameraStream, tokenId: number) => {
    setBusy(`share-revoke-${tokenId}`);
    try {
      await revokeCameraAccessToken(camera.id, tokenId);
      setShareTokens((current) => ({
        ...current,
        [camera.id]: (current[camera.id] ?? []).map((token) =>
          token.id === tokenId ? { ...token, is_revoked: true } : token,
        ),
      }));
    } catch {
      setError(c.genericError);
    } finally {
      setBusy(null);
    }
  };

  const durationLabel = (minutes: number): string => {
    switch (minutes) {
      case 60: return c.shareDuration60;
      case 1440: return c.shareDuration1440;
      case 10080: return c.shareDuration10080;
      case 43200: return c.shareDuration43200;
      default: return `${minutes} min`;
    }
  };

  const renderShare = (camera: CameraStream) => (
    <div className="mt-3 space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4" data-testid={`camera-share-panel-${camera.id}`}>
      <p className="text-[11px] font-black uppercase tracking-widest text-slate-500">{c.shareCreateTitle}</p>

      <div className="grid gap-3 sm:grid-cols-3">
        <label className="block">
          <span className="text-xs font-bold text-slate-600">{c.shareLabel}</span>
          <input
            className={inputClass}
            value={shareForm.label}
            maxLength={150}
            onChange={(event) => setShareForm({ ...shareForm, label: event.target.value })}
            data-testid={`camera-share-label-${camera.id}`}
          />
        </label>
        <label className="block">
          <span className="text-xs font-bold text-slate-600">{c.shareDuration}</span>
          <select
            className={inputClass}
            value={shareForm.minutes}
            onChange={(event) => setShareForm({ ...shareForm, minutes: Number(event.target.value) })}
            data-testid={`camera-share-duration-${camera.id}`}
          >
            {CAMERA_SHARE_DURATIONS.map((minutes) => (
              <option key={minutes} value={minutes}>{durationLabel(minutes)}</option>
            ))}
          </select>
        </label>
        <div className="flex items-end">
          <button
            type="button"
            disabled={busy !== null}
            onClick={() => void createShareLink(camera)}
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-60"
            data-testid={`camera-share-create-${camera.id}`}
          >
            {busy === `share-create-${camera.id}` ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <Link2 className="h-4 w-4" aria-hidden="true" />
            )}
            {busy === `share-create-${camera.id}` ? c.shareCreating : c.shareCreate}
          </button>
        </div>
      </div>

      <p className="text-[11px] text-slate-500" data-testid="camera-share-hint">{c.shareLinkHint}</p>
      {copiedId !== null && (shareTokens[camera.id] ?? []).some((token) => token.id === copiedId) ? (
        <p className="text-xs font-bold text-emerald-700" data-testid="camera-share-copied">{c.shareCopied}</p>
      ) : null}

      <div>
        <p className="text-xs font-bold text-slate-700">{c.shareLinksTitle}</p>
        {(shareTokens[camera.id] ?? []).length === 0 ? (
          <p className="mt-1 text-xs text-slate-500" data-testid={`camera-share-empty-${camera.id}`}>{c.shareEmpty}</p>
        ) : (
          <ul className="mt-1 space-y-1 text-xs text-slate-600">
            {(shareTokens[camera.id] ?? []).map((token) => (
              <li key={token.id} className="flex flex-wrap items-center gap-x-3 gap-y-1" data-testid={`camera-share-link-${token.id}`}>
                <span className="font-bold">{token.label ?? `#${token.id}`}</span>
                {token.expires_at ? <span>{c.shareExpires} {token.expires_at}</span> : null}
                {token.is_revoked ? (
                  <span className="rounded-lg border border-slate-200 bg-slate-100 px-2 py-0.5 font-black uppercase">{c.shareRevoked}</span>
                ) : (
                  <button
                    type="button"
                    disabled={busy !== null}
                    onClick={() => void revokeShareLink(camera, token.id)}
                    className="rounded-lg border border-red-200 px-2 py-0.5 font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-60"
                    data-testid={`camera-share-revoke-${token.id}`}
                  >
                    {busy === `share-revoke-${token.id}` ? c.shareRevoking : c.shareRevoke}
                  </button>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );

  const renderAccesses = (camera: CameraStream) => (
    <div className="mt-3 space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
      <p className="text-[11px] font-black uppercase tracking-widest text-slate-500">{c.accessesTitle}</p>

      <div>
        <p className="text-xs font-bold text-slate-700">{c.permissionsTitle}</p>
        {(permissions[camera.id] ?? []).length === 0 ? (
          <p className="mt-1 text-xs text-slate-500">{c.permissionsEmpty}</p>
        ) : (
          <ul className="mt-1 space-y-1 text-xs text-slate-600">
            {(permissions[camera.id] ?? []).map((permission) => (
              <li key={permission.id} className="flex flex-wrap items-center gap-x-2">
                <span>#{permission.employee_id}</span>
                <span>{permission.can_view ? c.permissionsView : c.permissionsNoView}</span>
                {permission.can_manage ? <span>{c.permissionsManage}</span> : null}
                {permission.expires_at ? <span>{permission.expires_at}</span> : null}
              </li>
            ))}
          </ul>
        )}
      </div>

      <div>
        <p className="text-xs font-bold text-slate-700">{c.logsTitle}</p>
        {(logs[camera.id] ?? []).length === 0 ? (
          <p className="mt-1 text-xs text-slate-500">{c.logsEmpty}</p>
        ) : (
          <ul className="mt-1 space-y-1 text-xs text-slate-600">
            {(logs[camera.id] ?? []).slice(0, 10).map((entry) => (
              <li key={entry.id}>
                {entry.created_at ?? '—'} — {entry.action}
                {entry.actor_type ? ` (${entry.actor_type})` : ''}
                {entry.ip_address ? ` ${entry.ip_address}` : ''}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );

  const max = planLimit?.max_cameras ?? null;

  return (
    <ModulePageShell title={c.title} subtitle={c.subtitle} icon={CameraIcon}>
      {loading ? (
        <p className="flex items-center gap-2 text-sm text-slate-500" data-testid="cameras-loading">
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
          {c.loading}
        </p>
      ) : null}

      {error ? (
        <div
          role="alert"
          className="flex items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
          data-testid="cameras-error"
        >
          <span className="flex items-center gap-2">
            <AlertCircle className="h-4 w-4" aria-hidden="true" />
            {error}
          </span>
          <button type="button" onClick={() => void load()} className="font-bold underline">
            {c.retry}
          </button>
        </div>
      ) : null}

      {planLimit ? (
        <div className="flex flex-wrap gap-4 text-xs font-bold text-slate-600" data-testid="cameras-plan-limit">
          <span>
            {c.planUsed} : {planLimit.current_count}
          </span>
          <span>
            {c.planMax} : {max === null ? c.planUnlimited : max}
          </span>
        </div>
      ) : null}

      {/* État de la chaîne vidéo : dit la vérité plutôt que d'afficher un lecteur mort. */}
      <section
        className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        data-testid="cameras-edge-notice"
      >
        <p className="font-bold">{c.edgeTitle}</p>
        <p className="mt-1 text-[13px] leading-relaxed">{c.edgeBody}</p>
      </section>

      <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
        <h2 className="text-sm font-black uppercase tracking-widest text-slate-500">{c.listTitle}</h2>

        {cameras.length === 0 && !loading ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="cameras-empty">
            {c.empty}
          </p>
        ) : (
          <ul className="mt-3 divide-y divide-slate-100">
            {cameras.map((camera) => (
              <li key={camera.id} className="py-3" data-testid={`camera-row-${camera.id}`}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-bold text-slate-800">
                      {camera.name}
                      <span
                        className={`ms-2 rounded-lg border px-2 py-0.5 text-[10px] font-black uppercase ${
                          camera.is_active
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-slate-200 bg-slate-100 text-slate-500'
                        }`}
                      >
                        {camera.is_active ? c.active : c.inactive}
                      </span>
                    </p>
                    {camera.location ? <p className="text-xs text-slate-500">{camera.location}</p> : null}
                    <p className="mt-1 truncate text-[11px] text-slate-400">{camera.stream_url}</p>
                  </div>

                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={() => void openShare(camera)}
                      className="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                      data-testid={`camera-share-${camera.id}`}
                    >
                      <Link2 className="h-3.5 w-3.5" aria-hidden="true" />
                      {shareOpen === camera.id ? c.close : c.shareCta}
                    </button>
                    <button
                      type="button"
                      onClick={() => void openAccesses(camera)}
                      className="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                      data-testid={`camera-accesses-${camera.id}`}
                    >
                      {openAccess === camera.id ? c.close : c.accessesCta}
                    </button>
                    <button
                      type="button"
                      disabled={busy !== null}
                      onClick={() => void remove(camera)}
                      className="inline-flex items-center gap-1 rounded-xl border border-red-200 px-3 py-1.5 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-60"
                      data-testid={`camera-delete-${camera.id}`}
                    >
                      <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                      {busy === `delete-${camera.id}` ? c.deleting : c.deleteCta}
                    </button>
                  </div>
                </div>

                {shareOpen === camera.id ? renderShare(camera) : null}
                {openAccess === camera.id ? renderAccesses(camera) : null}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
        <h2 className="text-sm font-black uppercase tracking-widest text-slate-500">{c.addTitle}</h2>

        <form className="mt-4 space-y-4" onSubmit={(event) => void submit(event)}>
          <div className="grid gap-4 sm:grid-cols-3">
            <label className="block">
              <span className="text-xs font-bold text-slate-600">{c.fieldName}</span>
              <input
                className={inputClass}
                value={form.name}
                required
                maxLength={100}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
                data-testid="camera-field-name"
              />
            </label>
            <label className="block">
              <span className="text-xs font-bold text-slate-600">{c.fieldRtsp}</span>
              <input
                className={inputClass}
                value={form.rtsp_url}
                required
                placeholder="rtsp://192.168.1.20:554/stream"
                onChange={(event) => setForm({ ...form, rtsp_url: event.target.value })}
                data-testid="camera-field-rtsp"
              />
            </label>
            <label className="block">
              <span className="text-xs font-bold text-slate-600">{c.fieldLocation}</span>
              <input
                className={inputClass}
                value={form.location}
                maxLength={200}
                onChange={(event) => setForm({ ...form, location: event.target.value })}
                data-testid="camera-field-location"
              />
            </label>
          </div>

          <p className="text-[11px] text-slate-500">{c.fieldRtspHint}</p>

          {formError ? (
            <p role="alert" className="text-xs font-bold text-red-600" data-testid="camera-form-error">
              {formError}
            </p>
          ) : null}

          {formTest ? (
            <p
              className={`text-xs font-bold ${formTest.ok ? 'text-emerald-700' : 'text-red-600'}`}
              data-testid="camera-form-test"
            >
              {formTest.ok ? c.testOk : c.testFailed}
              {formTest.message ? ` — ${formTest.message}` : ''}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={busy !== null}
            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60"
            data-testid="camera-submit"
          >
            {busy === 'create' ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <Plus className="h-4 w-4" aria-hidden="true" />
            )}
            {busy === 'create' ? c.adding : c.addCta}
          </button>

          <button
            type="button"
            disabled={busy !== null}
            onClick={() => void testFormSource()}
            className="ms-2 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60"
            data-testid="camera-test-source"
          >
            {busy === 'test' ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <ShieldCheck className="h-4 w-4" aria-hidden="true" />
            )}
            {busy === 'test' ? c.testing : c.testCta}
          </button>
        </form>
      </section>
    </ModulePageShell>
  );
}
