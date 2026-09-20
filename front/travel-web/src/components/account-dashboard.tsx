"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";

import { fetchMyBookings } from "@/lib/api";
import { useAccount } from "@/lib/account-provider";
import { formatDate, formatMoney, formatTime } from "@/lib/format";
import { statusLabel } from "@/lib/i18n";
import { useLocale } from "@/lib/locale-provider";
import type { CustomerBooking } from "@/lib/types";

/**
 * Tableau de bord du compte client (issue #7739) : profil, déconnexion et
 * « mes réservations » cross-agences (bornées au compte côté backend).
 */
export function AccountDashboard() {
  const { dict, locale } = useLocale();
  const { ready, account, token, logout } = useAccount();
  const router = useRouter();
  const searchParams = useSearchParams();

  const claimedParam = Number(searchParams.get("claimed") ?? "0");
  const claimed = Number.isFinite(claimedParam) && claimedParam > 0 ? claimedParam : 0;

  const [bookings, setBookings] = useState<CustomerBooking[] | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [loggingOut, setLoggingOut] = useState(false);

  // Sans session résolue → redirection vers la connexion.
  useEffect(() => {
    if (ready && !account) {
      router.replace("/account/login");
    }
  }, [ready, account, router]);

  useEffect(() => {
    if (!token) return;

    let cancelled = false;
    fetchMyBookings(token)
      .then((payload) => {
        if (!cancelled) setBookings(payload.data);
      })
      .catch(() => {
        if (!cancelled) setLoadError(dict.account.loadError);
      });

    return () => {
      cancelled = true;
    };
  }, [token, dict.account.loadError]);

  if (!ready || !account) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-16 text-center text-sm text-slate-500">
        {dict.common.loading}
      </div>
    );
  }

  const doLogout = async () => {
    setLoggingOut(true);
    await logout();
    router.push("/");
  };

  return (
    <div className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8">
      {claimed > 0 ? (
        <p className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {claimed} {claimed === 1 ? dict.account.claimedOne : dict.account.claimedMany}
        </p>
      ) : null}

      {/* ── Profil ── */}
      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold text-slate-900">{dict.account.dashboardTitle}</h1>
            <p className="mt-1 font-medium text-slate-800">{account.name}</p>
            <p className="text-sm text-slate-600">{account.email}</p>
            {account.phone ? <p className="text-sm text-slate-600">{account.phone}</p> : null}
            {account.created_at ? (
              <p className="mt-1 text-xs text-slate-400">
                {dict.account.memberSince} {formatDate(account.created_at.slice(0, 10), locale)}
              </p>
            ) : null}
          </div>
          <button
            type="button"
            onClick={doLogout}
            disabled={loggingOut}
            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 disabled:opacity-60"
          >
            {loggingOut ? dict.account.loggingOut : dict.account.logout}
          </button>
        </div>
      </section>

      {/* ── Mes réservations ── */}
      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">{dict.account.myBookings}</h2>
        <p className="mt-1 text-sm text-slate-500">{dict.account.myBookingsHint}</p>

        {loadError ? (
          <p role="alert" className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
            {loadError}
          </p>
        ) : bookings === null ? (
          <p className="mt-4 text-sm text-slate-500">{dict.common.loading}</p>
        ) : bookings.length === 0 ? (
          <div className="mt-4 rounded-xl border border-dashed border-slate-300 p-6 text-center">
            <p className="text-sm font-medium text-slate-700">{dict.account.noBookings}</p>
            <p className="mt-1 text-sm text-slate-500">{dict.account.noBookingsHint}</p>
            <Link
              href="/"
              className="mt-3 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700"
            >
              {dict.account.searchCta}
            </Link>
          </div>
        ) : (
          <ul className="mt-4 flex flex-col gap-3">
            {bookings.map((booking) => (
              <li
                key={booking.reference}
                className="rounded-xl border border-slate-200 p-4"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="font-semibold text-slate-900">
                      {booking.trip
                        ? `${booking.trip.origin_city ?? "—"} → ${booking.trip.destination_city ?? "—"}`
                        : booking.reference}
                    </p>
                    <p className="text-sm text-slate-600">
                      {booking.trip
                        ? `${formatDate(booking.trip.departure_date, locale)} · ${formatTime(booking.trip.departure_time)}`
                        : null}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="font-semibold text-slate-900">
                      {formatMoney(booking.total_amount_minor, booking.currency, locale)}
                    </p>
                    <p className="text-sm text-slate-500">
                      {statusLabel(dict, booking.status)} · {statusLabel(dict, booking.payment_status)}
                    </p>
                  </div>
                </div>
                <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                  <span className="font-mono text-slate-700">{booking.reference}</span>
                  {booking.agency.name ? (
                    <span>
                      {dict.account.agency} : {booking.agency.name}
                    </span>
                  ) : null}
                  <span>
                    {booking.passenger_count} {dict.account.passengers}
                  </span>
                  {booking.created_at ? (
                    <span>
                      {dict.account.bookedOn} {formatDate(booking.created_at.slice(0, 10), locale)}
                    </span>
                  ) : null}
                  {booking.tickets.length > 0 ? (
                    <span>
                      {dict.account.tickets} :{" "}
                      {booking.tickets.map((t) => t.ticket_number).join(", ")}
                    </span>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
