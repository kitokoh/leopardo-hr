'use client';

import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';

/**
 * Shape d'un bulletin détaillé — GET /pay-slips/{id} (PaySlipResource).
 * Les champs optionnels couvrent aussi le repli sur les données de ligne
 * déjà chargées dans la liste (fallback si le détail échoue).
 */
export type PaySlipDetailLine = {
  id: number;
  name: string;
  type: string;
  base_amount: number;
  rate: number;
  amount: number;
  order: number;
};

export type PaySlipDetail = {
  id: number;
  employee_id: number;
  employee_name?: string;
  employee?: { id: number; first_name: string; last_name: string; email: string } | null;
  period?: string;
  period_start?: string;
  period_end?: string;
  gross_salary: number;
  net_salary: number;
  status: string;
  currency?: string;
  total_deductions?: number;
  employer_contributions?: number;
  total_cost?: number;
  working_days?: number;
  actual_days_worked?: number;
  overtime_hours?: number;
  lines?: PaySlipDetailLine[];
  created_at?: string;
};

/** Sous-ensemble des clés payrollPage consommées par le modal. */
export type PaySlipDetailModalLabels = {
  detailTitle: string;
  detailClose: string;
  detailLoading: string;
  detailError: string;
  columnEmployee: string;
  columnPeriod: string;
  columnGross: string;
  columnNet: string;
  detailDeductions: string;
  detailEmployerContributions: string;
  detailTotalCost: string;
  detailWorkingDays: string;
  detailDaysWorked: string;
  detailOvertimeHours: string;
  detailSalaryBreakdown: string;
  statusValidated: string;
  statusDraft: string;
};

type Props = {
  slip: PaySlipDetail | null;
  loading: boolean;
  error: boolean;
  labels: PaySlipDetailModalLabels;
  formatCurrency: (value: number) => string;
  onClose: () => void;
};

export function PaySlipDetailModal({ slip, loading, error, labels, formatCurrency, onClose }: Props) {
  const panelRef = useRef<HTMLDivElement>(null);

  // ESC pour fermer + verrouillage du scroll tant que le modal est ouvert.
  useEffect(() => {
    if (!slip) {
      return;
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [slip, onClose]);

  // Focus initial sur le panneau pour la navigation clavier.
  useEffect(() => {
    if (slip) {
      panelRef.current?.focus();
    }
  }, [slip]);

  if (!slip) {
    return null;
  }

  const employeeName = slip.employee
    ? `${slip.employee.first_name ?? ''} ${slip.employee.last_name ?? ''}`.trim()
    : slip.employee_name;
  const period = slip.period_start && slip.period_end
    ? `${slip.period_start} → ${slip.period_end}`
    : slip.period;
  const isValidated = slip.status === 'validated';

  const details: Array<{ label: string; value: ReactNode }> = [];
  if (typeof slip.total_deductions === 'number') {
    details.push({ label: labels.detailDeductions, value: formatCurrency(slip.total_deductions) });
  }
  if (typeof slip.employer_contributions === 'number') {
    details.push({ label: labels.detailEmployerContributions, value: formatCurrency(slip.employer_contributions) });
  }
  if (typeof slip.total_cost === 'number') {
    details.push({ label: labels.detailTotalCost, value: formatCurrency(slip.total_cost) });
  }
  if (typeof slip.working_days === 'number') {
    details.push({ label: labels.detailWorkingDays, value: slip.working_days });
  }
  if (typeof slip.actual_days_worked === 'number') {
    details.push({ label: labels.detailDaysWorked, value: slip.actual_days_worked });
  }
  if (typeof slip.overtime_hours === 'number') {
    details.push({ label: labels.detailOvertimeHours, value: slip.overtime_hours });
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-label={labels.detailTitle}
      onClick={onClose}
    >
      <div
        ref={panelRef}
        tabIndex={-1}
        onClick={(event) => event.stopPropagation()}
        className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-app-border bg-white shadow-xl outline-none"
      >
        <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
          <h2 className="text-lg font-black text-slate-950">{labels.detailTitle}</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label={labels.detailClose}
            className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="px-6 py-5">
          {loading ? (
            <p className="py-8 text-center text-sm text-slate-500">{labels.detailLoading}</p>
          ) : (
            <>
              {error && (
                <p className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-700">
                  {labels.detailError}
                </p>
              )}

              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                  <p className="text-sm text-slate-500">{labels.columnEmployee}</p>
                  <p className="text-lg font-black text-slate-950">{employeeName}</p>
                </div>
                <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${isValidated ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                  {isValidated ? labels.statusValidated : labels.statusDraft}
                </span>
              </div>

              <p className="mt-3 text-sm text-slate-500">
                {labels.columnPeriod} : <span className="font-bold text-slate-900">{period}</span>
              </p>

              <div className="mt-5 grid grid-cols-2 gap-3">
                <div className="rounded-2xl border border-app-border bg-transparent p-4">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnNet}</p>
                  <p className="mt-1 text-2xl font-black tabular-nums text-emerald-600">{formatCurrency(slip.net_salary)}</p>
                </div>
                <div className="rounded-2xl border border-app-border bg-transparent p-4">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnGross}</p>
                  <p className="mt-1 text-2xl font-black tabular-nums text-slate-950">{formatCurrency(slip.gross_salary)}</p>
                </div>
              </div>

              {details.length > 0 && (
                <div className="mt-5">
                  <div className="divide-y divide-app-border rounded-2xl border border-app-border">
                    {details.map((detail) => (
                      <div key={detail.label} className="flex items-center justify-between px-4 py-2.5 text-sm">
                        <span className="text-slate-600">{detail.label}</span>
                        <span className="font-bold tabular-nums text-slate-950">{detail.value}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {Array.isArray(slip.lines) && slip.lines.length > 0 && (
                <div className="mt-5">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500">{labels.detailSalaryBreakdown}</h3>
                  <div className="mt-2 overflow-hidden rounded-2xl border border-app-border">
                    <table className="w-full text-sm">
                      <tbody className="divide-y divide-app-border">
                        {slip.lines.map((line) => (
                          <tr key={line.id}>
                            <td className="px-4 py-2.5 text-slate-700">{line.name}</td>
                            <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">
                              {formatCurrency(line.amount)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
