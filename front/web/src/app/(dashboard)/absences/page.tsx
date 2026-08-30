'use client';

/**
 * Page Absences/Congés — liste + formulaire de demande (#5693).
 *
 * Deux contextes d'utilisation :
 * - Manager : voit toutes les demandes de son équipe, peut approuver/refuser.
 * - Employé : voit ses propres demandes et peut en créer de nouvelles.
 */

import { useCallback, useEffect, useState } from 'react';
import { Check, Loader2, Plus, X, AlertTriangle, Calendar, FileText } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, normalizeLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { t as i18nT } from '@/lib/i18n/locale-catalog';
import type { AppLocale } from '@/lib/i18n';

type AbsenceRecord = {
  id: number;
  start_date?: string;
  end_date?: string;
  status?: string;
  reason?: string | null;
  days_count?: number | string | null;
  absence_type?: { name?: string } | null;
};

type AbsenceType = {
  id: number;
  name: string;
  code: string;
  is_paid: boolean;
  deducts_leave: boolean;
  requires_proof: boolean;
  max_days_once: number;
};

type AbsencesPayload   = { data?: AbsenceRecord[] };
type AbsenceTypesPayload = { data?: AbsenceType[] };

const STATUS_LABEL_KEY: Record<string, keyof ReturnType<typeof getCopy>['absences']> = {
  pending:   'statusPending',
  approved:  'statusApproved',
  rejected:  'statusRejected',
  cancelled: 'statusCancelled',
};

export default function AbsencesPage() {
  const { locale } = useVitrineLocale();
  const appLocale = normalizeLocale(locale ?? 'fr') as AppLocale;
  const labels = getCopy(appLocale).absences;

  const [absences, setAbsences]   = useState<AbsenceRecord[]>([]);
  const [error, setError]         = useState<string | null>(null);
  const [loading, setLoading]     = useState(true);
  const [actionId, setActionId]   = useState<number | null>(null);

  // Reject modal
  const [rejectTarget, setRejectTarget]   = useState<AbsenceRecord | null>(null);
  const [rejectReason, setRejectReason]   = useState('');
  const [rejecting, setRejecting]         = useState(false);
  const [rejectError, setRejectError]     = useState<string | null>(null);

  // New-request modal (#5693)
  const [showNewForm, setShowNewForm]     = useState(false);
  const [absenceTypes, setAbsenceTypes]   = useState<AbsenceType[]>([]);
  const [typesLoading, setTypesLoading]   = useState(false);
  const [newTypeId, setNewTypeId]         = useState('');
  const [newStart, setNewStart]           = useState('');
  const [newEnd, setNewEnd]               = useState('');
  const [newReason, setNewReason]         = useState('');
  const [newProof, setNewProof]           = useState<File | null>(null);
  const [submitting, setSubmitting]       = useState(false);
  const [newFormError, setNewFormError]   = useState<string | null>(null);
  const [newFormSuccess, setNewFormSuccess] = useState<string | null>(null);

  // ── Chargement de la liste des absences ──────────────────────────────────
  const loadAbsences = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiFetch('/absences');
      const payload = (await response.json()) as AbsencesPayload;
      setAbsences(Array.isArray(payload.data) ? payload.data : []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setLoading(false);
    }
  }, [labels.loadError]);

  useEffect(() => { void loadAbsences(); }, [loadAbsences]);

  // ── Chargement des types d'absence (lazy, à l'ouverture du formulaire) ───
  const openNewForm = useCallback(async () => {
    setShowNewForm(true);
    setNewFormError(null);
    setNewFormSuccess(null);

    if (absenceTypes.length > 0) return;

    setTypesLoading(true);
    try {
      const r = await apiFetch('/absence-types');
      const payload = (await r.json()) as AbsenceTypesPayload;
      setAbsenceTypes(payload.data ?? []);
    } catch {
      setNewFormError(i18nT(appLocale, 'absence.typesLoadError', 'Impossible de charger les types de congé.'));
    } finally {
      setTypesLoading(false);
    }
  }, [absenceTypes.length, appLocale]);

  const closeNewForm = useCallback(() => {
    setShowNewForm(false);
    setNewTypeId('');
    setNewStart('');
    setNewEnd('');
    setNewReason('');
    setNewProof(null);
    setNewFormError(null);
    setNewFormSuccess(null);
  }, []);

  // ── Soumission de la nouvelle demande ────────────────────────────────────
  const handleSubmitNew = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setNewFormError(null);

    if (!newTypeId) {
      setNewFormError(i18nT(appLocale, 'absence.typeRequired', 'Veuillez sélectionner un type de congé.'));
      return;
    }
    if (!newStart || !newEnd) {
      setNewFormError(i18nT(appLocale, 'absence.datesRequired', 'Les dates de début et de fin sont obligatoires.'));
      return;
    }
    if (newEnd < newStart) {
      setNewFormError(i18nT(appLocale, 'absence.endAfterStart', 'La date de fin doit être après la date de début.'));
      return;
    }

    setSubmitting(true);

    try {
      // Si une pièce jointe est fournie, on envoie en multipart/form-data.
      let body: FormData | string;

      if (newProof) {
        const fd = new FormData();
        fd.append('absence_type_id', newTypeId);
        fd.append('start_date', newStart);
        fd.append('end_date', newEnd);
        if (newReason) fd.append('reason', newReason);
        fd.append('proof', newProof);
        body = fd;
      } else {
        body = JSON.stringify({
          absence_type_id: Number(newTypeId),
          start_date: newStart,
          end_date: newEnd,
          reason: newReason || null,
        });
      }

      const response = await apiFetch('/absences', {
        method: 'POST',
        ...(newProof ? {} : { headers: { 'Content-Type': 'application/json' } }),
        body,
      });

      const result = (await response.json()) as { data?: AbsenceRecord };
      if (result.data) {
        setAbsences((prev) => [result.data as AbsenceRecord, ...prev]);
      }

      setNewFormSuccess(i18nT(appLocale, 'absence.submitSuccess', 'Votre demande a été envoyée et est en attente de validation.'));
      setNewTypeId('');
      setNewStart('');
      setNewEnd('');
      setNewReason('');
      setNewProof(null);
    } catch (err) {
      setNewFormError(err instanceof ApiError ? err.message : i18nT(appLocale, 'absence.submitError', 'Impossible d\'envoyer la demande.'));
    } finally {
      setSubmitting(false);
    }
  }, [newTypeId, newStart, newEnd, newReason, newProof, appLocale]);

  // ── Actions manager ──────────────────────────────────────────────────────
  const applyStatus = (id: number, status: string) => {
    setAbsences((prev) => prev.map((a) => (a.id === id ? { ...a, status } : a)));
  };

  const handleApprove = async (absence: AbsenceRecord) => {
    setActionId(absence.id);
    setError(null);
    try {
      await apiFetch(`/absences/${absence.id}/approve`, { method: 'PUT' });
      applyStatus(absence.id, 'approved');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setActionId(null);
    }
  };

  const handleReject = async () => {
    if (!rejectTarget) return;
    const reason = rejectReason.trim();
    if (!reason) { setRejectError(labels.reasonRequired); return; }
    setRejecting(true);
    setRejectError(null);
    try {
      await apiFetch(`/absences/${rejectTarget.id}/reject`, {
        method: 'PUT',
        body: JSON.stringify({ rejected_reason: reason }),
      });
      applyStatus(rejectTarget.id, 'rejected');
      setRejectTarget(null);
      setRejectReason('');
    } catch (err) {
      setRejectError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setRejecting(false);
    }
  };

  const statusLabel = (status: string | undefined) => {
    const key = STATUS_LABEL_KEY[status ?? ''] ?? 'statusPending';
    return labels[key];
  };

  // ── Sélection du type courant (pour afficher requires_proof) ─────────────
  const selectedType = absenceTypes.find((t) => String(t.id) === newTypeId);

  return (
    <ModulePageShell
      title={labels.title}
      subtitle=""
      accentClassName="from-emerald-500 to-teal-600"
    >
      {/* Bouton Nouvelle demande (#5693) */}
      <div className="flex justify-end mb-4">
        <Button
          variant="primary"
          onClick={() => void openNewForm()}
          icon={<Plus className="h-4 w-4" />}
          className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500"
        >
          {i18nT(appLocale, 'absence.newRequest', 'Nouvelle demande')}
        </Button>
      </div>

      {newFormSuccess && (
        <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-800">
          <Check className="h-4 w-4 shrink-0 text-emerald-600" />
          {newFormSuccess}
        </div>
      )}

      <section className="rounded-2xl border border-app-border bg-white/40 shadow-glass-sm backdrop-blur-xl dark:bg-slate-900/40">
        {error && (
          <p role="alert" className="m-4 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
            {error}
          </p>
        )}

        {loading ? (
          <div className="flex items-center justify-center gap-2 px-6 py-10 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            <span>{labels.statusPending}</span>
          </div>
        ) : absences.length === 0 ? (
          <div className="px-6 py-8 text-sm text-slate-500">{labels.empty}</div>
        ) : (
          absences.map((absence) => {
            const isPending = absence.status === 'pending';
            const busy      = actionId === absence.id;
            return (
              <div
                key={absence.id}
                className="flex flex-col gap-3 border-b border-app-border px-6 py-5 last:border-b-0 md:flex-row md:items-center md:justify-between"
              >
                <div>
                  <p className="text-sm font-bold text-slate-950">{absence.absence_type?.name ?? labels.title}</p>
                  <p className="text-xs text-slate-500">
                    {absence.start_date ?? '—'} → {absence.end_date ?? '—'}
                  </p>
                  {absence.reason && (
                    <p className="mt-2 text-xs text-slate-500">{absence.reason}</p>
                  )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                    {absence.days_count ?? 0} j
                  </span>
                  <span
                    className={`rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${
                      absence.status === 'approved'
                        ? 'bg-emerald-50 text-emerald-700'
                        : absence.status === 'rejected'
                          ? 'bg-red-50 text-red-700'
                          : 'bg-info/15 text-info'
                    }`}
                  >
                    {statusLabel(absence.status)}
                  </span>
                  {isPending && (
                    <>
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={() => void handleApprove(absence)}
                        disabled={busy}
                        loading={busy}
                        icon={<Check className="h-3.5 w-3.5" />}
                        className="bg-emerald-600 hover:bg-emerald-500"
                      >
                        {labels.approve}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => { setRejectTarget(absence); setRejectReason(''); setRejectError(null); }}
                        disabled={busy}
                        className="border-red-200 text-red-700 hover:border-red-300 hover:text-red-800"
                      >
                        {labels.reject}
                      </Button>
                    </>
                  )}
                </div>
              </div>
            );
          })
        )}
      </section>

      {/* ── Modal : Nouvelle demande (#5693) ─────────────────────────────── */}
      {showNewForm && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          onClick={(e) => { if (e.target === e.currentTarget) closeNewForm(); }}
        >
          <div
            className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
            role="dialog"
            aria-modal="true"
            aria-label={i18nT(appLocale, 'absence.newRequest', 'Nouvelle demande')}
          >
            <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
              <div className="flex items-center gap-2">
                <Calendar className="h-5 w-5 text-emerald-600" />
                <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                  {i18nT(appLocale, 'absence.newRequest', 'Nouvelle demande de congé')}
                </h2>
              </div>
              <button
                onClick={closeNewForm}
                aria-label={labels.cancel}
                className="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form className="p-6 space-y-5" onSubmit={(e) => void handleSubmitNew(e)}>
              {newFormError && (
                <div role="alert" className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                  <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                  {newFormError}
                </div>
              )}

              {/* Type de congé */}
              <div>
                <label htmlFor="new-absence-type" className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                  {i18nT(appLocale, 'absence.typeLabel', 'Type de congé')} <span className="text-red-500">*</span>
                </label>
                {typesLoading ? (
                  <div className="flex items-center gap-2 text-sm text-slate-400 py-2">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    {i18nT(appLocale, 'absence.typesLoading', 'Chargement...')}
                  </div>
                ) : (
                  <select
                    id="new-absence-type"
                    value={newTypeId}
                    required
                    className="block h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    onChange={(e) => { setNewTypeId(e.target.value); setNewFormError(null); }}
                  >
                    <option value="">{i18nT(appLocale, 'absence.selectType', '— Choisir un type —')}</option>
                    {absenceTypes.map((t) => (
                      <option key={t.id} value={t.id}>{t.name}{t.is_paid ? '' : ' (non payé)'}</option>
                    ))}
                  </select>
                )}
              </div>

              {/* Dates */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label htmlFor="new-start-date" className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    {i18nT(appLocale, 'absence.startDate', 'Début')} <span className="text-red-500">*</span>
                  </label>
                  <input
                    id="new-start-date"
                    type="date"
                    required
                    className="block h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    value={newStart}
                    onChange={(e) => { setNewStart(e.target.value); setNewFormError(null); }}
                  />
                </div>
                <div>
                  <label htmlFor="new-end-date" className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    {i18nT(appLocale, 'absence.endDate', 'Fin')} <span className="text-red-500">*</span>
                  </label>
                  <input
                    id="new-end-date"
                    type="date"
                    required
                    min={newStart || undefined}
                    className="block h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    value={newEnd}
                    onChange={(e) => { setNewEnd(e.target.value); setNewFormError(null); }}
                  />
                </div>
              </div>

              {/* Motif */}
              <div>
                <label htmlFor="new-reason" className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                  {i18nT(appLocale, 'absence.reason', 'Motif')}
                  <span className="ml-1 text-slate-400 font-normal">{i18nT(appLocale, 'absence.optional', '(optionnel)')}</span>
                </label>
                <textarea
                  id="new-reason"
                  rows={2}
                  maxLength={1000}
                  placeholder={i18nT(appLocale, 'absence.reasonPlaceholder', 'Ex : congé annuel, maladie, événement familial…')}
                  className="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                  value={newReason}
                  onChange={(e) => setNewReason(e.target.value)}
                />
              </div>

              {/* Pièce justificative (conditionnel selon requires_proof) */}
              {(selectedType?.requires_proof || newProof) && (
                <div>
                  <label htmlFor="new-proof" className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                    <FileText className="inline h-3.5 w-3.5 mr-1" />
                    {i18nT(appLocale, 'absence.proof', 'Pièce justificative')}
                    {selectedType?.requires_proof && <span className="text-red-500 ml-1">*</span>}
                  </label>
                  <input
                    id="new-proof"
                    type="file"
                    accept=".jpg,.jpeg,.png,.pdf,.heic"
                    className="block w-full text-sm text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-emerald-700 hover:file:bg-emerald-100"
                    onChange={(e) => setNewProof(e.target.files?.[0] ?? null)}
                  />
                  <p className="mt-1 text-xs text-slate-400">
                    {i18nT(appLocale, 'absence.proofHint', 'JPG, PNG, HEIC ou PDF — max 5 Mo')}
                  </p>
                </div>
              )}

              <div className="flex justify-end gap-3 pt-2">
                <Button variant="outline" type="button" onClick={closeNewForm} disabled={submitting} className="bg-white">
                  {labels.cancel}
                </Button>
                <Button
                  type="submit"
                  loading={submitting}
                  disabled={submitting}
                  className="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-500"
                >
                  {submitting
                    ? i18nT(appLocale, 'absence.submitting', 'Envoi...')
                    : i18nT(appLocale, 'absence.submit', 'Envoyer la demande')}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── Modal : Refus ──────────────────────────────────────────────────── */}
      {rejectTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
          <div
            className="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
            role="dialog"
            aria-modal="true"
            aria-label={labels.rejectTitle}
          >
            <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
              <h2 className="text-lg font-bold text-slate-900 dark:text-white">{labels.rejectTitle}</h2>
              <button onClick={() => setRejectTarget(null)} aria-label={labels.cancel} className="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="p-6">
              <p className="text-sm text-slate-500">{labels.rejectBody}</p>
              <label htmlFor="reject-reason" className="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-500">
                {labels.reasonLabel} <span className="text-red-500">*</span>
              </label>
              <textarea
                id="reject-reason"
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder={labels.reasonPlaceholder}
                rows={3}
                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-400/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              />
              {rejectError && (
                <p role="alert" className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{rejectError}</p>
              )}
              <div className="mt-5 flex justify-end gap-3">
                <Button variant="outline" onClick={() => setRejectTarget(null)} disabled={rejecting} className="bg-white">
                  {labels.cancel}
                </Button>
                <Button
                  variant="danger"
                  onClick={() => void handleReject()}
                  disabled={rejecting}
                  loading={rejecting}
                  className="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500"
                >
                  {rejecting ? labels.rejectInProgress : labels.rejectConfirm}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </ModulePageShell>
  );
}
