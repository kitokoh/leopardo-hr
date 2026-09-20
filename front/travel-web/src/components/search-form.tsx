"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";

import { fetchCities } from "@/lib/api";
import { todayIso } from "@/lib/format";
import { useLocale } from "@/lib/locale-provider";
import type { MarketplaceCity } from "@/lib/types";

type Props = {
  initialOrigin?: string;
  initialDestination?: string;
  initialDate?: string;
};

export function SearchForm({
  initialOrigin = "",
  initialDestination = "",
  initialDate = "",
}: Props) {
  const { dict } = useLocale();
  const router = useRouter();

  const [cities, setCities] = useState<MarketplaceCity[]>([]);
  const [origin, setOrigin] = useState(initialOrigin);
  const [destination, setDestination] = useState(initialDestination);
  const [date, setDate] = useState(initialDate || todayIso());

  useEffect(() => {
    let active = true;
    fetchCities()
      .then((data) => {
        if (active) setCities(data);
      })
      .catch(() => {
        // Autocomplete indisponible : la recherche par date reste possible.
      });
    return () => {
      active = false;
    };
  }, []);

  const sortedCities = useMemo(
    () => [...cities].sort((a, b) => a.name.localeCompare(b.name)),
    [cities],
  );

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const params = new URLSearchParams();
    if (origin) params.set("origin", origin);
    if (destination) params.set("destination", destination);
    if (date) params.set("date", date);
    router.push(`/trips?${params.toString()}`);
  };

  const swap = () => {
    setOrigin(destination);
    setDestination(origin);
  };

  const citySelect = (
    id: string,
    label: string,
    value: string,
    onChange: (value: string) => void,
  ) => (
    <div className="flex flex-1 flex-col gap-1">
      <label htmlFor={id} className="text-xs font-semibold uppercase tracking-wide text-slate-500">
        {label}
      </label>
      <select
        id={id}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
      >
        <option value="">{dict.search.anyCity}</option>
        {sortedCities.map((city) => (
          <option key={city.id} value={String(city.id)}>
            {city.name} ({city.country_iso2})
          </option>
        ))}
      </select>
    </div>
  );

  return (
    <form
      onSubmit={submit}
      className="flex w-full flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-lg shadow-slate-900/5 sm:flex-row sm:items-end"
    >
      {citySelect("search-origin", dict.search.from, origin, setOrigin)}

      <button
        type="button"
        onClick={swap}
        aria-label={dict.search.swap}
        title={dict.search.swap}
        className="hidden h-11 w-11 shrink-0 items-center justify-center self-end rounded-lg border border-slate-300 text-slate-500 transition hover:bg-slate-100 sm:flex"
      >
        ⇄
      </button>

      {citySelect(
        "search-destination",
        dict.search.to,
        destination,
        setDestination,
      )}

      <div className="flex flex-col gap-1">
        <label
          htmlFor="search-date"
          className="text-xs font-semibold uppercase tracking-wide text-slate-500"
        >
          {dict.search.date}
        </label>
        <input
          id="search-date"
          type="date"
          value={date}
          min={todayIso()}
          onChange={(event) => setDate(event.target.value)}
          className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
        />
      </div>

      <button
        type="submit"
        className="h-11 rounded-lg bg-brand-600 px-6 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300"
      >
        {dict.search.submit}
      </button>
    </form>
  );
}
