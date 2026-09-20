"use client";

import Link from "next/link";

import { cityLabel, formatDate, formatMoney, formatTime } from "@/lib/format";
import { useLocale } from "@/lib/locale-provider";
import type { MarketplaceTrip } from "@/lib/types";

export function TripCard({ trip }: { trip: MarketplaceTrip }) {
  const { dict, locale } = useLocale();
  const soldOut = trip.available_seats <= 0;

  return (
    <article className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center sm:justify-between">
      <div className="flex flex-1 flex-col gap-2">
        <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
          <span className="text-lg font-semibold text-slate-900">
            {formatTime(trip.departure_time)}
          </span>
          <span className="font-medium text-slate-700">
            {cityLabel(trip.origin_city)}
          </span>
          <span aria-hidden className="text-slate-400">
            →
          </span>
          <span className="text-lg font-semibold text-slate-900">
            {formatTime(trip.arrival_time)}
          </span>
          <span className="font-medium text-slate-700">
            {cityLabel(trip.destination_city)}
          </span>
        </div>
        <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
          <span>{formatDate(trip.departure_date, locale)}</span>
          {trip.agency.name ? (
            <span>
              {dict.results.agency} : {trip.agency.name}
            </span>
          ) : null}
          {trip.means_of_transport ? <span>{trip.means_of_transport}</span> : null}
          <span className={soldOut ? "font-medium text-red-600" : "text-brand-700"}>
            {soldOut
              ? dict.results.soldOut
              : `${trip.available_seats} ${
                  trip.available_seats === 1
                    ? dict.results.seatLeft
                    : dict.results.seatsLeft
                }`}
          </span>
        </div>
      </div>

      <div className="flex items-center justify-between gap-4 sm:flex-col sm:items-end">
        <p className="text-sm text-slate-500">
          {dict.results.from}{" "}
          <span className="text-lg font-bold text-slate-900">
            {formatMoney(trip.price_from_minor, trip.currency, locale)}
          </span>
        </p>
        <Link
          href={`/trips/${trip.id}`}
          className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700"
        >
          {dict.results.select}
        </Link>
      </div>
    </article>
  );
}
