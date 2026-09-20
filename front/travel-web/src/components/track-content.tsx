"use client";

import { useState } from "react";

import {
  ApiError,
  cancelBooking,
  getTicketPdfUrl,
  trackBooking,
} from "@/lib/api";
import { formatMoney } from "@/lib/format";
import { statusLabel } from "@/lib/i18n";
import { useLocale } from "@/lib/locale-provider";
import type { TrackedBooking } from "@/lib/types";

export function TrackContent() {
  const { dict, locale } = useLocale();

  const [reference, setReference] = useState("");
  const [code, setCode] = useState("");
  const [booking, setBooking] = useState<TrackedBooking | null>(null);
  const [searching, setSearching] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [cancelReason, setCancelReason] = useState("");
  const [cancelling, setCancelling] = useState(false);
  const [cancelledMessage, setCancelledMessage] = useState<string | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);

  const search = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setBooking(null);
    setCancelledMessage(null);
    setCancelError(null);
    setSearching(true);
    try {
      const found = await trackBooking(reference.trim(), code.trim());
      setBooking(found);
    } catch (err) {
      setError(
        err instanceof ApiError && (err.status === 404 || err.status === 422)
          ? dict.track.notFound
          : dict.common.error,
      );
    } finally {
      setSearching(false);
    }
  };

  const cancel = async () => {
    if (!booking || !cancelReason.trim()) return;
    setCancelError(null);
    setCancelling(true);
    try {
      const updated = await cancelBooking(
        reference.trim(),
        code.trim(),
        cancelReason.trim(),
      );
      setBooking(updated);
      setCancelledMessage(dict.track.cancelled);
    } catch {
      setCancelError(dict.track.cancelFailed);
    } finally {
      setCancelling(false);
    }
  };

  const openTicketPdf = async (ticketId: number) => {
    try {
      const url = await getTicketPdfUrl(ticketId, code.trim());
      if (url) window.open(url, "_blank", "noopener,noreferrer");
    } catch {
      setError(dict.common.error);
    }
  };

  const isCancellable =
    booking !== null &&
    booking.status !== "cancelled" &&
    booking.status !== "canceled";

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <h1 className="text-2xl font-bold text-slate-900">{dict.track.title}</h1>
      <p className="mt-2 text-sm text-slate-500">{dict.track.subtitle}</p>

      <form
        onSubmit={search}
        className="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
          {dict.track.reference}
          <input
            type="text"
            required
            value={reference}
            maxLength={40}
            onChange={(event) => setReference(event.target.value)}
            className="h-11 rounded-lg border border-slate-300 px-3 font-mono text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
          />
        </label>

        <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
          {dict.track.code}
          <input
            type="text"
            required
            value={code}
            onChange={(event) => setCode(event.target.value)}
            className="h-11 rounded-lg border border-slate-300 px-3 font-mono text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
          />
          <span className="text-[11px] font-normal normal-case tracking-normal text-slate-400">
            {dict.track.codeHint}
          </span>
        </label>

        {error ? (
          <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {error}
          </p>
        ) : null}

        <button
          type="submit"
          disabled={searching}
          className="h-11 rounded-lg bg-brand-600 px-6 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60"
        >
          {searching ? dict.track.searching : dict.track.submit}
        </button>
      </form>

      {booking ? (
        <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">
            {dict.track.resultTitle}
          </h2>

          {cancelledMessage ? (
            <p className="mt-3 rounded-lg bg-brand-50 p-3 text-sm text-brand-800">
              {cancelledMessage}
            </p>
          ) : null}

          <dl className="mt-4 grid grid-cols-2 gap-4 text-sm">
            <div>
              <dt className="text-slate-500">{dict.confirmation.reference}</dt>
              <dd className="font-mono font-semibold text-slate-900">
                {booking.reference}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.status}</dt>
              <dd className="font-semibold text-slate-900">
                {statusLabel(dict, booking.status)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.payment}</dt>
              <dd className="font-semibold text-slate-900">
                {statusLabel(dict, booking.payment_status)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.passengers}</dt>
              <dd className="font-semibold text-slate-900">
                {booking.passenger_count}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{dict.confirmation.total}</dt>
              <dd className="font-semibold text-slate-900">
                {formatMoney(booking.total_amount_minor, booking.currency, locale)}
              </dd>
            </div>
          </dl>

          {booking.tickets && booking.tickets.length > 0 ? (
            <div className="mt-5 border-t border-slate-100 pt-4">
              <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                {dict.track.tickets}
              </h3>
              <ul className="mt-2 flex flex-col gap-2">
                {booking.tickets.map((ticket) => (
                  <li
                    key={ticket.id}
                    className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  >
                    <span className="font-mono text-slate-800">
                      {ticket.ticket_number}
                    </span>
                    <button
                      type="button"
                      onClick={() => openTicketPdf(ticket.id)}
                      className="font-semibold text-brand-700 hover:text-brand-800"
                    >
                      {dict.track.downloadPdf}
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {isCancellable ? (
            <div className="mt-5 border-t border-slate-100 pt-4">
              <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                {dict.track.cancelTitle}
              </h3>
              <label className="mt-2 flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {dict.track.cancelReason}
                <input
                  type="text"
                  value={cancelReason}
                  maxLength={255}
                  onChange={(event) => setCancelReason(event.target.value)}
                  className="h-10 rounded-lg border border-slate-300 px-3 text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                />
              </label>
              {cancelError ? (
                <p role="alert" className="mt-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                  {cancelError}
                </p>
              ) : null}
              <button
                type="button"
                onClick={cancel}
                disabled={cancelling || !cancelReason.trim()}
                className="mt-3 rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-50"
              >
                {cancelling ? dict.track.cancelling : dict.track.cancelSubmit}
              </button>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
