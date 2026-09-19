"use client";

/**
 * Suivi public — /suivi (+ pré-remplissage via ?ref=&token=).
 * GET /public/market/orders/{reference}?token= — timeline visuelle des
 * statuts pending → confirmed → ready → shipped → delivered (+ cancelled).
 */

import { Loader2, PackageSearch } from "lucide-react";
import { useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useState, type FormEvent } from "react";

import { Price } from "@/components/Price";
import { StatusTimeline } from "@/components/StatusTimeline";
import { ApiError, fetchOrderTracking, type OrderTracking } from "@/lib/api";

function TrackingContent() {
  const searchParams = useSearchParams();
  const [reference, setReference] = useState(searchParams.get("ref") ?? "");
  const [token, setToken] = useState(searchParams.get("token") ?? "");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tracking, setTracking] = useState<OrderTracking | null>(null);

  const lookup = useCallback(async (ref: string, tok: string) => {
    setLoading(true);
    setError(null);
    setTracking(null);
    try {
      setTracking(await fetchOrderTracking(ref.trim(), tok.trim()));
    } catch (err) {
      setError(
        err instanceof ApiError && err.status === 404
          ? "Commande introuvable. Vérifiez la référence et le jeton de suivi : les deux doivent correspondre exactement."
          : err instanceof Error
            ? err.message
            : "Une erreur est survenue.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  // Lancement automatique si ?ref=&token= sont fournis (liens de /confirmation).
  useEffect(() => {
    const ref = searchParams.get("ref");
    const tok = searchParams.get("token");
    if (ref && tok) {
      // Fetch-au-montage légitime (synchronisation avec l'API) : la règle
      // « React Compiler readiness » flaguerait le setLoading synchrone.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void lookup(ref, tok);
    }
  }, [searchParams, lookup]);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (reference.trim() && token.trim()) void lookup(reference, token);
  };

  const sellerName =
    tracking && typeof tracking.seller === "object"
      ? tracking.seller.name
      : typeof tracking?.seller === "string"
        ? tracking.seller
        : null;

  const inputClass =
    "h-11 w-full rounded-xl border border-stone-300 bg-white px-4 font-mono text-sm text-stone-900 placeholder:font-sans placeholder:text-stone-400 focus:border-amber-500";

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <header className="mb-8 flex items-start gap-4">
        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
          <PackageSearch aria-hidden="true" className="h-6 w-6" />
        </span>
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-stone-900">Suivi de commande</h1>
          <p className="mt-1 text-sm text-stone-500">
            Saisissez la référence (WEB-…) et le jeton de suivi reçus lors de votre commande.
          </p>
        </div>
      </header>

      <form
        onSubmit={submit}
        className="space-y-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"
      >
        <div>
          <label htmlFor="tracking-ref" className="mb-1 block text-xs font-medium text-stone-600">
            Référence de commande
          </label>
          <input
            id="tracking-ref"
            required
            value={reference}
            onChange={(event) => setReference(event.target.value)}
            placeholder="WEB-XXXXXX"
            className={inputClass}
          />
        </div>
        <div>
          <label htmlFor="tracking-token" className="mb-1 block text-xs font-medium text-stone-600">
            Jeton de suivi
          </label>
          <input
            id="tracking-token"
            required
            value={token}
            onChange={(event) => setToken(event.target.value)}
            placeholder="Jeton privé remis à la commande"
            className={inputClass}
          />
        </div>
        <button
          type="submit"
          disabled={loading}
          className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-amber-600 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {loading ? (
            <>
              <Loader2 aria-hidden="true" className="h-5 w-5 animate-spin" />
              Recherche…
            </>
          ) : (
            "Suivre ma commande"
          )}
        </button>
      </form>

      {error ? (
        <p role="alert" className="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          {error}
        </p>
      ) : null}

      {tracking ? (
        <section
          aria-label={`Suivi de la commande ${tracking.reference}`}
          className="mt-8 space-y-6 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"
        >
          <header className="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 pb-4">
            <div>
              <p className="font-mono text-sm font-semibold text-stone-900">{tracking.reference}</p>
              {sellerName ? <p className="text-xs text-stone-500">Boutique : {sellerName}</p> : null}
            </div>
            <Price
              priceMinor={tracking.total_minor}
              currency={tracking.currency}
              className="text-base font-bold text-stone-900"
            />
          </header>

          <StatusTimeline status={tracking.fulfillment_status} timeline={tracking.timeline} />

          {tracking.items.length > 0 ? (
            <div>
              <h2 className="mb-2 text-sm font-semibold text-stone-900">Articles</h2>
              <ul className="space-y-1.5 text-sm text-stone-600">
                {tracking.items.map((item, index) => (
                  <li key={index} className="flex justify-between gap-3">
                    <span>
                      {item.quantity} × {item.name}
                    </span>
                    {typeof item.line_total_minor === "number" ? (
                      <Price
                        priceMinor={item.line_total_minor}
                        currency={tracking.currency}
                        className="shrink-0 font-medium text-stone-900"
                      />
                    ) : null}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}

export default function TrackingPage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-2xl px-4 py-8">
          <div className="h-9 w-64 animate-pulse rounded-lg bg-stone-200" />
          <div className="mt-6 h-64 animate-pulse rounded-2xl bg-stone-100" />
        </div>
      }
    >
      <TrackingContent />
    </Suspense>
  );
}
