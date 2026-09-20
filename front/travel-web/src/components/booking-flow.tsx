"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";

import { ApiError, createBooking } from "@/lib/api";
import { useAccount } from "@/lib/account-provider";
import {
  cityLabel,
  formatDate,
  formatMoney,
  formatTime,
} from "@/lib/format";
import { useLocale } from "@/lib/locale-provider";
import type {
  AgeCategory,
  MarketplaceTripDetail,
  TripPrice,
} from "@/lib/types";

type PassengerDraft = {
  full_name: string;
  age_category: AgeCategory;
  class_id: number;
  seat_number: number | null;
};

export const CONFIRMATION_STORAGE_PREFIX = "travel-booking:";

function priceFor(price: TripPrice | undefined, age: AgeCategory): number {
  if (!price) return 0;
  if (age === "adult") return price.adult_price_minor;
  if (age === "child") return price.child_price_minor;
  return 0; // infant : pas de siège facturé — tarif tranché par l'agence.
}

export function BookingFlow({ trip }: { trip: MarketplaceTripDetail }) {
  const { dict, locale } = useLocale();
  const { account } = useAccount();
  const router = useRouter();

  const defaultClassId = trip.prices[0]?.class_id ?? 0;
  const freeSeatNumbers = useMemo(
    () => trip.seats.map((seat) => seat.seat_number),
    [trip.seats],
  );
  const freeSeatSet = useMemo(() => new Set(freeSeatNumbers), [freeSeatNumbers]);

  const [passengers, setPassengers] = useState<PassengerDraft[]>([
    { full_name: "", age_category: "adult", class_id: defaultClassId, seat_number: null },
  ]);
  const [contactEmail, setContactEmail] = useState("");
  const [contactPhone, setContactPhone] = useState("");
  const [notifyConsent, setNotifyConsent] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // #7739 — pré-remplissage checkout : client connecté → coordonnées du
  // compte, sans jamais écraser une saisie manuelle.
  const prefilled = useRef(false);
  useEffect(() => {
    if (!account || prefilled.current) return;
    prefilled.current = true;
    setContactEmail((current) => current || account.email);
    setContactPhone((current) => current || (account.phone ?? ""));
  }, [account]);

  // Idempotence : UNE clé par session de checkout — un rejeu réseau ne crée
  // jamais deux réservations (contrat #7737).
  const idempotencyKey = useRef<string>(
    typeof crypto !== "undefined" && "randomUUID" in crypto
      ? crypto.randomUUID()
      : `web-${Date.now()}-${Math.random().toString(36).slice(2)}`,
  );

  const selectedSeats = useMemo(
    () =>
      passengers
        .map((p) => p.seat_number)
        .filter((seat): seat is number => seat !== null),
    [passengers],
  );

  const totalMinor = useMemo(() => {
    const priceByClass = new Map(trip.prices.map((p) => [p.class_id, p]));
    return passengers.reduce(
      (sum, passenger) =>
        sum + priceFor(priceByClass.get(passenger.class_id), passenger.age_category),
      0,
    );
  }, [passengers, trip.prices]);

  const updatePassenger = (index: number, patch: Partial<PassengerDraft>) => {
    setPassengers((current) =>
      current.map((p, i) => (i === index ? { ...p, ...patch } : p)),
    );
  };

  const addPassenger = () => {
    if (passengers.length >= 20) return;
    setPassengers((current) => [
      ...current,
      { full_name: "", age_category: "adult", class_id: defaultClassId, seat_number: null },
    ]);
  };

  const removePassenger = (index: number) => {
    setPassengers((current) =>
      current.length > 1 ? current.filter((_, i) => i !== index) : current,
    );
  };

  const toggleSeat = (seat: number) => {
    setPassengers((current) => {
      const holder = current.findIndex((p) => p.seat_number === seat);
      if (holder >= 0) {
        return current.map((p, i) =>
          i === holder ? { ...p, seat_number: null } : p,
        );
      }
      const empty = current.findIndex((p) => p.seat_number === null);
      if (empty >= 0) {
        return current.map((p, i) =>
          i === empty ? { ...p, seat_number: seat } : p,
        );
      }
      if (current.length >= 20) return current;
      return [
        ...current,
        {
          full_name: "",
          age_category: "adult",
          class_id: defaultClassId,
          seat_number: seat,
        },
      ];
    });
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    if (passengers.some((p) => p.full_name.trim().length === 0)) {
      setError(dict.checkout.fillName);
      return;
    }
    if (!contactEmail.trim() && !contactPhone.trim()) {
      setError(dict.checkout.contactRequired);
      return;
    }

    setSubmitting(true);
    try {
      const { booking, agencyName } = await createBooking(
        {
          trip_id: trip.id,
          idempotency_key: idempotencyKey.current,
          contact_email: contactEmail.trim() || undefined,
          contact_phone: contactPhone.trim() || undefined,
          notify_consent: notifyConsent,
          passengers: passengers.map((p) => ({
            full_name: p.full_name.trim(),
            age_category: p.age_category,
            class_id: p.class_id,
            seat_number: p.seat_number,
          })),
        },
        // #7739/#7841 — client connecté : réservation rattachée à son compte
        // via le cookie httpOnly de session (injecté en Bearer par le proxy).
      );

      try {
        sessionStorage.setItem(
          `${CONFIRMATION_STORAGE_PREFIX}${booking.reference}`,
          JSON.stringify({
            booking,
            agencyName,
            trip: {
              origin: cityLabel(trip.origin_city),
              destination: cityLabel(trip.destination_city),
              departure_date: trip.departure_date,
              departure_time: trip.departure_time,
            },
          }),
        );
      } catch {
        // sessionStorage indisponible : la page confirmation affichera le
        // message de repli (référence toujours visible dans l'URL).
      }

      router.push(`/confirmation/${encodeURIComponent(booking.reference)}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : dict.common.error);
      setSubmitting(false);
    }
  };

  const seatNumbers = Array.from({ length: trip.total_seats }, (_, i) => i + 1);

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-8">
      {/* ── En-tête trajet ── */}
      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">
          {cityLabel(trip.origin_city)} → {cityLabel(trip.destination_city)}
        </h1>
        <dl className="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
          <div>
            <dt className="text-slate-500">{dict.trip.departure}</dt>
            <dd className="font-semibold text-slate-900">
              {formatDate(trip.departure_date, locale)} · {formatTime(trip.departure_time)}
            </dd>
          </div>
          <div>
            <dt className="text-slate-500">{dict.trip.arrival}</dt>
            <dd className="font-semibold text-slate-900">
              {formatDate(trip.arrival_date, locale)} · {formatTime(trip.arrival_time)}
            </dd>
          </div>
          {trip.means_of_transport ? (
            <div>
              <dt className="text-slate-500">{dict.trip.transport}</dt>
              <dd className="font-semibold text-slate-900">{trip.means_of_transport}</dd>
            </div>
          ) : null}
          {trip.agency.name ? (
            <div>
              <dt className="text-slate-500">{dict.trip.agency}</dt>
              <dd className="font-semibold text-slate-900">{trip.agency.name}</dd>
            </div>
          ) : null}
        </dl>

        <div className="mt-5 border-t border-slate-100 pt-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            {dict.trip.classes}
          </h2>
          <ul className="mt-2 flex flex-wrap gap-3">
            {trip.prices.map((price, index) => (
              <li
                key={price.class_id}
                className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
              >
                <span className="font-medium text-slate-800">
                  {dict.trip.classLabel} {index + 1}
                </span>
                <span className="ml-2 text-slate-600">
                  {dict.trip.adult} : {formatMoney(price.adult_price_minor, price.currency, locale)}
                </span>
                <span className="ml-2 text-slate-600">
                  {dict.trip.child} : {formatMoney(price.child_price_minor, price.currency, locale)}
                </span>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[1fr_360px]">
        <div className="flex flex-col gap-8">
          {/* ── Plan de sièges ── */}
          <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">{dict.seats.title}</h2>
            <p className="mt-1 text-sm text-slate-500">{dict.seats.subtitle}</p>

            <div className="mt-4 flex flex-wrap gap-4 text-xs text-slate-600">
              <span className="flex items-center gap-1.5">
                <span className="h-3.5 w-3.5 rounded border border-brand-300 bg-brand-50" />
                {dict.seats.free}
              </span>
              <span className="flex items-center gap-1.5">
                <span className="h-3.5 w-3.5 rounded bg-brand-600" />
                {dict.seats.selected}
              </span>
              <span className="flex items-center gap-1.5">
                <span className="h-3.5 w-3.5 rounded bg-slate-200" />
                {dict.seats.taken}
              </span>
            </div>

            <div className="mt-4 grid grid-cols-6 gap-2 sm:grid-cols-8 md:grid-cols-10">
              {seatNumbers.map((seat) => {
                const free = freeSeatSet.has(seat);
                const selected = selectedSeats.includes(seat);
                return (
                  <button
                    key={seat}
                    type="button"
                    disabled={!free}
                    onClick={() => toggleSeat(seat)}
                    aria-pressed={selected}
                    aria-label={`${dict.seats.seatWord} ${seat}`}
                    className={`h-10 rounded-lg text-sm font-semibold transition ${
                      selected
                        ? "bg-brand-600 text-white"
                        : free
                          ? "border border-brand-300 bg-brand-50 text-brand-800 hover:bg-brand-100"
                          : "cursor-not-allowed bg-slate-200 text-slate-400"
                    }`}
                  >
                    {seat}
                  </button>
                );
              })}
            </div>

            <p className="mt-3 text-sm text-slate-500">
              {selectedSeats.length} {dict.seats.selectedCount}
              {selectedSeats.length === 0 ? ` — ${dict.seats.autoAssign}` : ""}
            </p>
          </section>

          {/* ── Passagers ── */}
          <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">
              {dict.checkout.passengersTitle}
            </h2>

            <div className="mt-4 flex flex-col gap-4">
              {passengers.map((passenger, index) => (
                <fieldset
                  key={index}
                  className="rounded-xl border border-slate-200 p-4"
                >
                  <legend className="px-1 text-sm font-semibold text-slate-700">
                    {dict.checkout.passenger} {index + 1}
                  </legend>
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2 lg:col-span-1">
                      {dict.checkout.fullName}
                      <input
                        type="text"
                        required
                        value={passenger.full_name}
                        maxLength={160}
                        onChange={(event) =>
                          updatePassenger(index, { full_name: event.target.value })
                        }
                        className="h-10 rounded-lg border border-slate-300 px-3 text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                      />
                    </label>

                    <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                      {dict.checkout.ageCategory}
                      <select
                        value={passenger.age_category}
                        onChange={(event) =>
                          updatePassenger(index, {
                            age_category: event.target.value as AgeCategory,
                          })
                        }
                        className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                      >
                        <option value="adult">{dict.checkout.ageAdult}</option>
                        <option value="child">{dict.checkout.ageChild}</option>
                        <option value="infant">{dict.checkout.ageInfant}</option>
                      </select>
                    </label>

                    {trip.prices.length > 1 ? (
                      <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {dict.checkout.classField}
                        <select
                          value={passenger.class_id}
                          onChange={(event) =>
                            updatePassenger(index, {
                              class_id: Number(event.target.value),
                            })
                          }
                          className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                        >
                          {trip.prices.map((price, classIndex) => (
                            <option key={price.class_id} value={price.class_id}>
                              {dict.trip.classLabel} {classIndex + 1} —{" "}
                              {formatMoney(price.adult_price_minor, price.currency, locale)}
                            </option>
                          ))}
                        </select>
                      </label>
                    ) : null}

                    <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                      {dict.checkout.seat}
                      <select
                        value={passenger.seat_number ?? ""}
                        onChange={(event) =>
                          updatePassenger(index, {
                            seat_number: event.target.value
                              ? Number(event.target.value)
                              : null,
                          })
                        }
                        className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                      >
                        <option value="">{dict.checkout.seatAuto}</option>
                        {freeSeatNumbers
                          .filter(
                            (seat) =>
                              seat === passenger.seat_number ||
                              !selectedSeats.includes(seat),
                          )
                          .map((seat) => (
                            <option key={seat} value={seat}>
                              {dict.seats.seatWord} {seat}
                            </option>
                          ))}
                      </select>
                    </label>
                  </div>

                  {passengers.length > 1 ? (
                    <button
                      type="button"
                      onClick={() => removePassenger(index)}
                      className="mt-3 text-sm font-medium text-red-600 hover:text-red-700"
                    >
                      {dict.checkout.removePassenger}
                    </button>
                  ) : null}
                </fieldset>
              ))}
            </div>

            <button
              type="button"
              onClick={addPassenger}
              disabled={passengers.length >= 20}
              className="mt-4 rounded-lg border border-brand-300 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 disabled:opacity-50"
            >
              + {dict.checkout.addPassenger}
            </button>
          </section>

          {/* ── Contact ── */}
          <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">
              {dict.checkout.contactTitle}
            </h2>
            <p className="mt-1 text-sm text-slate-500">{dict.checkout.contactHint}</p>
            {account ? (
              <p className="mt-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800">
                {dict.checkout.loggedInAs} : {account.email}
              </p>
            ) : null}
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {dict.checkout.contactEmail}
                <input
                  type="email"
                  value={contactEmail}
                  maxLength={255}
                  onChange={(event) => setContactEmail(event.target.value)}
                  className="h-10 rounded-lg border border-slate-300 px-3 text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                />
              </label>
              <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {dict.checkout.contactPhone}
                <input
                  type="tel"
                  value={contactPhone}
                  maxLength={40}
                  onChange={(event) => setContactPhone(event.target.value)}
                  className="h-10 rounded-lg border border-slate-300 px-3 text-sm font-normal normal-case text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                />
              </label>
            </div>
            <label className="mt-4 flex items-start gap-2 text-sm text-slate-600">
              <input
                type="checkbox"
                checked={notifyConsent}
                onChange={(event) => setNotifyConsent(event.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-300"
              />
              {dict.checkout.notifyConsent}
            </label>
          </section>
        </div>

        {/* ── Récapitulatif ── */}
        <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:sticky lg:top-20">
          <h2 className="text-lg font-semibold text-slate-900">
            {dict.checkout.summaryTitle}
          </h2>

          <dl className="mt-4 flex flex-col gap-2 text-sm">
            <div className="flex justify-between">
              <dt className="text-slate-500">{dict.confirmation.passengers}</dt>
              <dd className="font-medium text-slate-900">{passengers.length}</dd>
            </div>
            {selectedSeats.length > 0 ? (
              <div className="flex justify-between">
                <dt className="text-slate-500">{dict.checkout.seat}</dt>
                <dd className="font-medium text-slate-900">
                  {[...selectedSeats].sort((a, b) => a - b).join(", ")}
                </dd>
              </div>
            ) : null}
            <div className="flex justify-between border-t border-slate-100 pt-2">
              <dt className="font-semibold text-slate-700">{dict.checkout.total}</dt>
              <dd className="text-lg font-bold text-slate-900">
                {formatMoney(totalMinor, trip.currency, locale)}
              </dd>
            </div>
          </dl>

          <div className="mt-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-900">
            <p className="font-semibold">{dict.checkout.payAtAgency}</p>
            <p className="mt-1 leading-relaxed">{dict.checkout.payAtAgencyBody}</p>
          </div>

          {error ? (
            <p role="alert" className="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={submitting}
            className="mt-4 w-full rounded-lg bg-brand-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60"
          >
            {submitting ? dict.checkout.submitting : dict.checkout.submit}
          </button>
        </aside>
      </form>
    </div>
  );
}
