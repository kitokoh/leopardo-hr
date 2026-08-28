'use client';

import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { AppLocale, getCopy } from '@/lib/i18n';

interface LeaveBalanceRow {
  id: number;
  absence_type_id: number;
  balance: number;
  used: number;
  pending: number;
  year: number;
  absence_type: { id: number; name: string; code: string } | null;
}

interface LeaveBalancesPayload {
  data: LeaveBalanceRow[];
}

/**
 * Formulaire de demande de congé (issue #5693).
 *
 * Auto-servi : tout employé authentifié peut demander une absence
 * (POST /absences — le backend valide le solde, les conflits de dates et le
 * justificatif). Les types disponibles et le solde restant proviennent de
 * GET /me/leave-balances (chaque ligne embarque son `absence_type`).
 */
export default function LeaveRequestForm({ locale, onSubmitted }: { locale: AppLocale; onSubmitted: () => void }) {
  const labels = getCopy(locale).absences;

  const [rows, setRows] = useState<LeaveBalanceRow[]>([]);
  const [balancesLoading, setBalancesLoading] = useState(true);
  const [typeId, setTypeId] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);

  const loadBalances = useCallback(async () => {
    setBalancesLoading(true);
    try {
      const response = await apiFetch('/me/leave-balances');
      const payload = (await response.json()) as LeaveBalancesPayload;
      setRows(payload.data ?? []);
    } catch {
      setRows([]);
    } finally {
      setBalancesLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadBalances();
  }, [loadBalances]);

  const types = useMemo(
    () =>
      rows
        .map((row) => ({
          id: row.absence_type_id,
          name: row.absence_type?.name ?? String(row.absence_type_id),
          available: row.balance - row.used - row.pending,
        }))
        .filter((type) => type.available > 0),
    [rows],
  );

  const selectedAvailable = types.find((type) => type.id === Number(typeId))?.available ?? 0;

  const validate = (): string | null => {
    if (!typeId) {
      return labels.typeRequired;
    }
    if (!startDate || !endDate) {
      return labels.dateRequired;
    }
    if (endDate < startDate) {
      return labels.dateRequired;
    }
    if (reason.trim() === '') {
      return labels.reasonRequired;
    }
    return null;
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const validationError = validate();
    if (validationError !== null) {
      setSubmitError(validationError);
      return;
    }

    setSubmitting(true);
    setSubmitError(null);
    setSubmitted(false);
    try {
      const response = await apiFetch('/absences', {
        method: 'POST',
        body: JSON.stringify({
          absence_type_id: Number(typeId),
          start_date: startDate,
          end_date: endDate,
          reason: reason.trim(),
        }),
      });
      if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as { message?: string } | null;
        throw new ApiError(payload?.message ?? labels.submitError, response.status, 'SUBMIT_FAILED');
      }
      setSubmitted(true);
      setTypeId('');
      setStartDate('');
      setEndDate('');
      setReason('');
      onSubmitted();
    } catch (err) {
      setSubmitError(err instanceof ApiError ? err.message : labels.submitError);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-lg font-semibold text-slate-900">{labels.newAbsence}</h2>
      <p className="mt-0.5 text-sm text-slate-500">{labels.newAbsenceHint}</p>

      {submitted ? (
        <p className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
          {labels.submittedSnack}
        </p>
      ) : null}

      <form className="mt-4 space-y-4" onSubmit={handleSubmit}>
        <div>
          <label htmlFor="leave-type" className="mb-1 block text-sm font-medium text-slate-700">
            {labels.type}
          </label>
          {balancesLoading ? (
            <p className="text-sm text-slate-500">{labels.balancesLoading}</p>
          ) : types.length === 0 ? (
            <p className="text-sm text-slate-500">{labels.noTypeAvailable}</p>
          ) : (
            <select
              id="leave-type"
              value={typeId}
              onChange={(event) => setTypeId(event.target.value)}
              className="w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <option value="">{labels.typePlaceholder}</option>
              {types.map((type) => (
                <option key={type.id} value={type.id}>
                  {type.name} — {type.available}{labels.daysAvailable}
                </option>
              ))}
            </select>
          )}
        </div>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label htmlFor="leave-start" className="mb-1 block text-sm font-medium text-slate-700">
              {labels.start}
            </label>
            <input
              id="leave-start"
              type="date"
              value={startDate}
              onChange={(event) => setStartDate(event.target.value)}
              className="w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>
          <div>
            <label htmlFor="leave-end" className="mb-1 block text-sm font-medium text-slate-700">
              {labels.end}
            </label>
            <input
              id="leave-end"
              type="date"
              value={endDate}
              min={startDate || undefined}
              onChange={(event) => setEndDate(event.target.value)}
              className="w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>
        </div>

        <div>
          <label htmlFor="leave-reason" className="mb-1 block text-sm font-medium text-slate-700">
            {labels.reason}
          </label>
          <textarea
            id="leave-reason"
            value={reason}
            maxLength={1000}
            onChange={(event) => setReason(event.target.value)}
            placeholder={labels.reasonHint}
            rows={3}
            className="w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        {submitError !== null ? (
          <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {submitError}
          </p>
        ) : null}

        <button
          type="submit"
          disabled={submitting || types.length === 0}
          className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {submitting ? labels.submitting : labels.submitToHr}
        </button>
      </form>
    </section>
  );
}
