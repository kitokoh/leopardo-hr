'use client';

/**
 * HealthManager — hospitalisations & lits (HC-006, #7790 / web #7792).
 * Résumé d'occupation (`GET /admissions/occupancy`), liste des admissions
 * actives, formulaire d'admission (patient, praticien référent, service,
 * lit libre) et actions de transfert (nouveau lit) et de sortie.
 */
import { useCallback, useEffect, useState } from 'react';
import { BedDouble } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  createAdmission,
  dischargeAdmission,
  getOccupancy,
  listAdmissions,
  listBeds,
  listDepartments,
  listPatients,
  listPractitioners,
  transferAdmission,
  type Admission,
  type Bed,
  type BedOccupancy,
  type Department,
  type Patient,
  type Practitioner,
} from '@/lib/health-api';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

function formatDate(value: string | null | undefined, locale: string): string {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString(locale, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

type FormState = {
  patient_id: string;
  practitioner_id: string;
  department_id: string;
  bed_id: string;
  reason: string;
};

const EMPTY_FORM: FormState = { patient_id: '', practitioner_id: '', department_id: '', bed_id: '', reason: '' };

export default function HealthAdmissionsPage() {
  const locale = getPreferredLocale();
  const [admissions, setAdmissions] = useState<Admission[]>([]);
  const [occupancy, setOccupancy] = useState<BedOccupancy | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [patients, setPatients] = useState<Patient[]>([]);
  const [practitioners, setPractitioners] = useState<Practitioner[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [freeBeds, setFreeBeds] = useState<Bed[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [transferTarget, setTransferTarget] = useState<Admission | null>(null);
  const [transferBedId, setTransferBedId] = useState('');
  const [acting, setActing] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [admissionsPayload, occupancyPayload] = await Promise.all([listAdmissions(), getOccupancy()]);
      setAdmissions(admissionsPayload.data.filter((a) => a.status !== 'discharged'));
      setOccupancy(occupancyPayload);
    } catch {
      setError(t(locale, 'health.common.loadError', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const loadFormOptions = async () => {
    try {
      const [patientsPayload, practitionersList, departmentsList, bedsList] = await Promise.all([
        listPatients({ per_page: 200 }),
        listPractitioners(),
        listDepartments(),
        listBeds({ status: 'free' }),
      ]);
      setPatients(patientsPayload.data);
      setPractitioners(practitionersList);
      setDepartments(departmentsList);
      setFreeBeds(bedsList);
    } catch {
      // formulaire utilisable avec listes vides
    }
  };

  const openCreate = async () => {
    setForm(EMPTY_FORM);
    setFormError('');
    setShowForm(true);
    await loadFormOptions();
  };

  const submit = async () => {
    setSaving(true);
    setFormError('');
    try {
      await createAdmission({
        patient_id: Number(form.patient_id),
        practitioner_id: Number(form.practitioner_id),
        department_id: Number(form.department_id),
        bed_id: Number(form.bed_id),
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

  const openTransfer = async (admission: Admission) => {
    setTransferTarget(admission);
    setTransferBedId('');
    try {
      setFreeBeds(await listBeds({ status: 'free' }));
    } catch {
      setFreeBeds([]);
    }
  };

  const confirmTransfer = async () => {
    if (!transferTarget || !transferBedId) return;
    setActing(transferTarget.id);
    try {
      await transferAdmission(transferTarget.id, Number(transferBedId));
      setTransferTarget(null);
      await load();
    } catch (e) {
      window.alert(e instanceof Error ? e.message : t(locale, 'health.admissions.transferError', 'Transfert impossible.'));
    } finally {
      setActing(null);
    }
  };

  const discharge = async (admission: Admission) => {
    if (!window.confirm(t(locale, 'health.admissions.dischargeConfirm', 'Prononcer la sortie de ce patient ? Le lit sera libéré.'))) {
      return;
    }
    setActing(admission.id);
    try {
      await dischargeAdmission(admission.id);
      await load();
    } catch (e) {
      window.alert(e instanceof Error ? e.message : t(locale, 'health.admissions.dischargeError', 'Sortie impossible.'));
    } finally {
      setActing(null);
    }
  };

  const summary = [
    {
      label: t(locale, 'health.admissions.totalBeds', 'Lits au total'),
      value: occupancy?.total_beds ?? 0,
    },
    {
      label: t(locale, 'health.admissions.occupiedBeds', 'Lits occupés'),
      value: occupancy?.occupied_beds ?? 0,
    },
    {
      label: t(locale, 'health.admissions.freeBeds', 'Lits libres'),
      value: occupancy?.free_beds ?? 0,
    },
    {
      label: t(locale, 'health.admissions.occupancyRate', "Taux d'occupation"),
      value:
        occupancy?.occupancy_rate !== undefined
          ? `${Math.round((occupancy.occupancy_rate ?? 0) * 100)}%`
          : '—',
    },
  ];

  return (
    <ModulePageShell
      icon={BedDouble}
      title={t(locale, 'health.admissions.title', 'Hospitalisations')}
      description={t(locale, 'health.admissions.subtitle', 'Admissions en cours, transferts, sorties et occupation des lits.')}
    >
      <div className="space-y-6">
        <section aria-label={t(locale, 'health.admissions.occupancyTitle', 'Occupation des lits')}>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {summary.map((item) => (
              <div key={item.label} className="rounded-2xl border border-slate-200/60 bg-white/80 p-4 shadow-sm backdrop-blur-xl">
                <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">{item.label}</p>
                <p className="mt-2 text-2xl font-black tracking-tight text-slate-950">{String(item.value)}</p>
              </div>
            ))}
          </div>
        </section>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-lg font-black tracking-tight text-slate-950">
            {t(locale, 'health.admissions.activeTitle', 'Admissions actives')}
          </h2>
          <button
            type="button"
            onClick={() => void openCreate()}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
          >
            {t(locale, 'health.admissions.create', 'Nouvelle admission')}
          </button>
        </div>

        {error ? <p className="text-sm font-semibold text-red-600">{error}</p> : null}

        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white/80 shadow-sm backdrop-blur-xl">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.admissions.patient', 'Patient')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.admissions.department', 'Service')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.admissions.bed', 'Lit')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.admissions.admittedAt', 'Admis le')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.admissions.status', 'Statut')}</th>
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
              ) : admissions.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                    {t(locale, 'health.admissions.empty', 'Aucune admission en cours.')}
                  </td>
                </tr>
              ) : (
                admissions.map((admission) => (
                  <tr key={admission.id} className="hover:bg-emerald-50/40">
                    <td className="px-4 py-3 font-bold text-slate-900">
                      {admission.patient?.full_name ?? `#${admission.patient_id}`}
                    </td>
                    <td className="px-4 py-3 text-slate-600">
                      {admission.department?.name ?? `#${admission.department_id}`}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs font-bold text-slate-600">
                      {admission.bed?.code ?? `#${admission.bed_id}`}
                    </td>
                    <td className="px-4 py-3 text-slate-600">{formatDate(admission.admitted_at, locale)}</td>
                    <td className="px-4 py-3 text-slate-600">
                      {t(locale, `health.admissions.statusValue.${admission.status}`, admission.status)}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex flex-wrap justify-end gap-2">
                        <button
                          type="button"
                          disabled={acting === admission.id}
                          onClick={() => void openTransfer(admission)}
                          className="rounded-lg border border-cyan-200 px-2.5 py-1 text-xs font-bold text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                        >
                          {t(locale, 'health.admissions.transfer', 'Transférer')}
                        </button>
                        <button
                          type="button"
                          disabled={acting === admission.id}
                          onClick={() => void discharge(admission)}
                          className="rounded-lg border border-emerald-200 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                        >
                          {t(locale, 'health.admissions.discharge', 'Sortie')}
                        </button>
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
                {t(locale, 'health.admissions.create', 'Nouvelle admission')}
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
                    {t(locale, 'health.admissions.patient', 'Patient')} <span className="text-red-500">*</span>
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
                    {t(locale, 'health.admissions.practitioner', 'Praticien référent')} <span className="text-red-500">*</span>
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
                      {t(locale, 'health.admissions.department', 'Service')} <span className="text-red-500">*</span>
                    </span>
                    <select
                      required
                      value={form.department_id}
                      onChange={(e) => setForm((prev) => ({ ...prev, department_id: e.target.value }))}
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="">—</option>
                      {departments.map((department) => (
                        <option key={department.id} value={department.id}>
                          {department.name}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.admissions.freeBed', 'Lit libre')} <span className="text-red-500">*</span>
                    </span>
                    <select
                      required
                      value={form.bed_id}
                      onChange={(e) => setForm((prev) => ({ ...prev, bed_id: e.target.value }))}
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="">—</option>
                      {freeBeds.map((bed) => (
                        <option key={bed.id} value={bed.id}>
                          {bed.code}
                          {bed.room?.name ? ` — ${bed.room.name}` : ''}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>
                <label className="block text-sm">
                  <span className="mb-1 block font-semibold text-slate-700">
                    {t(locale, 'health.admissions.reason', "Motif d'admission")}
                  </span>
                  <textarea
                    rows={2}
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

        {transferTarget ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
              <h3 className="mb-4 text-lg font-bold text-slate-900">
                {t(locale, 'health.admissions.transferTitle', 'Transférer le patient')}
              </h3>
              <label className="block text-sm">
                <span className="mb-1 block font-semibold text-slate-700">
                  {t(locale, 'health.admissions.newBed', 'Nouveau lit (libre)')} <span className="text-red-500">*</span>
                </span>
                <select
                  value={transferBedId}
                  onChange={(e) => setTransferBedId(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                >
                  <option value="">—</option>
                  {freeBeds.map((bed) => (
                    <option key={bed.id} value={bed.id}>
                      {bed.code}
                      {bed.room?.name ? ` — ${bed.room.name}` : ''}
                    </option>
                  ))}
                </select>
              </label>
              <div className="mt-4 flex justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setTransferTarget(null)}
                  className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                >
                  {t(locale, 'health.crud.cancel', 'Annuler')}
                </button>
                <button
                  type="button"
                  disabled={!transferBedId || acting === transferTarget.id}
                  onClick={() => void confirmTransfer()}
                  className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                >
                  {t(locale, 'health.admissions.transfer', 'Transférer')}
                </button>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </ModulePageShell>
  );
}
