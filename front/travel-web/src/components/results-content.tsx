"use client";

import { SearchForm } from "@/components/search-form";
import { TripCard } from "@/components/trip-card";
import { useLocale } from "@/lib/locale-provider";
import type { MarketplaceTrip, SearchMeta } from "@/lib/types";

type Props = {
  trips: MarketplaceTrip[];
  meta: SearchMeta | null;
  backendDown: boolean;
  initialOrigin: string;
  initialDestination: string;
  initialDate: string;
};

export function ResultsContent({
  trips,
  meta,
  backendDown,
  initialOrigin,
  initialDestination,
  initialDate,
}: Props) {
  const { dict } = useLocale();
  const total = meta?.total ?? trips.length;

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8">
      <SearchForm
        initialOrigin={initialOrigin}
        initialDestination={initialDestination}
        initialDate={initialDate}
      />

      <div className="flex items-baseline justify-between">
        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">
          {dict.results.title}
        </h1>
        {!backendDown ? (
          <p className="text-sm text-slate-500">
            {total} {total === 1 ? dict.results.countOne : dict.results.countMany}
          </p>
        ) : null}
      </div>

      {backendDown ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
          {dict.results.backendDown}
        </div>
      ) : trips.length === 0 ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-10 text-center">
          <p className="font-medium text-slate-700">{dict.results.empty}</p>
          <p className="mt-1 text-sm text-slate-500">{dict.results.emptyHint}</p>
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          {trips.map((trip) => (
            <TripCard key={trip.id} trip={trip} />
          ))}
        </div>
      )}
    </div>
  );
}
