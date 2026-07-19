import type { ReactElement } from 'react';

export type GeoSessionStatus =
  | 'detected'
  | 'pending_validation'
  | 'approved'
  | 'rejected'
  | 'cancelled';

type Props = {
  status: GeoSessionStatus | string;
};

const STATUS_CONFIG: Record<string, { label: string; className: string }> = {
  detected: {
    label: 'Détecté',
    className: 'bg-slate-100 text-slate-700 border-slate-200',
  },
  pending_validation: {
    label: 'En attente',
    className: 'bg-amber-50 text-amber-700 border-amber-200',
  },
  approved: {
    label: 'Approuvé',
    className: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  },
  rejected: {
    label: 'Refusé',
    className: 'bg-red-50 text-red-700 border-red-200',
  },
  cancelled: {
    label: 'Annulé',
    className: 'bg-slate-100 text-slate-500 border-slate-200',
  },
};

export function GeoSessionStatusBadge({ status }: Props): ReactElement {
  const config = STATUS_CONFIG[status] ?? {
    label: status,
    className: 'bg-slate-100 text-slate-600 border-slate-200',
  };

  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider ${config.className}`}
    >
      {config.label}
    </span>
  );
}
