'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Fuel, Gauge, Save } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { Card, Field, Modal, SelectInput, TextInput } from '../_components/fuel-ui';

/**
 * FUEL-013 (#5807) — Écran mobile pompiste : saisie rapide d'un relevé
 * compteur (station → pompe → compteur), avec shift, horodatage, feedback
 * delta et signalement d'anomalie.
 *
 * Connexion dégradée : chaque relevé porte une `idempotency_key` (uuid) —
 * le serveur rejoue la 1re réponse au lieu de dupliquer (FUEL batch A), et
 * apiFetch retry déjà 502/503/504. Accessibilité : cibles larges, labels,
 * aria, états de chargement/erreur explicites.
 */

type Station = { id: number; code: string; name: string; address?: string | null };
type Equipment = { kind: 'pump' | 'meter' | 'tank'; id: number; station_id: number; code: string; status?: string };
type ShiftAssignment = { id: number; shift_id: number; shift?: { id: number; name: string; start_time?: string; end_time?: string } | null };

function uuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `fuel-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

export default function FuelPumpPage() {
  const locale = getPreferredLocale();

  const [stations, setStations] = useState<Station[]>([]);
  const [equipment, setEquipment] = useState<Equipment[]>([]);
  const [shift, setShift] = useState<ShiftAssignment | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [stationId, setStationId] = useState<number | null>(null);
  const [pumpId, setPumpId] = useState<number | null>(null);
  const [meterId, setMeterId] = useState<number | null>(null);

  const [value, setValue] = useState('');
  const [capturedAt, setCapturedAt] = useState(() => new Date().toISOString().slice(0, 16));
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState<number | null>(null);
  const [delta, setDelta] = useState<number | null>(null);
  const [deltaLoading, setDeltaLoading] = useState(false);

  const [anomalyOpen, setAnomalyOpen] = useState(false);
  const [anomalyTitle, setAnomalyTitle] = useState('');
  const [anomalySeverity, setAnomalySeverity] = useState('low');
  const [anomalySaving, setAnomalySaving] = useState(false);
  const [anomalySaved, setAnomalySaved] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const today = new Date().toISOString().slice(0, 10);
      const [stationsRes, equipmentRes, shiftsRes] = await Promise.all([
        apiFetch('/fuel-station/stations?per_page=200').then((r) => r.json()).catch(() => null),
        apiFetch('/fuel-station/equipment?per_page=500').then((r) => r.json()).catch(() => null),
        apiFetch(`/fuel-station/me/shifts?date_from=${today}&date_to=${today}`).then((r) => r.json()).catch(() => null),
      ]);

      setStations(Array.isArray(stationsRes?.data) ? stationsRes.data : []);
      setEquipment(Array.isArray(equipmentRes?.data) ? equipmentRes.data : []);

      const assignments = Array.isArray(shiftsRes?.data) ? shiftsRes.data : [];
      const current = assignments.find(
        (a: ShiftAssignment) => a.shift_id != null,
      ) ?? null;
      setShift(current);
    } catch {
      setError(t(locale, 'fuel.pump.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const pumps = useMemo(
    () => equipment.filter((item) => item.kind === 'pump' && item.station_id === stationId),
    [equipment, stationId],
  );
  const meters = useMemo(
    () => equipment.filter((item) => item.kind === 'meter' && item.station_id === stationId),
    [equipment, stationId],
  );
  const selectedMeter = useMemo(
    () => meters.find((meter) => meter.id === meterId) ?? null,
    [meters, meterId],
  );

  const submit = async () => {
    if (stationId === null || pumpId === null || meterId === null || value.trim() === '') {
      return;
    }

    setSaving(true);
    setSaved(null);
    setDelta(null);
    try {
      const minor = Math.round(Number(value) * 100);
      await apiFetch(
        `/fuel-station/stations/${stationId}/pumps/${pumpId}/meters/${meterId}/readings`,
        {
          method: 'POST',
          body: JSON.stringify({
            reading_value_minor: minor,
            reading_unit: 'l',
            captured_at: capturedAt ? new Date(capturedAt).toISOString() : null,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
            shift_id: shift?.shift_id ?? null,
            device_reference: 'web-pompiste',
            idempotency_key: uuid(),
          }),
          _idempotent: true,
        },
      );
      setSaved(minor / 100);
      void loadDelta();
    } catch {
      setError(t(locale, 'fuel.pump.loadError'));
    } finally {
      setSaving(false);
    }
  };

  const loadDelta = async () => {
    if (stationId === null || pumpId === null || meterId === null) {
      return;
    }
    setDeltaLoading(true);
    try {
      const res = await apiFetch(
        `/fuel-station/stations/${stationId}/pumps/${pumpId}/meters/${meterId}/intervals?limit=1`,
        { _cacheBust: true },
      );
      const json = (await res.json()) as { data?: { delta_minor?: number }[] };
      const latest = json.data?.[0];
      setDelta(latest?.delta_minor != null ? latest.delta_minor / 100 : null);
    } catch {
      setDelta(null);
    } finally {
      setDeltaLoading(false);
    }
  };

  const submitAnomaly = async () => {
    if (stationId === null || anomalyTitle.trim() === '') {
      return;
    }
    setAnomalySaving(true);
    try {
      await apiFetch('/fuel-station/incidents', {
        method: 'POST',
        body: JSON.stringify({
          station_id: stationId,
          equipment_type: 'pump',
          equipment_id: pumpId ?? undefined,
          severity: anomalySeverity,
          title: anomalyTitle.trim(),
        }),
        _idempotent: true,
      });
      setAnomalySaved(true);
      setAnomalyTitle('');
      setAnomalyOpen(false);
    } catch {
      setError(t(locale, 'fuel.pump.loadError'));
    } finally {
      setAnomalySaving(false);
    }
  };

  const reset = () => {
    setStationId(null);
    setPumpId(null);
    setMeterId(null);
    setValue('');
    setSaved(null);
    setDelta(null);
    setAnomalySaved(false);
  };

  const step = stationId === null ? 'station' : pumpId === null ? 'pump' : meterId === null ? 'meter' : 'reading';

  return (
    <ModulePageShell
      title={t(locale, 'fuel.pump.title')}
      subtitle={t(locale, 'fuel.pump.subtitle')}
      accentClassName="border-amber-500/10 bg-amber-500/5"
    >
      <div className="space-y-4">
        {shift ? (
          <p className="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">
            <Fuel className="h-3.5 w-3.5" aria-hidden="true" />
            {t(locale, 'fuel.pump.shift')} : {shift.shift?.name ?? `#${shift.shift_id}`}
          </p>
        ) : (
          <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700">
            {t(locale, 'fuel.pump.noShift')}
          </p>
        )}

        {error ? (
          <div className="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center">
            <p className="text-sm font-bold text-rose-700">{error}</p>
            <Button variant="ghost" className="mt-3" onClick={() => void load()}>
              {t(locale, 'fuel.pump.retry')}
            </Button>
          </div>
        ) : loading ? (
          <Card className="py-10 text-center text-sm font-medium text-slate-500">{t(locale, 'edu.common.loading')}</Card>
        ) : stations.length === 0 ? (
          <Card className="py-10 text-center text-sm font-medium text-slate-500">{t(locale, 'fuel.pump.emptyStations')}</Card>
        ) : step === 'station' ? (
          <Card>
            <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'fuel.pump.selectStation')}</p>
            <div className="grid gap-2 sm:grid-cols-2">
              {stations.map((station) => (
                <button
                  key={station.id}
                  type="button"
                  onClick={() => setStationId(station.id)}
                  className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-left transition-colors hover:border-amber-300 hover:bg-amber-50/40"
                >
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600">
                    <Fuel className="h-5 w-5" aria-hidden="true" />
                  </div>
                  <div>
                    <p className="font-black tracking-tight text-slate-950">{station.name}</p>
                    <p className="font-mono text-xs font-bold text-slate-400">{station.code}</p>
                  </div>
                </button>
              ))}
            </div>
          </Card>
        ) : step === 'pump' ? (
          <Card>
            <button type="button" onClick={reset} className="mb-3 inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-slate-700">
              <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" /> {t(locale, 'fuel.pump.back')}
            </button>
            <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'fuel.pump.selectPump')}</p>
            <div className="grid gap-2 sm:grid-cols-2">
              {pumps.map((pump) => (
                <button
                  key={pump.id}
                  type="button"
                  onClick={() => setPumpId(pump.id)}
                  className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-left transition-colors hover:border-amber-300 hover:bg-amber-50/40"
                >
                  <Gauge className="h-5 w-5 text-amber-600" aria-hidden="true" />
                  <span className="font-bold text-slate-800">{pump.code}</span>
                </button>
              ))}
            </div>
          </Card>
        ) : step === 'meter' ? (
          <Card>
            <button type="button" onClick={() => setPumpId(null)} className="mb-3 inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-slate-700">
              <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" /> {t(locale, 'fuel.pump.back')}
            </button>
            <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'fuel.pump.selectMeter')}</p>
            <div className="grid gap-2 sm:grid-cols-2">
              {meters.map((meter) => (
                <button
                  key={meter.id}
                  type="button"
                  onClick={() => setMeterId(meter.id)}
                  className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-left transition-colors hover:border-amber-300 hover:bg-amber-50/40"
                >
                  <Gauge className="h-5 w-5 text-amber-600" aria-hidden="true" />
                  <span className="font-bold text-slate-800">{meter.code}</span>
                </button>
              ))}
            </div>
          </Card>
        ) : (
          <Card>
            <button type="button" onClick={() => setMeterId(null)} className="mb-3 inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-slate-700">
              <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" /> {t(locale, 'fuel.pump.back')}
            </button>

            <div className="mb-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm">
              <p className="font-black tracking-tight text-slate-900">{selectedMeter?.code}</p>
              <p className="font-mono text-xs text-slate-400">
                {t(locale, 'fuel.pump.selectStation')} → {stations.find((s) => s.id === stationId)?.name}
              </p>
            </div>

            {saved !== null ? (
              <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700" role="status">
                <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                {t(locale, 'fuel.pump.saved')} ({saved.toFixed(2)} l)
              </div>
            ) : null}

            {delta !== null ? (
              <p className="mb-4 rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-bold text-cyan-700">
                {t(locale, 'fuel.pump.delta')} : {delta.toFixed(2)} l
              </p>
            ) : deltaLoading ? (
              <p className="mb-4 text-xs text-slate-400">{t(locale, 'edu.common.loading')}</p>
            ) : saved !== null ? (
              <p className="mb-4 text-xs text-slate-400">{t(locale, 'fuel.pump.noDelta')}</p>
            ) : null}

            <div className="space-y-4">
              <Field label={t(locale, 'fuel.pump.reading')} hint={t(locale, 'fuel.pump.unitHint')}>
                <TextInput
                  type="number"
                  inputMode="decimal"
                  min={0}
                  step="0.01"
                  value={value}
                  onChange={(e) => setValue(e.target.value)}
                  placeholder="0.00"
                  aria-label={t(locale, 'fuel.pump.reading')}
                  className="text-2xl font-black"
                />
              </Field>
              <Field label={t(locale, 'fuel.pump.capturedAt')}>
                <TextInput
                  type="datetime-local"
                  value={capturedAt}
                  onChange={(e) => setCapturedAt(e.target.value)}
                  aria-label={t(locale, 'fuel.pump.capturedAt')}
                />
              </Field>

              <p className="text-xs text-slate-400">{t(locale, 'fuel.pump.offlineHint')}</p>

              <div className="flex flex-wrap gap-2">
                <Button onClick={() => void submit()} disabled={saving || value.trim() === ''}>
                  <Save className="h-4 w-4" aria-hidden="true" />
                  {saving ? t(locale, 'fuel.pump.submitting') : t(locale, 'fuel.pump.submit')}
                </Button>
                <Button variant="ghost" onClick={() => { setAnomalySaved(false); setAnomalyOpen(true); }}>
                  <AlertTriangle className="h-4 w-4" aria-hidden="true" />
                  {t(locale, 'fuel.pump.anomaly')}
                </Button>
              </div>
            </div>
          </Card>
        )}

        <Modal open={anomalyOpen} title={t(locale, 'fuel.pump.anomaly')} onClose={() => setAnomalyOpen(false)}>
          <div className="space-y-4">
            <Field label={t(locale, 'fuel.pump.anomalyTitle')}>
              <TextInput value={anomalyTitle} onChange={(e) => setAnomalyTitle(e.target.value)} required aria-label={t(locale, 'fuel.pump.anomalyTitle')} />
            </Field>
            <Field label={t(locale, 'fuel.pump.anomalySeverity')}>
              <SelectInput value={anomalySeverity} onChange={(e) => setAnomalySeverity(e.target.value)} aria-label={t(locale, 'fuel.pump.anomalySeverity')}>
                <option value="low">{t(locale, 'fuel.pump.low')}</option>
                <option value="medium">{t(locale, 'fuel.pump.medium')}</option>
                <option value="high">{t(locale, 'fuel.pump.high')}</option>
                <option value="critical">{t(locale, 'fuel.pump.critical')}</option>
              </SelectInput>
            </Field>
            {anomalySaved ? (
              <p className="rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700">{t(locale, 'fuel.pump.anomalySaved')}</p>
            ) : null}
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="ghost" onClick={() => setAnomalyOpen(false)}>{t(locale, 'edu.common.cancel')}</Button>
              <Button onClick={() => void submitAnomaly()} disabled={anomalySaving || anomalyTitle.trim() === ''}>
                {t(locale, 'fuel.pump.anomaly')}
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </ModulePageShell>
  );
}
