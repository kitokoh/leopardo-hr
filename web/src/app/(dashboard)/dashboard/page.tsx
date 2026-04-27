'use client';

import { useEffect, useMemo, useState } from 'react';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';

export default function DashboardPage() {
  const [locale, setLocale] = useState<AppLocale>('fr');
  const labels = useMemo(() => getCopy(locale), [locale]);

  useEffect(() => {
    setLocale(getPreferredLocale());
  }, []);

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium uppercase text-gray-500">{labels.dashboard.employees}</h3>
          <p className="mt-2 text-3xl font-bold text-gray-900">24</p>
        </div>
        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium uppercase text-gray-500">{labels.dashboard.present}</h3>
          <p className="mt-2 text-3xl font-bold text-green-600">18</p>
        </div>
        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium uppercase text-gray-500">{labels.dashboard.late}</h3>
          <p className="mt-2 text-3xl font-bold text-orange-600">2</p>
        </div>
      </div>

      <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 className="mb-4 text-lg font-semibold text-gray-800">{labels.dashboard.activity}</h3>
        <div className="space-y-4">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="flex items-center justify-between border-b border-gray-50 py-2 last:border-0">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 font-bold text-slate-600">
                  E{i}
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-900">{labels.dashboard.employeeLabel} #{i}</p>
                  <p className="text-xs text-gray-500">{labels.dashboard.checkInAt} 08:{30 + i}</p>
                </div>
              </div>
              <span className="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                {labels.dashboard.presentBadge}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
