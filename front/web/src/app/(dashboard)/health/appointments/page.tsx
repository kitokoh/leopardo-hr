'use client';

/**
 * HealthManager — rendez-vous & agenda (HC-004, #7788 / web #7792).
 * Liste des rendez-vous par jour, création (patient, praticien, créneau,
 * motif) et transitions de statut valides (spec §4) :
 * scheduled→confirmed|cancelled ; confirmed→checked_in|cancelled|no_show ;
 * checked_in→completed.
 */
import { useCallback, useEffect, useState } from 'react';
import { CalendarClock } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  APPOINTMENT_TRANSITIONS,
  createAppointment,
  listAppointments,
  listPatients,
  listPractitioners,
  transitionAppointment,
  type Appointment,
  type AppointmentStatus,
  type Patient,
  type Practitioner,
} from '@/lib/health-api';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

function timeOf(value: string, locale: string): string {
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

const STATUS_BADGES: Record<AppointmentStatus, string> = {
  scheduled: 'bg-slate-100 text-slate-700',
  confirmed: 'bg-cyan-100 text-cyan-800',
  checked_in: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-emerald-600/10 text-emerald-700',
  cancelled: 'bg-rose-100 text-rose-700',
  no_show: 'bg-amber-100 text-amber-800',
};

type FormState = {
  patient_id: string;
  practitioner_id: string;
  starts_at: string;
  ends_at: string;
  reason: string;
};

const EMPTY_FORM: FormState = { patient_id: '', practitioner_id: '', starts_at: '', ends_at: '', reason: '' };

export default function HealthAppointmentsPage() {
  const locale = getPreferredLocale();
  const [day, setDay] = useState(todayIso());
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [patients, setPatients] = useState<Patient[]>([]);
  const [practitioners, setPractitioners] = useState<Practitioner[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [transitioning, setTransitioning] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const payload = await listAppointments({ date: day });
      setAppointments(payload.data);
    } catch {
      setError(t(locale, 'health.common.loadError', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale, day]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = async () => {
    setForm({ ...EMPTY_FORM, starts_at: `${day}T09:00`, ends_at: `${day}T09:30` });
    setFormError('');
    setShowForm(true);
    try {
      const [patientsPayload, practitionersList] = await Promise.all([
        listPatients({ per_page: 200 }),
        listPractitioners(),
      ]);
      setPatients(patientsPayload.data);
      setPractitioners(practitionersList);
    } catch {
      // le formulaire reste utilisable ; les listes seront vides
    }
  };

  const submit = async () => {
    setSaving(true);
    setFormError('');
    try {
      await createAppointment({
        patient_id: Number(form.patient_id),
        practitioner_id: Number(form.practitioner_id),
        starts_at: form.starts_at,
        ends_at: form.ends_at,
        reason: form.reason || undefined,
      });
      setShowForm(false);
      await load();
    } catch (e) {
      setFormError(e instanceof Error ? e.message : t(locale, 'health.crud.saveError', 'Erreur lors de la sauvegarde.'));
    } finally {
      setSaving(false);
    }
  };

  const transition = async (appointment: Appointment, status: AppointmentStatus) => {
    setTransitioning(appointment.id);
    try {
      await transitionAppointment(appointment.id, status);
      await load();
    } catch (e) {
      window.alert(e instanceof Error ? e.message : t(locale, 'health.appointments.transitionError', 'Transition impossible.'));
    } finally {
      setTransitioning(null);
    }
  };

  const practitionerLabel = (appointment: Appointment): string =>
    appointment.practitioner?.full_name ?? `#${appointment.practitioner_id}`;

  return (
    <ModulePageShell
      icon={CalendarClock}
      title={t(locale, 'health.appointments.title', 'Rendez-vous')}
      description={t(locale, 'health.appointments.subtitle', 'Agenda par journée, prise de rendez-vous et suivi des statuts.')}
    >
      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            {t(locale, 'health.appointments.day', 'Journée')}
            <input
              type="date"
              value={day}
              onChange={(e) => setDay(e.target.value || todayIso())}
              className="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
            />
          </label>
          <button
            type="button"
            onClick={() => void openCreate()}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
          >
            {t(locale, 'health.appointments.create', 'Nouveau rendez-vous')}
          </button>
        </div>

        {error ? <p className="text-sm font-semibold text-red-600">{error}</p> : null}

        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white/80 shadow-sm backdrop-blur-xl">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.appointments.slot', 'Créneau')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.appointments.patient', 'Patient')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.appointments.practitioner', 'Praticien')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.appointments.reason', 'Motif')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.appointments.status', 'Statut')}</th>
                <th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                    {t(locale, 'health.crud.loading', 'Chargement...')}
                  </td>
                </tr>
              ) : appointments.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                    {t(locale, 'health.appointments.empty', 'Aucun rendez-vous ce jour.')}
                  </td>
                </tr>
              ) : (
                appointments.map((appointment) => (
                  <tr key={appointment.id} className="hover:bg-emerald-50/40">
                    <td className="px-4 py-3 font-mono text-xs font-bold text-slate-600">
                      {timeOf(appointment.starts_at, locale)} – {timeOf(appointment.ends_at, locale)}
                    </td>
                    <td className="px-4 py-3 font-bold text-slate-900">
                      {appointment.patient?.full_name ?? `#${appointment.patient_id}`}
                    </td>
                    <td className="px-4 py-3 text-slate-600">{practitionerLabel(appointment)}</td>
                    <td className="px-4 py-3 text-slate-600">{appointment.reason ?? '—'}</td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${STATUS_BADGES[appointment.status] ?? 'bg-slate-100 text-slate-700'}`}
                      >
                        {t(locale, `health.appointments.statusValue.${appointment.status}`, appointment.status)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex flex-wrap justify-end gap-2">
                        {(APPOINTMENT_TRANSITIONS[appointment.status] ?? []).map((next) => (
                          <button
                            key={next}
                            type="button"
                            disabled={transitioning === appointment.id}
                            onClick={() => void transition(appointment, next)}
                            className={`rounded-lg border px-2.5 py-1 text-xs font-bold disabled:opacity-50 ${
                              next === 'cancelled' || next === 'no_show'
                                ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                                : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                            }`}
                          >
                            {t(locale, `health.appointments.transition.${next}`, next)}
                          </button>
                        ))}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {showForm ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
            <div className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
              <h3 className="mb-4 text-lg font-bold text-slate-900">
                {t(locale, 'health.appointments.create', 'Nouveau rendez-vous')}
              </h3>
              {formError ? <p className="mb-3 text-sm text-red-600">{formError}</p> : null}
              <form
                className="space-y-4"
                onSubmit={(e) => {
                  e.preventDefault();
                  void submit();
                }}
              >
                <label className="block text-sm">
                  <span className="mb-1 block font-semibold text-slate-700">
                    {t(locale, 'health.appointments.patient', 'Patient')} <span className="text-red-500">*</span>
                  </span>
                  <select
                    required
                    value={form.patient_id}
                    onChange={(e) => setForm((prev) => ({ ...prev, patient_id: e.target.value }))}
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="">—</option>
                    {patients.map((patient) => (
                      <option key={patient.id} value={patient.id}>
                        {patient.full_name} ({patient.mrn})
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block font-semibold text-slate-700">
                    {t(locale, 'health.appointments.practitioner', 'Praticien')} <span className="text-red-500">*</span>
                  </span>
                  <select
                    required
                    value={form.practitioner_id}
                    onChange={(e) => setForm((prev) => ({ ...prev, practitioner_id: e.target.value }))}
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="">—</option>
                    {practitioners.map((practitioner) => (
                      <option key={practitioner.id} value={practitioner.id}>
                        {practitioner.full_name ?? `#${practitioner.id}`}
                      </option>
                    ))}
                  </select>
                </label>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.appointments.startsAt', 'Début')} <span className="text-red-500">*</span>
                    </span>
                    <input
                      type="datetime-local"
                      required
                      value={form.starts_at}
                      onChange={(e) => setForm((prev) => ({ ...prev, starts_at: e.target.value }))}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.appointments.endsAt', 'Fin')} <span className="text-red-500">*</span>
                    </span>
                    <input
                      type="datetime-local"
                      required
                      value={form.ends_at}
                      onChange={(e) => setForm((prev) => ({ ...prev, ends_at: e.target.value }))}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  </label>
                </div>
                <label className="block text-sm">
                  <span className="mb-1 block font-semibold text-slate-700">
                    {t(locale, 'health.appointments.reason', 'Motif')}
                  </span>
                  <input
                    type="text"
                    value={form.reason}
                    onChange={(e) => setForm((prev) => ({ ...prev, reason: e.target.value }))}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
                <div className="flex justify-end gap-3 pt-2">
                  <button
                    type="button"
                    onClick={() => setShowForm(false)}
                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                  >
                    {t(locale, 'health.crud.cancel', 'Annuler')}
                  </button>
                  <button
                    type="submit"
                    disabled={saving}
                    className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                  >
                    {saving
                      ? t(locale, 'health.crud.saving', 'Enregistrement...')
                      : t(locale, 'health.crud.save', 'Enregistrer')}
                  </button>
                </div>
              </form>
            </div>
          </div>
        ) : null}
      </div>
    </ModulePageShell>
  );
}
