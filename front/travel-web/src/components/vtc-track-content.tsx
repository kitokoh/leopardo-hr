"use client";

import { useSearchParams } from "next/navigation";
import { useState } from "react";

import { ApiError, trackVtcRide } from "@/lib/api";
import { formatMoney } from "@/lib/format";
import { vtcStatusLabel } from "@/lib/i18n";
import { useLocale } from "@/lib/locale-provider";
import type { VtcRideTracking } from "@/lib/types";

/**
 * #8423 (M-30) — suivi invité d'une course VTC : référence + code de suivi
 * (remis une seule fois à la réservation) → statut lisible. DTO strict
 * côté API : JAMAIS de position chauffeur sur cette surface ; les 404/422
 * sont uniformes (anti-énumération — pas de distinction « mauvais code » /
 * « référence inconnue »). Les paramètres d'URL ?reference=&code=
 * pré-remplissent le formulaire (lien remis à la réservation).
 */
export function VtcTrackContent() {
  const { dict, locale } = useLocale();
  const searchParams = useSearchParams();

  const [reference, setReference] = useState(searchParams.get("reference") ?? "");
  const [code, setCode] = useState(searchParams.get("code") ?? "");
  const [ride, setRide] = useState<VtcRideTracking | null>(null);
  const [searching, setSearching] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const search = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setRide(null);
    setSearching(true);
    try {
      const found = await trackVtcRide(reference.trim(), code.trim());
      setRide(found);
    } catch (err) {
      setError(
        err instanceof ApiError && (err.status === 404 || err.status === 422)
          ? dict.vtc.trackNotFound
          : dict.vtc.errorGeneric,
      );
    } finally {
      setSearching(false);
    }
  };

  const requestedAt = ride?.requested_at ? new Date(ride.requested_at) : null;
  const requestedLabel =
    requestedAt !== null && !Number.isNaN(requestedAt.getTime())
      ? new Intl.DateTimeFormat(locale === "fr" ? "fr-FR" : "en-GB", {
          dateStyle: "medium",
          timeStyle: "short",
        }).format(requestedAt)
      : "—";

  const inputClass =
    "h-11 rounded-lg border border-slate-300 px-3 font-mono text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200";
  const labelClass =
    "flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500";

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <h1 className="text-2xl font-bold text-slate-900">{dict.vtc.trackTitle}</h1>
      <p className="mt-2 text-sm text-slate-500">{dict.vtc.trackSubtitle}</p>

      <form
        onSubmit={search}
        className="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        <label className={labelClass}>
          {dict.vtc.trackReference}
          <input
            type="text"
            required
            value={reference}
            maxLength={40}
            onChange={(event) => setReference(event.target.value)}
            className={inputClass}
          />
        </label>

        <label className={labelClass}>
          {dict.vtc.trackCode}
          <input
            type="text"
            required
            value={code}
            maxLength={16}
            onChange={(event) => setCode(event.target.value)}
            className={inputClass}
          />
          <span className="text-[11px] font-normal normal-case tracking-normal text-slate-400">
            {dict.vtc.trackCodeHint}
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
          className="h-11 rounded-lg bg-brand-600 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {searching ? dict.vtc.trackSearching : dict.vtc.trackSubmit}
        </button>
      </form>

      {ride !== null && (
        <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-lg font-semibold text-slate-900">
              {dict.vtc.trackResultTitle}
            </h2>
            <span className="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
              {vtcStatusLabel(dict, ride.status)}
            </span>
          </div>

          <dl className="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div className="rounded-xl bg-slate-50 p-3">
              <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {dict.vtc.referenceLabel}
              </dt>
              <dd className="mt-1 font-mono font-semibold text-slate-900">
                {ride.reference}
              </dd>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {dict.vtc.requestedAtLabel}
              </dt>
              <dd className="mt-1 font-semibold text-slate-900">{requestedLabel}</dd>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {dict.vtc.etaLabel}
              </dt>
              <dd className="mt-1 font-semibold text-slate-900">
                {ride.estimated_duration_s !== null
                  ? `${Math.max(1, Math.round(ride.estimated_duration_s / 60))} min`
                  : "—"}
              </dd>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {dict.vtc.priceLabel}
              </dt>
              <dd className="mt-1 font-semibold text-slate-900">
                {ride.estimated_price_minor !== null
                  ? formatMoney(ride.estimated_price_minor, ride.currency, locale)
                  : dict.vtc.priceOnRequest}
              </dd>
            </div>
            {ride.pickup_address ? (
              <div className="rounded-xl bg-slate-50 p-3">
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  {dict.vtc.pickupAddress}
                </dt>
                <dd className="mt-1 font-semibold text-slate-900">
                  {ride.pickup_address}
                </dd>
              </div>
            ) : null}
            {ride.dropoff_address ? (
              <div className="rounded-xl bg-slate-50 p-3">
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  {dict.vtc.dropoffAddress}
                </dt>
                <dd className="mt-1 font-semibold text-slate-900">
                  {ride.dropoff_address}
                </dd>
              </div>
            ) : null}
          </dl>
        </section>
      )}
    </div>
  );
}
