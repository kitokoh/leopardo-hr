"use client";

import Link from "next/link";

import { useLocale } from "@/lib/locale-provider";

export default function TripNotFound() {
  const { dict } = useLocale();

  return (
    <div className="mx-auto max-w-2xl px-4 py-20 text-center">
      <p className="text-5xl" aria-hidden>
        🧳
      </p>
      <h1 className="mt-4 text-xl font-bold text-slate-900">{dict.trip.notFound}</h1>
      <Link
        href="/"
        className="mt-6 inline-block rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700"
      >
        {dict.common.backHome}
      </Link>
    </div>
  );
}
