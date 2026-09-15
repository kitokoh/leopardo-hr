'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { Cctv, MapPin, Plus, RefreshCw, ShieldAlert } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { Card, Field, Modal, Notice, TextInput } from './_components/cameras-ui';

/**
 * BC-19 (#7425) — mur de caméras de l'espace client.
 *
 * Le backend caméras est complet (CRUD `/cameras`, `POST /cameras/test-rtsp`,
 * viewer public `/view/cam`) mais aucune page ne l'exposait : cet écran est le
 * point d'entrée. Il reflète le contrat réel de l'API :
 *   - 403 `FEATURE_NOT_ENABLED` (middleware `module.cameras`) ⇒ message dédié,
 *     jamais un mur vide ;
 *   - `plan_limit` (max_cameras / current_count) affiché tel quel ;
 *   - test RTSP : `ok:false` + `error`/`message` en 200, 408 (timeout) et
 *     503 (ffprobe indisponible) sont distingués à l'écran.
 */

type Camera = {
  id: number;
  name: string;
  location?: string | null;
  is_active?: boolean;
  sort_order?: number;
  stream_url?: string | null;
};

type PlanLimit = { max_cameras?: number | null; current_count?: number | null };

const RTSP_TEST_KEYS: Record<string, string> = {
  RTSP_TIMEOUT: 'cameras.testTimeout',
  VIDEO_PROXY_UNAVAILABLE: 'cameras.testUnavailable',
  host_not_allowed: 'cameras.testHostForbidden',
  invalid_url: 'cameras.testInvalidUrl',
  RTSP_CONNECTION_FAILED: 'cameras.testFailed',
};

function testFailureKey(code?: string, status?: number): string {
  if (code && RTSP_TEST_KEYS[code]) {
    return RTSP_TEST_KEYS[code];
  }
  if (status === 408) {
    return 'cameras.testTimeout';
  }
  if (status === 503) {
    return 'cameras.testUnavailable';
  }
  return 'cameras.testFailed';
}

export default function CamerasPage() {
  const locale = getPreferredLocale();

  const [cameras, setCameras] = useState<Camera[]>([]);
  const [planLimit, setPlanLimit] = useState<PlanLimit | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [forbidden, setForbidden] = useState(false);

  const [addOpen, setAddOpen] = useState(false);
  const [name, setName] = useState('');
  const [rtspUrl, setRtspUrl] = useState('');
  const [location, setLocation] = useState('');
  const [sortOrder, setSortOrder] = useState('0');
  const [streamPathOverride, setStreamPathOverride] = useState('');
  const [creating, setCreating] = useState(false);
  const [created, setCreated] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ tone: 'success' | 'error' | 'warning'; key: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    setForbidden(false);
    try {
      const res = await apiFetch('/cameras?per_page=100', { _cacheBust: true });
      const json = (await res.json()) as { data?: Camera[]; plan_limit?: PlanLimit };
      setCameras(Array.isArray(json.data) ? json.data : []);
      setPlanLimit(json.plan_limit ?? null);
    } catch (err) {
      if (err instanceof ApiError && err.status === 403) {
        setForbidden(true);
      } else {
        setError(t(locale, 'cameras.loadError'));
      }
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const resetForm = () => {
    setName('');
    setRtspUrl('');
    setLocation('');
    setSortOrder('0');
    setStreamPathOverride('');
    setCreateError(null);
    setCreated(false);
    setTestResult(null);
  };

  const submit = async () => {
    if (name.trim() === '' || rtspUrl.trim() === '') {
      setCreateError(t(locale, 'cameras.requiredFields'));
      return;
    }

    setCreating(true);
    setCreateError(null);
    try {
      await apiFetch('/cameras', {
        method: 'POST',
        body: JSON.stringify({
          name: name.trim(),
          rtsp_url: rtspUrl.trim(),
          location: location.trim() === '' ? null : location.trim(),
          sort_order: Number.isFinite(Number(sortOrder)) ? Number(sortOrder) : 0,
          stream_path_override: streamPathOverride.trim() === '' ? null : streamPathOverride.trim(),
        }),
        _idempotent: true,
      });
      setCreated(true);
      await load();
    } catch {
      setCreateError(t(locale, 'cameras.createError'));
    } finally {
      setCreating(false);
    }
  };

  const testRtsp = async () => {
    if (rtspUrl.trim() === '') {
      return;
    }

    setTesting(true);
    setTestResult(null);
    try {
      const res = await apiFetch('/cameras/test-rtsp', {
        method: 'POST',
        body: JSON.stringify({ rtsp_url: rtspUrl.trim() }),
        _idempotent: false,
      });
      const json = (await res.json()) as { ok?: boolean; error?: string; skipped?: boolean };
      if (json.ok === false) {
        setTestResult({ tone: 'error', key: testFailureKey(json.error, res.status) });
      } else if (json.skipped === true) {
        setTestResult({ tone: 'warning', key: 'cameras.testSkipped' });
      } else {
        setTestResult({ tone: 'success', key: 'cameras.testOk' });
      }
    } catch (err) {
      const apiError = err instanceof ApiError ? err : null;
      setTestResult({ tone: 'error', key: testFailureKey(apiError?.code, apiError?.status) });
    } finally {
      setTesting(false);
    }
  };

  return (
    <ModulePageShell
      title={t(locale, 'cameras.title')}
      subtitle={t(locale, 'cameras.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
    >
      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          {planLimit && planLimit.max_cameras !== null && planLimit.max_cameras !== undefined ? (
            <p className="rounded-xl border border-slate-200 bg-white/70 px-3 py-1.5 text-xs font-bold text-slate-600">
              {t(locale, 'cameras.planLimit')} : {planLimit.current_count ?? cameras.length} / {planLimit.max_cameras}
            </p>
          ) : (
            <span />
          )}
          <Button onClick={() => { resetForm(); setAddOpen(true); }}>
            <Plus className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'cameras.addCamera')}
          </Button>
        </div>

        {created ? <Notice tone="success">{t(locale, 'cameras.created')}</Notice> : null}

        {forbidden ? (
          <Card className="space-y-2 text-center">
            <ShieldAlert className="mx-auto h-8 w-8 text-amber-500" aria-hidden="true" />
            <p className="text-sm font-black text-slate-900">{t(locale, 'cameras.featureLockedTitle')}</p>
            <p className="text-sm text-slate-600">{t(locale, 'cameras.featureLockedBody')}</p>
          </Card>
        ) : error ? (
          <Card className="space-y-3 text-center">
            <p className="text-sm font-bold text-rose-700">{error}</p>
            <Button variant="ghost" onClick={() => void load()}>
              <RefreshCw className="h-4 w-4" aria-hidden="true" />
              {t(locale, 'cameras.retry')}
            </Button>
          </Card>
        ) : loading ? (
          <Card className="py-10 text-center text-sm font-medium text-slate-500">{t(locale, 'cameras.loading')}</Card>
        ) : cameras.length === 0 ? (
          <Card className="space-y-2 py-10 text-center">
            <Cctv className="mx-auto h-8 w-8 text-slate-300" aria-hidden="true" />
            <p className="text-sm font-black text-slate-900">{t(locale, 'cameras.empty')}</p>
            <p className="text-sm text-slate-500">{t(locale, 'cameras.emptyHint')}</p>
          </Card>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {cameras.map((camera) => (
              <li key={camera.id}>
                <Link
                  href={`/cameras/${camera.id}`}
                  className="flex h-full flex-col gap-3 rounded-2xl border border-slate-200/60 bg-white/80 p-5 shadow-sm backdrop-blur-xl transition-colors hover:border-emerald-300 hover:bg-emerald-50/40"
                >
                  <span className="flex items-center gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-700">
                      <Cctv className="h-5 w-5" aria-hidden="true" />
                    </span>
                    <span className="min-w-0">
                      <span className="block truncate font-black tracking-tight text-slate-950">{camera.name}</span>
                      <span className="block font-mono text-[11px] font-bold text-slate-400">#{camera.id}</span>
                    </span>
                  </span>
                  {camera.location ? (
                    <span className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                      <MapPin className="h-3.5 w-3.5" aria-hidden="true" />
                      {camera.location}
                    </span>
                  ) : null}
                  <span className="mt-auto flex items-center justify-between text-xs font-bold">
                    <span className={camera.is_active === false ? 'text-slate-400' : 'text-emerald-700'}>
                      {camera.is_active === false ? t(locale, 'cameras.statusInactive') : t(locale, 'cameras.statusActive')}
                    </span>
                    <span className="text-slate-500">{t(locale, 'cameras.open')}</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}

        <Modal
          open={addOpen}
          title={t(locale, 'cameras.addTitle')}
          closeLabel={t(locale, 'cameras.cancel')}
          onClose={() => setAddOpen(false)}
        >
          <div className="space-y-4">
            <Field label={t(locale, 'cameras.name')}>
              <TextInput value={name} onChange={(e) => setName(e.target.value)} aria-label={t(locale, 'cameras.name')} />
            </Field>
            <Field label={t(locale, 'cameras.rtspUrl')} hint={t(locale, 'cameras.rtspHint')}>
              <TextInput
                value={rtspUrl}
                onChange={(e) => { setRtspUrl(e.target.value); setTestResult(null); }}
                aria-label={t(locale, 'cameras.rtspUrl')}
              />
            </Field>
            <Field label={t(locale, 'cameras.location')}>
              <TextInput value={location} onChange={(e) => setLocation(e.target.value)} aria-label={t(locale, 'cameras.location')} />
            </Field>
            <Field label={t(locale, 'cameras.sortOrder')}>
              <TextInput
                type="number"
                min={0}
                max={9999}
                value={sortOrder}
                onChange={(e) => setSortOrder(e.target.value)}
                aria-label={t(locale, 'cameras.sortOrder')}
              />
            </Field>
            <Field label={t(locale, 'cameras.streamPathOverride')} hint={t(locale, 'cameras.streamPathOverrideHint')}>
              <TextInput
                value={streamPathOverride}
                onChange={(e) => setStreamPathOverride(e.target.value)}
                aria-label={t(locale, 'cameras.streamPathOverride')}
              />
            </Field>

            {testResult ? <Notice tone={testResult.tone}>{t(locale, testResult.key)}</Notice> : null}
            {createError ? <Notice tone="error">{createError}</Notice> : null}

            <div className="flex flex-wrap justify-end gap-2 pt-2">
              <Button variant="ghost" onClick={() => void testRtsp()} disabled={testing || rtspUrl.trim() === ''}>
                {testing ? t(locale, 'cameras.testing') : t(locale, 'cameras.testRtsp')}
              </Button>
              <Button variant="ghost" onClick={() => setAddOpen(false)}>
                {t(locale, 'cameras.cancel')}
              </Button>
              <Button onClick={() => void submit()} disabled={creating}>
                {creating ? t(locale, 'cameras.creating') : t(locale, 'cameras.create')}
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </ModulePageShell>
  );
}
