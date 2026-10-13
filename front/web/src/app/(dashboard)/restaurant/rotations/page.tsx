'use client';

/**
 * Issue #8431 — page « Rotations » de l'espace restaurateur.
 *
 * L'API existait déjà (module Planning/HR : `ScheduleController` —
 * `GET/POST /schedules`, `PUT/DELETE /schedules/{id}`,
 * `POST /schedules/{id}/assign-employees`, modèle
 * `App\Modules\Planning\Domain\Models\Schedule`) mais AUCUNE surface web ne
 * l'exposait : un restaurateur pouvait ajouter ses employés sans pouvoir
 * gérer leurs rotations. Cette page branche le CRUD des rotations et
 * l'affectation des employés sur l'API réelle, dans des rotations.
 */
import { useCallback, useEffect, useState } from 'react';
import { CalendarClock } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** `ScheduleResource` (Planning) — sous-ensemble consommé par la page. */
type Schedule = {
  id: number;
  name: string;
  start_time: string | null;
  end_time: string | null;
  break_minutes: number | null;
};

type EmployeeOption = { id: number; label: string };

export default function RestaurantRotationsPage() {
  const locale = getPreferredLocale();
  const [schedules, setSchedules] = useState<Schedule[]>([]);
  const [employees, setEmployees] = useState<EmployeeOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [formId, setFormId] = useState('');
  const [formName, setFormName] = useState('');
  const [formStart, setFormStart] = useState('09:00');
  const [formEnd, setFormEnd] = useState('17:00');
  const [formBreak, setFormBreak] = useState('60');
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [assignSelections, setAssignSelections] = useState<Record<number, string>>({});
  const [assigningId, setAssigningId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/schedules?per_page=200');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: Schedule[] };
      setSchedules(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'restaurant.rotations.loadError', 'Impossible de charger les rotations.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  // Référentiel employés du tenant (même pattern que restaurant/team).
  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const res = await apiFetch('/employees?per_page=200');
        if (!res.ok) return;
        const payload = (await res.json()) as {
          data?: { id: number; first_name?: string | null; last_name?: string | null; name?: string | null }[];
        };
        if (cancelled) return;
        setEmployees(
          (Array.isArray(payload.data) ? payload.data : []).map((employee) => ({
            id: employee.id,
            label:
              [employee.first_name, employee.last_name].filter(Boolean).join(' ') ||
              employee.name ||
              String(employee.id),
          })),
        );
      } catch {
        // référentiel optionnel : la liste des rotations prime.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const readApiError = async (res: Response): Promise<string | null> => {
    try {
      const payload = (await res.json()) as { message?: string };
      return typeof payload.message === 'string' && payload.message !== '' ? payload.message : null;
    } catch {
      return null;
    }
  };

  const resetForm = () => {
    setFormId('');
    setFormName('');
    setFormStart('09:00');
    setFormEnd('17:00');
    setFormBreak('60');
    setFormError('');
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    if (formName.trim() === '' || formStart === '' || formEnd === '') {
      setFormError(t(locale, 'restaurant.rotations.formIncomplete', 'Renseignez un nom et des horaires valides.'));
      return;
    }
    setSaving(true);
    const body = JSON.stringify({
      name: formName.trim(),
      start_time: formStart,
      end_time: formEnd,
      break_minutes: Number(formBreak) || 0,
    });
    try {
      const res =
        formId === ''
          ? await apiFetch('/schedules', { method: 'POST', body })
          : await apiFetch(`/schedules/${formId}`, { method: 'PUT', body });
      if (!res.ok) {
        setFormError(
          (await readApiError(res)) ??
            t(locale, 'restaurant.rotations.saveFailed', 'L’enregistrement a échoué.'),
        );
        return;
      }
      resetForm();
      await load();
    } catch {
      setFormError(t(locale, 'restaurant.rotations.saveFailed', 'L’enregistrement a échoué.'));
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (schedule: Schedule) => {
    setFormId(String(schedule.id));
    setFormName(schedule.name ?? '');
    setFormStart((schedule.start_time ?? '').slice(0, 5));
    setFormEnd((schedule.end_time ?? '').slice(0, 5));
    setFormBreak(String(schedule.break_minutes ?? 0));
    setFormError('');
  };

  const remove = async (schedule: Schedule) => {
    if (!window.confirm(t(locale, 'restaurant.rotations.confirmRemove', 'Supprimer cette rotation ?'))) {
      return;
    }
    setError('');
    try {
      const res = await apiFetch(`/schedules/${schedule.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'restaurant.rotations.removeFailed', 'La suppression a échoué.'));
    }
  };

  const assign = async (schedule: Schedule) => {
    const employeeId = assignSelections[schedule.id];
    if (!employeeId) {
      setError(t(locale, 'restaurant.rotations.formIncomplete', 'Renseignez un nom et des horaires valides.'));
      return;
    }
    setAssigningId(schedule.id);
    setError('');
    try {
      const res = await apiFetch(`/schedules/${schedule.id}/assign-employees`, {
        method: 'POST',
        body: JSON.stringify({ employee_ids: [Number(employeeId)] }),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setAssignSelections((prev) => ({ ...prev, [schedule.id]: '' }));
    } catch {
      setError(t(locale, 'restaurant.rotations.assignFailed', 'L’affectation a échoué.'));
    } finally {
      setAssigningId(null);
    }
  };

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none';

  return (
    <ModulePageShell
      icon={CalendarClock}
      title={t(locale, 'restaurant.rotations.title', 'Rotations')}
      description={t(locale, 'restaurant.rotations.subtitle')}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {formId === ''
            ? t(locale, 'restaurant.rotations.createTitle', 'Nouvelle rotation')
            : t(locale, 'restaurant.rotations.editTitle', 'Modifier la rotation')}
        </h2>
        <form onSubmit={submit} className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'restaurant.rotations.name', 'Nom')}
            </span>
            <input
              type="text"
              value={formName}
              onChange={(e) => setFormName(e.target.value)}
              placeholder={t(locale, 'restaurant.rotations.namePlaceholder', 'Matin, soir, week-end…')}
              maxLength={100}
              className={inputClass}
              data-testid="rotation-name"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'restaurant.rotations.startTime', 'Début')}
            </span>
            <input
              type="time"
              value={formStart}
              onChange={(e) => setFormStart(e.target.value)}
              className={inputClass}
              data-testid="rotation-start"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'restaurant.rotations.endTime', 'Fin')}
            </span>
            <input
              type="time"
              value={formEnd}
              onChange={(e) => setFormEnd(e.target.value)}
              className={inputClass}
              data-testid="rotation-end"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'restaurant.rotations.break', 'Pause (min)')}
            </span>
            <input
              type="number"
              min={0}
              max={480}
              value={formBreak}
              onChange={(e) => setFormBreak(e.target.value)}
              className={inputClass}
              data-testid="rotation-break"
            />
          </label>
          <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
            <button
              type="submit"
              disabled={saving}
              data-testid="rotation-submit"
              className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white transition hover:bg-emerald-700 disabled:opacity-50"
            >
              {saving
                ? t(locale, 'restaurant.rotations.saving', 'Enregistrement…')
                : formId === ''
                  ? t(locale, 'restaurant.rotations.create', 'Créer')
                  : t(locale, 'restaurant.rotations.save', 'Enregistrer')}
            </button>
            {formId !== '' ? (
              <button
                type="button"
                onClick={resetForm}
                className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
              >
                {t(locale, 'restaurant.rotations.cancel', 'Annuler')}
              </button>
            ) : null}
          </div>
        </form>
        {formError ? (
          <p role="alert" className="mt-3 text-sm font-semibold text-red-600">
            {formError}
          </p>
        ) : null}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'restaurant.rotations.listTitle', 'Rotations existantes')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'restaurant.loading', 'Chargement…')}</p>
        ) : schedules.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="rotation-empty">
            {t(locale, 'restaurant.rotations.empty', 'Aucune rotation pour le moment.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'restaurant.rotations.name', 'Nom')}</th>
                  <th className="px-3 py-2">{t(locale, 'restaurant.rotations.startTime', 'Début')}</th>
                  <th className="px-3 py-2">{t(locale, 'restaurant.rotations.endTime', 'Fin')}</th>
                  <th className="px-3 py-2">{t(locale, 'restaurant.rotations.break', 'Pause (min)')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {schedules.map((schedule) => (
                  <tr key={schedule.id} className="border-b border-slate-100" data-testid={`rotation-row-${schedule.id}`}>
                    <td className="px-3 py-2 font-semibold text-slate-800">{schedule.name}</td>
                    <td className="px-3 py-2 text-slate-600">{(schedule.start_time ?? '—').slice(0, 5)}</td>
                    <td className="px-3 py-2 text-slate-600">{(schedule.end_time ?? '—').slice(0, 5)}</td>
                    <td className="px-3 py-2 text-slate-600">{schedule.break_minutes ?? 0}</td>
                    <td className="px-3 py-2">
                      <div className="flex flex-wrap items-center justify-end gap-2">
                        <select
                          value={assignSelections[schedule.id] ?? ''}
                          onChange={(e) =>
                            setAssignSelections((prev) => ({ ...prev, [schedule.id]: e.target.value }))
                          }
                          className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none"
                          data-testid={`rotation-assign-select-${schedule.id}`}
                        >
                          <option value="">
                            {t(locale, 'restaurant.rotations.selectPlaceholder', 'Sélectionner…')}
                          </option>
                          {employees.map((employee) => (
                            <option key={employee.id} value={employee.id}>
                              {employee.label}
                            </option>
                          ))}
                        </select>
                        <button
                          type="button"
                          onClick={() => void assign(schedule)}
                          disabled={assigningId === schedule.id}
                          data-testid={`rotation-assign-${schedule.id}`}
                          className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        >
                          {assigningId === schedule.id
                            ? t(locale, 'restaurant.rotations.assigning', 'Affectation…')
                            : t(locale, 'restaurant.rotations.assign', 'Affecter des employés')}
                        </button>
                        <button
                          type="button"
                          onClick={() => startEdit(schedule)}
                          data-testid={`rotation-edit-${schedule.id}`}
                          className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                        >
                          {t(locale, 'restaurant.rotations.edit', 'Modifier')}
                        </button>
                        <button
                          type="button"
                          onClick={() => void remove(schedule)}
                          data-testid={`rotation-remove-${schedule.id}`}
                          className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-50"
                        >
                          {t(locale, 'restaurant.rotations.remove', 'Supprimer')}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </ModulePageShell>
  );
}
