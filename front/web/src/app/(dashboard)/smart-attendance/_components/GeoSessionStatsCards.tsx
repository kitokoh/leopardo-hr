type StatsCardProps = {
  title: string;
  value: number | string;
  icon: string;
  colorClass: string;
  bgClass: string;
};

function StatsCard({ title, value, icon, colorClass, bgClass }: StatsCardProps) {
  return (
    <div className={`rounded-2xl border bg-white p-5 shadow-sm ${bgClass}`}>
      <div className="flex items-center justify-between">
        <p className="text-xs font-bold uppercase tracking-widest text-slate-500">{title}</p>
        <span className={`text-2xl ${colorClass}`}>{icon}</span>
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
      icon: '📋',
      colorClass: 'text-slate-700',
      bgClass: 'border-slate-200',
    },
    {
      title: 'Détectés',
      value: loading ? '...' : stats.detected,
      icon: '📍',
      colorClass: 'text-blue-700',
      bgClass: 'border-blue-100',
    },
    {
      title: 'En attente',
      value: loading ? '...' : stats.pending_validation,
      icon: '⏳',
      colorClass: 'text-amber-700',
      bgClass: 'border-amber-100',
    },
    {
      title: 'Approuvés',
      value: loading ? '...' : stats.approved,
      icon: '✅',
      colorClass: 'text-emerald-700',
      bgClass: 'border-emerald-100',
    },
    {
      title: 'Refusés',
      value: loading ? '...' : stats.rejected,
      icon: '❌',
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
