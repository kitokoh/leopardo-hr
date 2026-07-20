import { CheckCircle2, ClipboardList, Hourglass, MapPin, XCircle } from 'lucide-react';

type StatsCardProps = {
  title: string;
  value: number | string;
  icon: typeof ClipboardList;
  colorClass: string;
  bgClass: string;
};

function StatsCard({ title, value, icon: Icon, colorClass, bgClass }: StatsCardProps) {
  return (
    <div className={`rounded-2xl border bg-white p-5 shadow-sm ${bgClass}`}>
      <div className="flex items-center justify-between">
        <p className="text-xs font-bold uppercase tracking-widest text-slate-500">{title}</p>
        <Icon className={`h-5 w-5 ${colorClass}`} aria-hidden="true" />
      </div>
      <p className={`mt-3 text-3xl font-black ${colorClass}`}>{value}</p>
    </div>
  );
}

export type DashboardStats = {
  total: number;
  detected: number;
  pending_validation: number;
  approved: number;
  rejected: number;
};

type Props = {
  stats: DashboardStats;
  loading?: boolean;
};

export function GeoSessionStatsCards({ stats, loading = false }: Props) {
  const cards: StatsCardProps[] = [
    {
      title: 'Total',
      value: loading ? '...' : stats.total,
      icon: ClipboardList,
      colorClass: 'text-slate-700',
      bgClass: 'border-slate-200',
    },
    {
      title: 'Détectés',
      value: loading ? '...' : stats.detected,
      icon: MapPin,
      colorClass: 'text-security-dark',
      bgClass: 'border-security-light',
    },
    {
      title: 'En attente',
      value: loading ? '...' : stats.pending_validation,
      icon: Hourglass,
      colorClass: 'text-amber-700',
      bgClass: 'border-amber-100',
    },
    {
      title: 'Approuvés',
      value: loading ? '...' : stats.approved,
      icon: CheckCircle2,
      colorClass: 'text-emerald-700',
      bgClass: 'border-emerald-100',
    },
    {
      title: 'Refusés',
      value: loading ? '...' : stats.rejected,
      icon: XCircle,
      colorClass: 'text-red-700',
      bgClass: 'border-red-100',
    },
  ];

  return (
    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
      {cards.map((card) => (
        <StatsCard key={card.title} {...card} />
      ))}
    </section>
  );
}
