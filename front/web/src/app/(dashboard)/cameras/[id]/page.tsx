'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { ArrowLeft, Check, Clock, Copy, KeyRound, Link2, RefreshCw, ScrollText, ShieldCheck, Trash2, Video } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { Card, Checkbox, Field, Notice, SelectInput, TextInput } from '../_components/cameras-ui';

/**
 * BC-19 (#7425) — détail d'une caméra : flux (jeton signé), permissions
 * internes, jetons d'accès tiers (création + révocation) et journal d'accès
 * (fenêtre 30 jours). Contrats API consommés tels quels :
 *   - `GET /cameras/{id}`             → métadonnées ;
 *   - `GET /cameras/{id}/stream-token` → `stream_url` + `stream_token` signés ;
 *   - `GET/POST/DELETE /cameras/{id}/permissions[/{permission}]` (responsable) ;
 *   - `GET/POST/DELETE /cameras/{id}/access-tokens[/{token}]` ;
 *   - `GET /cameras/{id}/access-logs?per_page=20` (30 jours glissants).
 */

type Camera = {
  id: number;
  name: string;
  location?: string | null;
  is_active?: boolean;
  sort_order?: number;
  stream_url?: string | null;
  stream_token?: string | null;
  token_expires_at?: string | null;
  created_at?: string | null;
};

type Permission = {
  id: number;
  employee_id: number;
  can_view?: boolean;
  can_share?: boolean;
  can_manage?: boolean;
  expires_at?: string | null;
};

type AccessToken = {
  id: number;
  label?: string | null;
  granted_to_name?: string | null;
  granted_to_email?: string | null;
  expires_at?: string | null;
  last_used_at?: string | null;
  use_count?: number;
  is_revoked?: boolean;
  created_at?: string | null;
  token?: string | null;
  share_url?: string | null;
};

type AccessLog = {
  id: number;
  employee_id?: number | null;
  access_token_id?: number | null;
  actor_type?: string | null;
  action?: string | null;
  ip_address?: string | null;
  created_at?: string | null;
};

const TOKEN_DURATIONS: { minutes: number; key: string }[] = [
  { minutes: 60, key: 'cameras.detail.tokenDuration1h' },
  { minutes: 360, key: 'cameras.detail.tokenDuration6h' },
  { minutes: 1440, key: 'cameras.detail.tokenDuration24h' },
  { minutes: 10080, key: 'cameras.detail.tokenDuration7d' },
  { minutes: 43200, key: 'cameras.detail.tokenDuration30d' },
];

function actorKey(actorType?: string | null): string {
  if (actorType === 'external_token') {
    return 'cameras.detail.logActorToken';
  }
  if (actorType === 'employee') {
    return 'cameras.detail.logActorEmployee';
  }
  return 'cameras.detail.logActorSystem';
}

async function copyText(value: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(value);
    return true;
  } catch {
    return false;
  }
}

export default function CameraDetailPage() {
  const locale = getPreferredLocale();
  const params = useParams<{ id: string }>();
  const cameraId = Number(params?.id);

  const [camera, setCamera] = useState<Camera | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [streamLoading, setStreamLoading] = useState(false);
  const [stream, setStream] = useState<{ stream_url?: string | null; stream_token?: string | null; token_expires_at?: string | null } | null>(null);

  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [permissionsRestricted, setPermissionsRestricted] = useState(false);
  const [permissionsLoaded, setPermissionsLoaded] = useState(false);
  const [employeeId, setEmployeeId] = useState('');
  const [canView, setCanView] = useState(true);
  const [canShare, setCanShare] = useState(false);
  const [canManage, setCanManage] = useState(false);
  const [granting, setGranting] = useState(false);
  const [permissionMessage, setPermissionMessage] = useState<string | null>(null);
  const [permissionError, setPermissionError] = useState<string | null>(null);

  const [tokens, setTokens] = useState<AccessToken[]>([]);
  const [tokensLoaded, setTokensLoaded] = useState(false);
  const [tokenLabel, setTokenLabel] = useState('');
  const [tokenName, setTokenName] = useState('');
  const [tokenEmail, setTokenEmail] = useState('');
  const [tokenDuration, setTokenDuration] = useState('60');
  const [tokenCreating, setTokenCreating] = useState(false);
  const [newToken, setNewToken] = useState<AccessToken | null>(null);
  const [tokenMessage, setTokenMessage] = useState<string | null>(null);
  const [tokenError, setTokenError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const [logs, setLogs] = useState<AccessLog[]>([]);
  const [logsLoaded, setLogsLoaded] = useState(false);
  const [logsError, setLogsError] = useState(false);

  const loadCamera = useCallback(async () => {
    if (!Number.isFinite(cameraId)) {
      setNotFound(true);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    setNotFound(false);
    try {
      const res = await apiFetch(`/cameras/${cameraId}`, { _cacheBust: true });
      const json = (await res.json()) as { data?: Camera };
      if (!json.data) {
        setNotFound(true);
      } else {
        setCamera(json.data);
      }
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) {
        setNotFound(true);
      } else {
        setError(t(locale, 'cameras.detail.loadError'));
      }
    } finally {
      setLoading(false);
    }
  }, [cameraId, locale]);

  const loadStreamToken = useCallback(async () => {
    if (!Number.isFinite(cameraId)) {
      return;
    }

    setStreamLoading(true);
    try {
      const res = await apiFetch(`/cameras/${cameraId}/stream-token`, { _cacheBust: true });
      const json = (await res.json()) as { data?: typeof stream };
      setStream(json.data ?? null);
    } catch {
      setStream(null);
    } finally {
      setStreamLoading(false);
    }
  }, [cameraId]);

  const loadPermissions = useCallback(async () => {
    if (!Number.isFinite(cameraId)) {
      return;
    }

    setPermissionsLoaded(false);
    setPermissionsRestricted(false);
    try {
      const res = await apiFetch(`/cameras/${cameraId}/permissions`, { _cacheBust: true });
      const json = (await res.json()) as { data?: Permission[] };
      setPermissions(Array.isArray(json.data) ? json.data : []);
    } catch (err) {
      if (err instanceof ApiError && err.status === 403) {
        setPermissionsRestricted(true);
      } else {
        setPermissions([]);
      }
    } finally {
      setPermissionsLoaded(true);
    }
  }, [cameraId]);

  const loadTokens = useCallback(async () => {
    if (!Number.isFinite(cameraId)) {
      return;
    }

    setTokensLoaded(false);
    try {
      const res = await apiFetch(`/cameras/${cameraId}/access-tokens`, { _cacheBust: true });
      const json = (await res.json()) as { data?: AccessToken[] };
      setTokens(Array.isArray(json.data) ? json.data : []);
    } catch {
      setTokens([]);
    } finally {
      setTokensLoaded(true);
    }
  }, [cameraId]);

  const loadLogs = useCallback(async () => {
    if (!Number.isFinite(cameraId)) {
      return;
    }

    setLogsLoaded(false);
    setLogsError(false);
    try {
      const res = await apiFetch(`/cameras/${cameraId}/access-logs?per_page=20`, { _cacheBust: true });
      const json = (await res.json()) as { data?: AccessLog[] };
      setLogs(Array.isArray(json.data) ? json.data : []);
    } catch {
      setLogsError(true);
    } finally {
      setLogsLoaded(true);
    }
  }, [cameraId]);

  useEffect(() => {
    void loadCamera();
  }, [loadCamera]);

  useEffect(() => {
    if (!camera) {
      return;
    }
    void loadStreamToken();
    void loadPermissions();
    void loadTokens();
    void loadLogs();
  }, [camera, loadStreamToken, loadPermissions, loadTokens, loadLogs]);

  const grant = async () => {
    const target = Number(employeeId);
    if (!Number.isInteger(target) || target < 1) {
      setPermissionError(t(locale, 'cameras.detail.employeeId'));
      return;
    }

    setGranting(true);
    setPermissionMessage(null);
    setPermissionError(null);
    try {
      await apiFetch(`/cameras/${cameraId}/permissions`, {
        method: 'POST',
        body: JSON.stringify({
          employee_id: target,
          can_view: canView,
          can_share: canShare,
          can_manage: canManage,
        }),
        _idempotent: true,
      });
      setPermissionMessage(t(locale, 'cameras.detail.granted'));
      setEmployeeId('');
      await loadPermissions();
      await loadLogs();
    } catch (err) {
      setPermissionError(
        err instanceof ApiError && err.status === 403
          ? t(locale, 'cameras.detail.permissionsRestricted')
          : t(locale, 'cameras.detail.grantError'),
      );
    } finally {
      setGranting(false);
    }
  };

  const revokePermission = async (permissionId: number) => {
    setPermissionMessage(null);
    setPermissionError(null);
    try {
      await apiFetch(`/cameras/${cameraId}/permissions/${permissionId}`, { method: 'DELETE' });
      setPermissionMessage(t(locale, 'cameras.detail.permissionRevoked'));
      await loadPermissions();
      await loadLogs();
    } catch {
      setPermissionError(t(locale, 'cameras.detail.revokeError'));
    }
  };

  const createToken = async () => {
    setTokenCreating(true);
    setTokenMessage(null);
    setTokenError(null);
    setCopied(false);
    try {
      const res = await apiFetch(`/cameras/${cameraId}/access-tokens`, {
        method: 'POST',
        body: JSON.stringify({
          label: tokenLabel.trim() === '' ? null : tokenLabel.trim(),
          granted_to_name: tokenName.trim() === '' ? null : tokenName.trim(),
          granted_to_email: tokenEmail.trim() === '' ? null : tokenEmail.trim(),
          expires_in_minutes: Number(tokenDuration),
          permissions: { view: true },
        }),
        _idempotent: true,
      });
      const json = (await res.json()) as { data?: AccessToken };
      setNewToken(json.data ?? null);
      setTokenMessage(t(locale, 'cameras.detail.tokenCreated'));
      setTokenLabel('');
      setTokenName('');
      setTokenEmail('');
      await loadTokens();
      await loadLogs();
    } catch {
      setTokenError(t(locale, 'cameras.detail.tokenCreateError'));
    } finally {
      setTokenCreating(false);
    }
  };

  const revokeToken = async (tokenId: number) => {
    setTokenMessage(null);
    setTokenError(null);
    try {
      await apiFetch(`/cameras/${cameraId}/access-tokens/${tokenId}`, { method: 'DELETE' });
      setTokenMessage(t(locale, 'cameras.detail.tokenRevoked'));
      if (newToken?.id === tokenId) {
        setNewToken(null);
      }
      await loadTokens();
      await loadLogs();
    } catch {
      setTokenError(t(locale, 'cameras.detail.tokenRevokeError'));
    }
  };

  const shareUrlFor = (token: AccessToken): string | null => {
    if (token.share_url) {
      return token.share_url;
    }
    if (!token.token || typeof window === 'undefined') {
      return null;
    }
    return `${window.location.origin}/view/cam?t=${token.token}`;
  };

  const formatDate = (value?: string | null): string =>
    value ? new Date(value).toLocaleString(locale) : '';

  if (notFound) {
    return (
      <ModulePageShell title={t(locale, 'cameras.detail.notFound')} accentClassName="border-rose-500/10 bg-rose-500/5">
        <Card className="space-y-3 text-center">
          <p className="text-sm text-slate-600">{t(locale, 'cameras.detail.loadError')}</p>
          <Link href="/cameras" className="inline-flex items-center gap-1 text-sm font-bold text-emerald-700">
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'cameras.detail.back')}
          </Link>
        </Card>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell
      title={camera?.name ?? t(locale, 'cameras.title')}
      subtitle={camera?.location ?? t(locale, 'cameras.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
    >
      <div className="space-y-5">
        <Link href="/cameras" className="inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-slate-700">
          <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
          {t(locale, 'cameras.detail.back')}
        </Link>

        {error ? <Notice tone="error">{error}</Notice> : null}

        {loading ? (
          <Card className="py-10 text-center text-sm font-medium text-slate-500">{t(locale, 'cameras.loading')}</Card>
        ) : (
          <>
            <Card className="space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-500">
                  <Video className="h-4 w-4 text-emerald-700" aria-hidden="true" />
                  {t(locale, 'cameras.detail.stream')}
                </h2>
                <Button variant="ghost" size="sm" onClick={() => void loadStreamToken()} disabled={streamLoading}>
                  <RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />
                  {t(locale, 'cameras.retry')}
                </Button>
              </div>

              <Notice tone="warning">
                <span className="block font-black">{t(locale, 'cameras.detail.streamUnavailable')}</span>
                <span className="block font-medium">{t(locale, 'cameras.detail.streamUnavailableHint')}</span>
              </Notice>

              <dl className="space-y-3 text-sm">
                <div>
                  <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.detail.streamUrl')}</dt>
                  <dd className="font-mono text-xs break-all text-slate-800">{stream?.stream_url ?? ''}</dd>
                </div>
                <div>
                  <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.detail.streamToken')}</dt>
                  <dd className="font-mono text-xs break-all text-slate-800">{stream?.stream_token ?? ''}</dd>
                </div>
                <div>
                  <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.detail.tokenExpiresAt')}</dt>
                  <dd className="text-slate-800">{formatDate(stream?.token_expires_at)}</dd>
                </div>
                {camera?.created_at ? (
                  <div>
                    <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.detail.createdAt')}</dt>
                    <dd className="text-slate-800">{formatDate(camera.created_at)}</dd>
                  </div>
                ) : null}
              </dl>
              <p className="text-xs text-slate-400">{t(locale, 'cameras.detail.webrtcHint')}</p>
            </Card>

            <Card className="space-y-4">
              <h2 className="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-500">
                <ShieldCheck className="h-4 w-4 text-emerald-700" aria-hidden="true" />
                {t(locale, 'cameras.detail.permissionsTitle')}
              </h2>

              {permissionsRestricted ? (
                <Notice tone="warning">{t(locale, 'cameras.detail.permissionsRestricted')}</Notice>
              ) : (
                <>
                  <p className="text-xs text-slate-400">{t(locale, 'cameras.detail.permissionsHint')}</p>
                  {permissionsLoaded && permissions.length === 0 ? (
                    <p className="text-sm text-slate-500">{t(locale, 'cameras.detail.permissionsEmpty')}</p>
                  ) : (
                    <ul className="divide-y divide-slate-100">
                      {permissions.map((permission) => (
                        <li key={permission.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                          <span className="font-mono text-xs font-bold text-slate-700">
                            #{permission.employee_id}
                          </span>
                          <span className="flex flex-wrap items-center gap-1.5 text-[11px] font-bold">
                            {permission.can_view ? <span className="rounded-md bg-emerald-50 px-2 py-0.5 text-emerald-700">{t(locale, 'cameras.detail.canView')}</span> : null}
                            {permission.can_share ? <span className="rounded-md bg-cyan-50 px-2 py-0.5 text-cyan-700">{t(locale, 'cameras.detail.canShare')}</span> : null}
                            {permission.can_manage ? <span className="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">{t(locale, 'cameras.detail.canManage')}</span> : null}
                          </span>
                          <Button variant="ghost" size="sm" onClick={() => void revokePermission(permission.id)}>
                            <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                            {t(locale, 'cameras.detail.revoke')}
                          </Button>
                        </li>
                      ))}
                    </ul>
                  )}

                  <div className="grid gap-3 sm:grid-cols-2">
                    <Field label={t(locale, 'cameras.detail.employeeId')}>
                      <TextInput
                        type="number"
                        min={1}
                        value={employeeId}
                        onChange={(e) => setEmployeeId(e.target.value)}
                        aria-label={t(locale, 'cameras.detail.employeeId')}
                      />
                    </Field>
                    <div className="flex flex-wrap items-end gap-4 pb-2">
                      <Checkbox label={t(locale, 'cameras.detail.canView')} checked={canView} onChange={(e) => setCanView(e.target.checked)} />
                      <Checkbox label={t(locale, 'cameras.detail.canShare')} checked={canShare} onChange={(e) => setCanShare(e.target.checked)} />
                      <Checkbox label={t(locale, 'cameras.detail.canManage')} checked={canManage} onChange={(e) => setCanManage(e.target.checked)} />
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button onClick={() => void grant()} disabled={granting}>
                      {granting ? t(locale, 'cameras.detail.granting') : t(locale, 'cameras.detail.grantPermission')}
                    </Button>
                    {permissionMessage ? <span className="text-xs font-bold text-emerald-700">{permissionMessage}</span> : null}
                    {permissionError ? <span className="text-xs font-bold text-rose-700">{permissionError}</span> : null}
                  </div>
                </>
              )}
            </Card>

            <Card className="space-y-4">
              <h2 className="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-500">
                <KeyRound className="h-4 w-4 text-emerald-700" aria-hidden="true" />
                {t(locale, 'cameras.detail.tokensTitle')}
              </h2>
              <p className="text-xs text-slate-400">{t(locale, 'cameras.detail.tokensHint')}</p>

              {tokensLoaded && tokens.length === 0 ? (
                <p className="text-sm text-slate-500">{t(locale, 'cameras.detail.tokensEmpty')}</p>
              ) : (
                <ul className="divide-y divide-slate-100">
                  {tokens.map((token) => (
                    <li key={token.id} className="space-y-1 py-3 text-sm">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="font-bold text-slate-800">
                          {token.label ?? `#${token.id}`}
                        </span>
                        <span className="flex items-center gap-2">
                          {token.is_revoked ? (
                            <span className="rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-black uppercase text-rose-700">
                              {t(locale, 'cameras.detail.tokenRevoked')}
                            </span>
                          ) : null}
                          <Button variant="ghost" size="sm" onClick={() => void revokeToken(token.id)}>
                            <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                            {t(locale, 'cameras.detail.tokenRevoke')}
                          </Button>
                        </span>
                      </div>
                      <p className="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <span className="inline-flex items-center gap-1">
                          <Clock className="h-3.5 w-3.5" aria-hidden="true" />
                          {t(locale, 'cameras.detail.tokenExpiresAt')} : {formatDate(token.expires_at)}
                        </span>
                        <span>
                          {t(locale, 'cameras.detail.tokenUseCount')} : {token.use_count ?? 0}
                        </span>
                        <span>
                          {t(locale, 'cameras.detail.tokenLastUsed')} : {token.last_used_at ? formatDate(token.last_used_at) : t(locale, 'cameras.detail.tokenNeverUsed')}
                        </span>
                      </p>
                    </li>
                  ))}
                </ul>
              )}

              <div className="grid gap-3 sm:grid-cols-2">
                <Field label={t(locale, 'cameras.detail.tokenLabel')}>
                  <TextInput value={tokenLabel} onChange={(e) => setTokenLabel(e.target.value)} aria-label={t(locale, 'cameras.detail.tokenLabel')} />
                </Field>
                <Field label={t(locale, 'cameras.detail.tokenRecipient')}>
                  <TextInput value={tokenName} onChange={(e) => setTokenName(e.target.value)} aria-label={t(locale, 'cameras.detail.tokenRecipient')} />
                </Field>
                <Field label={t(locale, 'cameras.detail.tokenEmail')}>
                  <TextInput type="email" value={tokenEmail} onChange={(e) => setTokenEmail(e.target.value)} aria-label={t(locale, 'cameras.detail.tokenEmail')} />
                </Field>
                <Field label={t(locale, 'cameras.detail.tokenDuration')}>
                  <SelectInput value={tokenDuration} onChange={(e) => setTokenDuration(e.target.value)} aria-label={t(locale, 'cameras.detail.tokenDuration')}>
                    {TOKEN_DURATIONS.map((duration) => (
                      <option key={duration.minutes} value={String(duration.minutes)}>
                        {t(locale, duration.key)}
                      </option>
                    ))}
                  </SelectInput>
                </Field>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <Button onClick={() => void createToken()} disabled={tokenCreating}>
                  <Link2 className="h-4 w-4" aria-hidden="true" />
                  {tokenCreating ? t(locale, 'cameras.detail.tokenCreating') : t(locale, 'cameras.detail.tokenCreate')}
                </Button>
                {tokenMessage ? <span className="text-xs font-bold text-emerald-700">{tokenMessage}</span> : null}
                {tokenError ? <span className="text-xs font-bold text-rose-700">{tokenError}</span> : null}
              </div>

              {newToken ? (
                <div className="space-y-2 rounded-2xl bg-slate-50 p-4">
                  <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.detail.tokenShareUrl')}</p>
                  <p className="font-mono text-xs break-all text-slate-800">{shareUrlFor(newToken) ?? ''}</p>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => void copyText(shareUrlFor(newToken) ?? '').then((ok) => setCopied(ok))}
                    >
                      {copied ? <Check className="h-3.5 w-3.5" aria-hidden="true" /> : <Copy className="h-3.5 w-3.5" aria-hidden="true" />}
                      {copied ? t(locale, 'cameras.detail.tokenCopied') : t(locale, 'cameras.detail.tokenCopy')}
                    </Button>
                    <span className="text-xs text-slate-500">{t(locale, 'cameras.detail.tokenValueWarning')}</span>
                  </div>
                  <p className="text-xs text-slate-400">{t(locale, 'cameras.detail.tokenRevocationHint')}</p>
                </div>
              ) : null}
            </Card>

            <Card className="space-y-4">
              <h2 className="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-500">
                <ScrollText className="h-4 w-4 text-emerald-700" aria-hidden="true" />
                {t(locale, 'cameras.detail.logsTitle')}
              </h2>
              <p className="text-xs text-slate-400">{t(locale, 'cameras.detail.logsHint')}</p>

              {logsError ? (
                <Notice tone="error">{t(locale, 'cameras.detail.logsLoadError')}</Notice>
              ) : logsLoaded && logs.length === 0 ? (
                <p className="text-sm text-slate-500">{t(locale, 'cameras.detail.logsEmpty')}</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <th className="py-2 pr-3">{t(locale, 'cameras.detail.logDate')}</th>
                        <th className="py-2 pr-3">{t(locale, 'cameras.detail.logAction')}</th>
                        <th className="py-2 pr-3">{t(locale, 'cameras.detail.logActor')}</th>
                        <th className="py-2 pr-3">{t(locale, 'cameras.detail.logIp')}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {logs.map((log) => (
                        <tr key={log.id}>
                          <td className="py-2 pr-3 text-slate-700">{formatDate(log.created_at)}</td>
                          <td className="py-2 pr-3 font-bold text-slate-800">{log.action ?? ''}</td>
                          <td className="py-2 pr-3 text-slate-600">
                            {t(locale, actorKey(log.actor_type))}
                            {log.employee_id ? ` #${log.employee_id}` : ''}
                          </td>
                          <td className="py-2 pr-3 font-mono text-slate-500">{log.ip_address ?? ''}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
              <p className="text-xs text-slate-400">{t(locale, 'cameras.detail.logsRetention')}</p>
            </Card>
          </>
        )}
      </div>
    </ModulePageShell>
  );
}
