import type { MarketplaceCityRef, MarketplaceTrip } from "@/lib/types";
import type { Locale } from "@/lib/i18n";

/** Montant mineur → chaîne localisée (ex. 250000 XAF → « 2 500 XAF »). */
export function formatMoney(
  amountMinor: number | null | undefined,
  currency: string | null | undefined,
  locale: Locale,
): string {
  if (amountMinor === null || amountMinor === undefined) return "—";
  const code = currency || "XAF";
  try {
    return new Intl.NumberFormat(locale === "fr" ? "fr-FR" : "en-US", {
      style: "currency",
      currency: code,
      maximumFractionDigits: 0,
    }).format(amountMinor / 100);
  } catch {
    return `${(amountMinor / 100).toFixed(0)} ${code}`;
  }
}

export function formatDate(date: string | null | undefined, locale: Locale): string {
  if (!date) return "—";
  const parsed = new Date(`${date}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return date;
  return new Intl.DateTimeFormat(locale === "fr" ? "fr-FR" : "en-GB", {
    weekday: "short",
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(parsed);
}

/** « 08:30:00 » → « 08:30 ». */
export function formatTime(time: string | null | undefined): string {
  if (!time) return "—";
  return time.slice(0, 5);
}

export function cityLabel(city: MarketplaceCityRef): string {
  if (!city) return "—";
  return city.country_iso2 ? `${city.name} (${city.country_iso2})` : city.name;
}

export function tripTitle(trip: MarketplaceTrip): string {
  return `${cityLabel(trip.origin_city)} → ${cityLabel(trip.destination_city)}`;
}

export function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}
