"use client";

import { useEffect, useState } from "react";
import Link from "next/link";

import { CONFIRMATION_STORAGE_PREFIX } from "@/components/booking-flow";
import { formatMoney } from "@/lib/format";
import { statusLabel } from "@/lib/i18n";
import { useLocale } from "@/lib/locale-provider";
import type { Booking } from "@/lib/types";

type StoredConfirmation = {
  booking: Booking;
  agencyName: string | null;
  trip: {
    origin: string;
    destination: string;
    departure_date: string;
    departure_time: string | null;
  };
};

function formatExpiry(value: string | null | undefined, locale: string): string {
  if (!value) return "—";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat(locale === "fr" ? "fr-FR" : "en-GB", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(parsed);
}

export function ConfirmationContent({ reference }: { reference: string }) {
  const { dict, locale } = useLocale();
  const [stored, setStored] = useState<StoredConfirmation | null>(null);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    try {
      const raw = sessionStorage.getItem(
        `${CONFIRMATION_STORAGE_PREFIX}${reference}`,
      );
      if (raw) setStored(JSON.parse(raw) as StoredConfirmation);
    } catch {
      // sessionStorage indisponible : repli sur le message générique.
    }
    setLoaded(true);
  }, [reference]);

  return (
    <div className="mx-auto max-w-2xl px-4 py-12">
      <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p className="text-5xl" aria-hidden>
          ✅
        </p>
        <h1 className="mt-4 text-2xl font-bold text-slate-900">
          {dict.confirmation.title}
        </h1>

        <div className="mt-6 rounded-xl bg-brand-50 p-5 text-center">
          <p className="text-xs font-semibold uppercase tracking-wide text-brand-700">
            {dict.confirmation.reference}
          </p>
          <p className="mt-1 font-mono text-2xl font-bold tracking-wider text-brand-900">
            {reference}
          </p>
        </div>
        <p className="mt-3 text-sm text-slate-500">{dict.confirmation.keepIt}</p>

        {loaded && stored ? (
          <dl className="mt-6 grid grid-cols-2 gap-4 border-t border-slate-100 pt-6 text-sm">
            <div className="col-span-2">
              <dt className="text-slate-500">{dict.trip.duration}</dt>
              <dd className="font-semibold text-slate-900">
                {stored.trip.origin} → {stored.trip.destination} ·{" "}
                {stored.trip.departure_date}
                {stored.trip.departure_time
                  ? ` · ${stored.trip.departure_time.slice(0, 5)}`
                  : ""}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.status}</dt>
              <dd className="font-semibold text-slate-900">
                {statusLabel(dict, stored.booking.status)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.payment}</dt>
              <dd className="font-semibold text-slate-900">
                {statusLabel(dict, stored.booking.payment_status)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.passengers}</dt>
              <dd className="font-semibold text-slate-900">
                {stored.booking.passenger_count}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.total}</dt>
              <dd className="font-semibold text-slate-900">
                {formatMoney(
                  stored.booking.total_amount_minor,
                  stored.booking.currency,
                  locale,
                )}
              </dd>
            </div>
            {stored.booking.expires_at ? (
              <div>
                <dt className="text-slate-500">{dict.confirmation.expires}</dt>
                <dd className="font-semibold text-red-700">
                  {formatExpiry(stored.booking.expires_at, locale)}
                </dd>
              </div>
            ) : null}
            {stored.agencyName ? (
              <div>
                <dt className="text-slate-500">{dict.confirmation.agency}</dt>
                <dd className="font-semibold text-slate-900">{stored.agencyName}</dd>
              </div>
            ) : null}
          </dl>
        ) : loaded ? (
          <p className="mt-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
            {dict.confirmation.missing}
          </p>
        ) : null}

        <div className="mt-6 rounded-xl border border-brand-200 bg-white p-4 text-sm text-slate-700">
          {dict.confirmation.payNotice}
        </div>

        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
          <Link
            href="/booking"
            className="flex-1 rounded-lg bg-brand-600 px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-brand-700"
          >
            {dict.confirmation.trackCta}
          </Link>
          <Link
            href="/"
            className="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            {dict.confirmation.newSearch}
          </Link>
        </div>
      </div>
    </div>
  );
}
