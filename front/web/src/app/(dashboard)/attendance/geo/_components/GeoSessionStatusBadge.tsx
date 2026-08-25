import type { ReactElement } from 'react';

export type GeoSessionStatus =
  | 'detected'
  | 'pending_validation'
  | 'approved'
  | 'rejected'
  | 'cancelled';

export type GeoSessionStatusLabels = {
  statusDetected: string;
  statusPendingValidation: string;
  statusApproved: string;
  statusRejected: string;
  statusCancelled: string;
};

type Props = {
  status: GeoSessionStatus | string;
  labels: GeoSessionStatusLabels;
};

const STATUS_CLASSNAMES: Record<string, string> = {
  detected: 'bg-slate-100 text-slate-700 border-slate-200',
  pending_validation: 'bg-amber-50 text-amber-700 border-amber-200',
  approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  rejected: 'bg-red-50 text-red-700 border-red-200',
  cancelled: 'bg-slate-100 text-slate-500 border-slate-200',
};

export function GeoSessionStatusBadge({ status, labels }: Props): ReactElement {
  const statusLabels: Record<string, string> = {
    detected: labels.statusDetected,
    pending_validation: labels.statusPendingValidation,
    approved: labels.statusApproved,
    rejected: labels.statusRejected,
    cancelled: labels.statusCancelled,
  };

  const className = STATUS_CLASSNAMES[status] ?? 'bg-slate-100 text-slate-600 border-slate-200';
  const label = statusLabels[status] ?? status;

  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider ${className}`}
    >
      {label}
    </span>
  );
}
