"use client";

import Link from "next/link";

import { SearchForm } from "@/components/search-form";
import { useLocale } from "@/lib/locale-provider";

export function HomeContent() {
  const { dict } = useLocale();

  const features = [
    {
      title: dict.home.features.compareTitle,
      body: dict.home.features.compareBody,
      icon: "🧭",
    },
    {
      title: dict.home.features.seatsTitle,
      body: dict.home.features.seatsBody,
      icon: "💺",
    },
    {
      title: dict.home.features.ticketTitle,
      body: dict.home.features.ticketBody,
      icon: "🎫",
    },
  ];

  return (
    <div>
      <section className="bg-gradient-to-b from-brand-900 via-brand-800 to-brand-700 text-white">
        <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-16 sm:py-24">
          <div className="max-w-2xl">
            <h1 className="text-3xl font-bold tracking-tight sm:text-5xl">
              {dict.home.heroTitle}
            </h1>
            <p className="mt-4 text-base text-brand-100 sm:text-lg">
              {dict.home.heroSubtitle}
            </p>
          </div>
          <SearchForm />
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 py-14">
        <div className="grid gap-6 sm:grid-cols-3">
          {features.map((feature) => (
            <div
              key={feature.title}
              className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
              <span aria-hidden className="text-3xl">
                {feature.icon}
              </span>
              <h2 className="mt-3 text-lg font-semibold text-slate-900">
                {feature.title}
              </h2>
              <p className="mt-2 text-sm leading-relaxed text-slate-600">
                {feature.body}
              </p>
            </div>
          ))}
        </div>
      </section>

      {/* #8423 (M-30) — la boucle marketplace : les offres VTC/taxi des
          tenants sont réservables ici comme les billets de bus. */}
      <section className="mx-auto max-w-6xl px-4 pb-14">
        <div className="flex flex-col items-start gap-5 rounded-2xl bg-gradient-to-r from-brand-800 to-brand-600 p-8 text-white sm:flex-row sm:items-center sm:justify-between">
          <div className="max-w-xl">
            <h2 className="text-xl font-bold tracking-tight">
              {dict.home.vtcBannerTitle}
            </h2>
            <p className="mt-2 text-sm leading-relaxed text-brand-100">
              {dict.home.vtcBannerBody}
            </p>
          </div>
          <Link
            href="/vtc"
            className="inline-flex h-11 shrink-0 items-center rounded-lg bg-white px-5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50"
          >
            {dict.home.vtcBannerCta}
          </Link>
        </div>
      </section>
    </div>
  );
}
