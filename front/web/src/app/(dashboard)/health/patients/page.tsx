'use client';

/**
 * HealthManager — registre patients (HC-003, #7787 / web #7792).
 * Table paginée avec recherche serveur, formulaire de création/édition
 * (identité, groupe sanguin, assurance) et archivage logique (jamais de
 * suppression physique — données de santé).
 */
import { useCallback, useEffect, useState } from 'react';
import { Users } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  archivePatient,
  createPatient,
  listPatients,
  updatePatient,
  type Patient,
  type PatientInput,
  type PatientSex,
} from '@/lib/health-api';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

type FormState = {
  full_name: string;
  sex: PatientSex;
  birth_date: string;
  phone: string;
  blood_group: string;
  insurance_provider: string;
  insurance_number: string;
};

const EMPTY_FORM: FormState = {
  full_name: '',
  sex: 'female',
  birth_date: '',
  phone: '',
  blood_group: '',
  insurance_provider: '',
  insurance_number: '',
};

export default function HealthPatientsPage() {
  const locale = getPreferredLocale();
  const [patients, setPatients] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Patient | null>(null);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const payload = await listPatients({ search: search || undefined, page });
      setPatients(payload.data);
      setLastPage(payload.meta?.last_page ?? 1);
    } catch {
      setError(t(locale, 'health.common.loadError', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale, search, page]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY_FORM);
    setFormError('');
    setShowForm(true);
  };

  const openEdit = (patient: Patient) => {
    setEditing(patient);
    setForm({
      full_name: patient.full_name ?? '',
      sex: patient.sex ?? 'female',
      birth_date: patient.birth_date ?? '',
      phone: patient.phone ?? '',
      blood_group: patient.blood_group ?? '',
      insurance_provider: patient.insurance_provider ?? '',
      insurance_number: patient.insurance_number ?? '',
    });
    setFormError('');
    setShowForm(true);
  };

  const submit = async () => {
    setSaving(true);
    setFormError('');
    try {
      const input: PatientInput = {
        full_name: form.full_name,
        sex: form.sex,
        birth_date: form.birth_date || undefined,
        phone: form.phone || undefined,
        blood_group: form.blood_group || undefined,
        insurance_provider: form.insurance_provider || undefined,
        insurance_number: form.insurance_number || undefined,
      };
      if (editing) {
        await updatePatient(editing.id, input);
      } else {
        await createPatient(input);
      }
      setShowForm(false);
      await load();
    } catch (e) {
      setFormError(e instanceof Error ? e.message : t(locale, 'health.crud.saveError', 'Erreur lors de la sauvegarde.'));
    } finally {
      setSaving(false);
    }
  };

  const archive = async (patient: Patient) => {
    if (!window.confirm(t(locale, 'health.patients.archiveConfirm', 'Archiver ce patient ? (aucune suppression physique)'))) {
      return;
    }
    try {
      await archivePatient(patient.id);
      await load();
    } catch {
      window.alert(t(locale, 'health.patients.archiveError', "Impossible d'archiver ce patient."));
    }
  };

  const updateField = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  return (
    <ModulePageShell
      icon={Users}
      title={t(locale, 'health.patients.title', 'Patients')}
      description={t(locale, 'health.patients.subtitle', 'Registre patients : identités protégées, assurance, archivage logique.')}
    >
      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <input
            type="search"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder={t(locale, 'health.patients.searchPlaceholder', 'Rechercher un patient (nom, n° dossier)...')}
            className="w-full max-w-sm rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
            aria-label={t(locale, 'health.crud.search', 'Rechercher...')}
          />
          <button
            type="button"
            onClick={openCreate}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
          >
            {t(locale, 'health.patients.create', 'Nouveau patient')}
          </button>
        </div>

        {error ? <p className="text-sm font-semibold text-red-600">{error}</p> : null}

        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white/80 shadow-sm backdrop-blur-xl">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.patients.mrn', 'N° dossier')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.patients.fullName', 'Nom complet')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.patients.sex', 'Sexe')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.patients.bloodGroup', 'Groupe sanguin')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.patients.phone', 'Téléphone')}</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'health.patients.status', 'Statut')}</th>
                <th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                    {t(locale, 'health.crud.loading', 'Chargement...')}
                  </td>
                </tr>
              ) : patients.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                    {t(locale, 'health.patients.empty', 'Aucun patient.')}
                  </td>
                </tr>
              ) : (
                patients.map((patient) => (
                  <tr key={patient.id} className="hover:bg-emerald-50/40">
                    <td className="px-4 py-3 font-mono text-xs font-bold text-slate-500">{patient.mrn}</td>
                    <td className="px-4 py-3 font-bold text-slate-900">{patient.full_name}</td>
                    <td className="px-4 py-3 text-slate-600">
                      {t(locale, `health.patients.sexValue.${patient.sex}`, patient.sex)}
                    </td>
                    <td className="px-4 py-3 text-slate-600">{patient.blood_group ?? '—'}</td>
                    <td className="px-4 py-3 text-slate-600">{patient.phone ?? '—'}</td>
                    <td className="px-4 py-3 text-slate-600">
                      {t(locale, `health.patients.statusValue.${patient.status}`, patient.status)}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex justify-end gap-3">
                        <button
                          type="button"
                          className="font-medium text-emerald-700 hover:text-emerald-800"
                          onClick={() => openEdit(patient)}
                        >
                          {t(locale, 'health.crud.edit', 'Modifier')}
                        </button>
                        {patient.status !== 'archived' ? (
                          <button
                            type="button"
                            className="font-medium text-amber-600 hover:text-amber-700"
                            onClick={() => void archive(patient)}
                          >
                            {t(locale, 'health.patients.archive', 'Archiver')}
                          </button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {lastPage > 1 ? (
          <div className="flex items-center justify-end gap-3 text-sm">
            <button
              type="button"
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            >
              {t(locale, 'health.common.previous', 'Précédent')}
            </button>
            <span className="font-semibold text-slate-600">
              {page} / {lastPage}
            </span>
            <button
              type="button"
              disabled={page >= lastPage}
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              className="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            >
              {t(locale, 'health.common.next', 'Suivant')}
            </button>
          </div>
        ) : null}

        {showForm ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
            <div className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
              <h3 className="mb-4 text-lg font-bold text-slate-900">
                {editing
                  ? t(locale, 'health.patients.edit', 'Modifier le patient')
                  : t(locale, 'health.patients.create', 'Nouveau patient')}
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
                    {t(locale, 'health.patients.fullName', 'Nom complet')} <span className="text-red-500">*</span>
                  </span>
                  <input
                    type="text"
                    required
                    value={form.full_name}
                    onChange={(e) => updateField('full_name', e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.patients.sex', 'Sexe')} <span className="text-red-500">*</span>
                    </span>
                    <select
                      required
                      value={form.sex}
                      onChange={(e) => updateField('sex', e.target.value as PatientSex)}
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    >
                      {(['female', 'male', 'other'] as const).map((sex) => (
                        <option key={sex} value={sex}>
                          {t(locale, `health.patients.sexValue.${sex}`, sex)}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.patients.birthDate', 'Date de naissance')}
                    </span>
                    <input
                      type="date"
                      value={form.birth_date}
                      onChange={(e) => updateField('birth_date', e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.patients.phone', 'Téléphone')}
                    </span>
                    <input
                      type="tel"
                      value={form.phone}
                      onChange={(e) => updateField('phone', e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.patients.bloodGroup', 'Groupe sanguin')}
                    </span>
                    <select
                      value={form.blood_group}
                      onChange={(e) => updateField('blood_group', e.target.value)}
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="">—</option>
                      {BLOOD_GROUPS.map((group) => (
                        <option key={group} value={group}>
                          {group}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.patients.insuranceProvider', 'Assureur')}
                    </span>
                    <input
                      type="text"
                      value={form.insurance_provider}
                      onChange={(e) => updateField('insurance_provider', e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="mb-1 block font-semibold text-slate-700">
                      {t(locale, 'health.patients.insuranceNumber', "N° d'assuré")}
                    </span>
                    <input
                      type="text"
                      value={form.insurance_number}
                      onChange={(e) => updateField('insurance_number', e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  </label>
                </div>
                <p className="text-xs text-slate-500">
                  {t(locale, 'health.patients.piiHint', 'Les données sensibles sont chiffrées au repos et jamais exposées hors tenant.')}
                </p>
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
