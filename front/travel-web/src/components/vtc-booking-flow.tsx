"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import {
  ApiError,
  createVtcRide,
  estimateVtcRide,
  fetchVtcOperators,
} from "@/lib/api";
import { formatMoney } from "@/lib/format";
import { useLocale } from "@/lib/locale-provider";
import type {
  VtcEstimate,
  VtcGeoPointInput,
  VtcGuestRideCreated,
  VtcOperator,
} from "@/lib/types";

/**
 * #8423 (M-30) — parcours public « VTC & Taxi » : opérateur → points
 * (géolocalisation ou coordonnées collées) → devis → réservation invitée →
 * remise du code de suivi (présenté UNE SEULE FOIS) → page de suivi.
 *
 * Surface 100 % sans compte : la réservation est idempotente (clé UUID
 * générée à l'affichage du devis, stable pendant les retries, régénérée à
 * chaque nouveau devis — un double tap ne crée JAMAIS deux courses).
 * Le géocodage d'adresses est hors scope (issue dédiée) : les adresses
 * restent des libellés libres, les coordonnées se remplissent par
 * géolocalisation ou à la main.
 */

type Step = "form" | "estimate" | "success";
type PointField = "pickup" | "dropoff";

type PointState = {
  lat: string;
  lng: string;
};

const EMPTY_POINT: PointState = { lat: "", lng: "" };

function parsePoint(point: PointState): VtcGeoPointInput | null {
  if (point.lat.trim() === "" || point.lng.trim() === "") return null;
  const lat = Number(point.lat);
  const lng = Number(point.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
  return { lat, lng };
}

/** UUID v4 — crypto.randomUUID, avec repli RFC 4122 (contexte non sécurisé). */
function newIdempotencyKey(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID();
  }
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (char) => {
    const random = (Math.random() * 16) | 0;
    const value = char === "x" ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
}

function formatDurationSeconds(seconds: number | null): string {
  if (seconds === null) return "—";
  return `${Math.max(1, Math.round(seconds / 60))} min`;
}

function formatDistanceMeters(meters: number | null): string {
  if (meters === null) return "—";
  return `${(meters / 1000).toFixed(1)} km`;
}

export function VtcBookingFlow() {
  const { dict, locale } = useLocale();

  const [operators, setOperators] = useState<VtcOperator[] | null>(null);
  const [operatorsError, setOperatorsError] = useState(false);

  const [operatorSlug, setOperatorSlug] = useState("");
  const [pickupAddress, setPickupAddress] = useState("");
  const [dropoffAddress, setDropoffAddress] = useState("");
  const [pickup, setPickup] = useState<PointState>(EMPTY_POINT);
  const [dropoff, setDropoff] = useState<PointState>(EMPTY_POINT);

  const [locating, setLocating] = useState<PointField | null>(null);
  const [locationMessage, setLocationMessage] = useState<string | null>(null);

  const [step, setStep] = useState<Step>("form");
  const [estimating, setEstimating] = useState(false);
  const [estimateError, setEstimateError] = useState<string | null>(null);
  const [quote, setQuote] = useState<VtcEstimate | null>(null);

  const [passengerName, setPassengerName] = useState("");
  const [passengerPhone, setPassengerPhone] = useState("");
  const [idempotencyKey, setIdempotencyKey] = useState<string>("");
  const [booking, setBooking] = useState(false);
  const [bookingError, setBookingError] = useState<string | null>(null);
  const [ride, setRide] = useState<VtcGuestRideCreated | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchVtcOperators()
      .then((list) => {
        if (cancelled) return;
        setOperators(list);
        const first = list[0];
        if (list.length === 1 && first) {
          setOperatorSlug((current) => current || first.slug);
        }
      })
      .catch(() => {
        if (!cancelled) setOperatorsError(true);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const locate = (target: PointField) => {
    setLocationMessage(null);
    if (!("geolocation" in navigator)) {
      setLocationMessage(dict.vtc.locationUnsupported);
      return;
    }
    setLocating(target);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const point = {
          lat: position.coords.latitude.toFixed(6),
          lng: position.coords.longitude.toFixed(6),
        };
        if (target === "pickup") {
          setPickup(point);
        } else {
          setDropoff(point);
        }
        setLocating(null);
      },
      () => {
        setLocationMessage(dict.vtc.locationError);
        setLocating(null);
      },
      { enableHighAccuracy: true, timeout: 10_000, maximumAge: 30_000 },
    );
  };

  const estimate = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setEstimateError(null);

    const pickupPoint = parsePoint(pickup);
    const dropoffPoint = parsePoint(dropoff);

    if (operatorSlug === "" || !pickupPoint || !dropoffPoint) {
      setEstimateError(dict.common.required);
      return;
    }

    setEstimating(true);
    try {
      const found = await estimateVtcRide({
        operator: operatorSlug,
        pickup: pickupPoint,
        dropoff: dropoffPoint,
      });
      setQuote(found);
      // Nouvelle intention de course → nouvelle clé d'idempotence (les
      // retries du MÊME devis la conservent — jamais de doublon).
      setIdempotencyKey(newIdempotencyKey());
      setStep("estimate");
    } catch (err) {
      setEstimateError(
        err instanceof ApiError && (err.status === 404 || err.status === 422)
          ? dict.common.required
          : dict.vtc.errorGeneric,
      );
    } finally {
      setEstimating(false);
    }
  };

  const book = async () => {
    const pickupPoint = parsePoint(pickup);
    const dropoffPoint = parsePoint(dropoff);
    if (operatorSlug === "" || !quote || !pickupPoint || !dropoffPoint) return;
    if (passengerName.trim() === "" || passengerPhone.trim() === "") {
      setBookingError(dict.common.required);
      return;
    }

    setBookingError(null);
    setBooking(true);
    try {
      const created = await createVtcRide({
        operator: operatorSlug,
        idempotency_key: idempotencyKey,
        pickup: pickupPoint,
        dropoff: dropoffPoint,
        pickup_address: pickupAddress.trim() || undefined,
        dropoff_address: dropoffAddress.trim() || undefined,
        passenger_name: passengerName.trim(),
        passenger_phone: passengerPhone.trim(),
      });
      setRide(created);
      setStep("success");
    } catch {
      setBookingError(dict.vtc.errorGeneric);
    } finally {
      setBooking(false);
    }
  };

  const resetFlow = () => {
    setStep("form");
    setQuote(null);
    setRide(null);
    setBookingError(null);
    setEstimateError(null);
    setIdempotencyKey("");
  };

  const inputClass =
    "h-11 rounded-lg border border-slate-300 px-3 text-sm font-normal text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200";
  const labelClass =
    "flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500";

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <h1 className="text-2xl font-bold text-slate-900">{dict.vtc.title}</h1>
      <p className="mt-2 text-sm text-slate-500">{dict.vtc.subtitle}</p>

      {step === "form" && (
        <form
          onSubmit={estimate}
          className="mt-6 flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          {operatorsError ? (
            <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
              {dict.vtc.operatorsError}
            </p>
          ) : operators === null ? (
            <p className="text-sm text-slate-500">{dict.vtc.operatorsLoading}</p>
          ) : operators.length === 0 ? (
            <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
              {dict.vtc.operatorsEmpty}
            </p>
          ) : (
            <label className={labelClass}>
              {dict.vtc.operatorLabel}
              <select
                required
                value={operatorSlug}
                onChange={(event) => setOperatorSlug(event.target.value)}
                className={inputClass}
              >
                <option value="" disabled>
                  {dict.vtc.operatorPlaceholder}
                </option>
                {operators.map((operator) => (
                  <option key={operator.slug} value={operator.slug}>
                    {operator.name ?? operator.slug}
                    {operator.city ? ` — ${operator.city}` : ""}
                  </option>
                ))}
              </select>
            </label>
          )}

          <fieldset className="flex flex-col gap-4">
            <legend className="text-xs font-semibold uppercase tracking-wide text-slate-500">
              {dict.vtc.journeyTitle}
            </legend>

            <label className={labelClass}>
              {dict.vtc.pickupAddress}
              <input
                type="text"
                value={pickupAddress}
                maxLength={200}
                placeholder={dict.vtc.pickupAddressPlaceholder}
                onChange={(event) => setPickupAddress(event.target.value)}
                className={inputClass}
              />
            </label>

            <PointInputs
              point={pickup}
              onChange={setPickup}
              onLocate={() => locate("pickup")}
              locating={locating === "pickup"}
              locateLabel={dict.vtc.useMyLocationPickup}
              locatingLabel={dict.vtc.locating}
              coordsTitle={dict.vtc.coordsTitle}
              coordsHint={dict.vtc.coordsHint}
              latLabel={dict.vtc.latLabel}
              lngLabel={dict.vtc.lngLabel}
              inputClass={inputClass}
            />

            <label className={labelClass}>
              {dict.vtc.dropoffAddress}
              <input
                type="text"
                value={dropoffAddress}
                maxLength={200}
                placeholder={dict.vtc.dropoffAddressPlaceholder}
                onChange={(event) => setDropoffAddress(event.target.value)}
                className={inputClass}
              />
            </label>

            <PointInputs
              point={dropoff}
              onChange={setDropoff}
              onLocate={() => locate("dropoff")}
              locating={locating === "dropoff"}
              locateLabel={dict.vtc.useMyLocationDropoff}
              locatingLabel={dict.vtc.locating}
              coordsTitle={dict.vtc.coordsTitle}
              coordsHint={dict.vtc.coordsHint}
              latLabel={dict.vtc.latLabel}
              lngLabel={dict.vtc.lngLabel}
              inputClass={inputClass}
            />
          </fieldset>

          {locationMessage ? (
            <p role="alert" className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
              {locationMessage}
            </p>
          ) : null}
          {estimateError ? (
            <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
              {estimateError}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={estimating || operators === null || operators.length === 0}
            className="h-11 rounded-lg bg-brand-600 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {estimating ? dict.vtc.estimating : dict.vtc.estimateCta}
          </button>
        </form>
      )}

      {step === "estimate" && quote !== null && (
        <div className="mt-6 flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">
              {dict.vtc.estimateTitle}
            </h2>
            <dl className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div className="rounded-xl bg-slate-50 p-3">
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  {dict.vtc.distanceLabel}
                </dt>
                <dd className="mt-1 text-sm font-semibold text-slate-900">
                  {formatDistanceMeters(quote.road_distance_m ?? quote.distance_m)}
                </dd>
              </div>
              <div className="rounded-xl bg-slate-50 p-3">
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  {dict.vtc.durationLabel}
                </dt>
                <dd className="mt-1 text-sm font-semibold text-slate-900">
                  {formatDurationSeconds(quote.duration_s)}
                </dd>
              </div>
              <div className="rounded-xl bg-slate-50 p-3">
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  {dict.vtc.priceLabel}
                </dt>
                <dd className="mt-1 text-sm font-semibold text-slate-900">
                  {quote.price_minor !== null
                    ? formatMoney(quote.price_minor, quote.currency, locale)
                    : dict.vtc.priceOnRequest}
                </dd>
              </div>
            </dl>
          </div>

          <fieldset className="flex flex-col gap-4">
            <legend className="text-xs font-semibold uppercase tracking-wide text-slate-500">
              {dict.vtc.passengerTitle}
            </legend>
            <label className={labelClass}>
              {dict.vtc.passengerName}
              <input
                type="text"
                required
                value={passengerName}
                maxLength={120}
                placeholder={dict.vtc.passengerNamePlaceholder}
                onChange={(event) => setPassengerName(event.target.value)}
                className={inputClass}
              />
            </label>
            <label className={labelClass}>
              {dict.vtc.passengerPhone}
              <input
                type="tel"
                required
                value={passengerPhone}
                maxLength={40}
                placeholder={dict.vtc.passengerPhonePlaceholder}
                onChange={(event) => setPassengerPhone(event.target.value)}
                className={inputClass}
              />
            </label>
          </fieldset>

          {bookingError ? (
            <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
              {bookingError}
            </p>
          ) : null}

          <div className="flex flex-col gap-3 sm:flex-row">
            <button
              type="button"
              onClick={book}
              disabled={booking}
              className="h-11 flex-1 rounded-lg bg-brand-600 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {booking ? dict.vtc.booking : dict.vtc.bookCta}
            </button>
            <button
              type="button"
              onClick={resetFlow}
              className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              {dict.vtc.modifyCta}
            </button>
          </div>
        </div>
      )}

      {step === "success" && ride !== null && (
        <div className="mt-6 flex flex-col gap-5 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6 shadow-sm">
          <div>
            <h2 className="text-lg font-semibold text-emerald-900">
              {dict.vtc.successTitle}
            </h2>
            <p className="mt-1 text-sm text-emerald-800">{dict.vtc.successBody}</p>
          </div>

          <dl className="grid grid-cols-1 gap-3">
            <div className="rounded-xl bg-white p-3">
              <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {dict.vtc.referenceLabel}
              </dt>
              <dd className="mt-1 font-mono text-sm font-semibold text-slate-900">
                {ride.reference}
              </dd>
            </div>
            {ride.tracking_code ? (
              <div className="rounded-xl bg-white p-3">
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  {dict.vtc.trackingCodeLabel}
                </dt>
                <dd className="mt-1 font-mono text-lg font-bold tracking-widest text-brand-700">
                  {ride.tracking_code}
                </dd>
                <dd className="mt-1 text-[11px] text-slate-500">
                  {dict.vtc.trackingCodeHint}
                </dd>
              </div>
            ) : null}
          </dl>

          <div className="flex flex-col gap-3 sm:flex-row">
            <Link
              href={`/vtc/suivi?reference=${encodeURIComponent(ride.reference)}${
                ride.tracking_code
                  ? `&code=${encodeURIComponent(ride.tracking_code)}`
                  : ""
              }`}
              className="flex h-11 flex-1 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white transition hover:bg-brand-700"
            >
              {dict.vtc.trackLink}
            </Link>
            <button
              type="button"
              onClick={resetFlow}
              className="h-11 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              {dict.vtc.newRideCta}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

type PointInputsProps = {
  point: PointState;
  onChange: (point: PointState) => void;
  onLocate: () => void;
  locating: boolean;
  locateLabel: string;
  locatingLabel: string;
  coordsTitle: string;
  coordsHint: string;
  latLabel: string;
  lngLabel: string;
  inputClass: string;
};

/**
 * Coordonnées d'un point : géolocalisation un-tap + saisie manuelle (le
 * géocodage d'adresses est un lot ultérieur — issue dédiée).
 */
function PointInputs({
  point,
  onChange,
  onLocate,
  locating,
  locateLabel,
  locatingLabel,
  coordsTitle,
  coordsHint,
  latLabel,
  lngLabel,
  inputClass,
}: PointInputsProps) {
  return (
    <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
      <div className="flex items-center justify-between gap-3">
        <span className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          {coordsTitle}
        </span>
        <button
          type="button"
          onClick={onLocate}
          disabled={locating}
          className="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {locating ? locatingLabel : locateLabel}
        </button>
      </div>
      <div className="mt-2 grid grid-cols-2 gap-2">
        <input
          type="text"
          inputMode="decimal"
          required
          value={point.lat}
          placeholder={latLabel}
          aria-label={latLabel}
          onChange={(event) => onChange({ ...point, lat: event.target.value })}
          className={inputClass}
        />
        <input
          type="text"
          inputMode="decimal"
          required
          value={point.lng}
          placeholder={lngLabel}
          aria-label={lngLabel}
          onChange={(event) => onChange({ ...point, lng: event.target.value })}
          className={inputClass}
        />
      </div>
      <p className="mt-2 text-[11px] leading-relaxed text-slate-400">{coordsHint}</p>
    </div>
  );
}
