"use client";

/**
 * Retour de paiement — /paiement/retour?reference=WEB-… (#7812).
 *
 * Le PSP redirige ici après le paiement. Le jeton de suivi n'est JAMAIS
 * transmis au prestataire : il est relu depuis le localStorage
 * (`leopardo_marche_orders`). La page interroge le suivi public
 * (`payment.status`) et POLLE quelques instants : le statut « payé »
 * n'arrive que par le webhook signé côté serveur, il peut prendre
 * quelques secondes.
 *
 * États couverts : paiement confirmé, en attente (poll + relance),
 * remboursé, commande locale introuvable (renvoi vers /suivi), erreur API.
 */

import { CheckCircle2, Clock3, KeyRound, Loader2, RotateCcw, Undo2 } from "lucide-react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { useHydrated } from "@/hooks/useCart";
import { ApiError, fetchOrderTracking } from "@/lib/api";
import { findSavedOrder, type SavedOrder } from "@/lib/orders";

const POLL_INTERVAL_MS = 5_000;
const MAX_POLLS = 12; // ~1 minute d'attente avant de proposer la relance.

type PaymentState = "loading" | "paid" | "pending" | "refunded" | "timeout" | "error" | "unknown-order";

function PaymentReturnContent() {
  const searchParams = useSearchParams();
  const hydrated = useHydrated();
  const reference = (searchParams.get("reference") ?? "").trim();

  const [state, setState] = useState<PaymentState>("loading");
  const [order, setOrder] = useState<SavedOrder | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  // Chaque incrément relance le cycle de vérification (bouton « Revérifier »).
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    if (!hydrated) return;

    if (reference.length === 0) {
      // Pas de référence dans l'URL : rien à vérifier.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setState("unknown-order");
      return;
    }

    const saved = findSavedOrder(reference);
    if (!saved) {
      // Jeton absent de ce navigateur (autre appareil, stockage purgé) :
      // le suivi manuel réf + jeton reste possible.
       
      setState("unknown-order");
      return;
    }

    // Fetch-au-montage légitime (synchronisation avec l'API de suivi).
     
    setOrder(saved);

    let cancelled = false;
    let polls = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const check = async () => {
      try {
        const tracking = await fetchOrderTracking(saved.reference, saved.trackingToken);
        if (cancelled) return;

        const status = tracking.payment?.status ?? "pending";

        if (status === "paid") {
          setState("paid");
          return;
        }
        if (status === "refunded") {
          setState("refunded");
          return;
        }

        polls += 1;
        if (polls >= MAX_POLLS) {
          setState("timeout");
          return;
        }
        setState("pending");
        timer = setTimeout(() => void check(), POLL_INTERVAL_MS);
      } catch (err) {
        if (cancelled) return;
        setErrorMessage(
          err instanceof ApiError && err.status === 404
            ? "Commande introuvable. Vérifiez votre référence sur la page de suivi."
            : err instanceof Error
              ? err.message
              : "Une erreur est survenue.",
        );
        setState("error");
      }
    };

    void check();

    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [hydrated, reference, attempt]);

  const retry = () => {
    setState("loading");
    setAttempt((n) => n + 1);
  };

  const trackingHref = order
    ? `/suivi?ref=${encodeURIComponent(order.reference)}&token=${encodeURIComponent(order.trackingToken)}`
    : "/suivi";

  if (!hydrated || state === "loading") {
    return (
      <div className="mx-auto max-w-2xl px-4 py-16 text-center">
        <Loader2 aria-hidden="true" className="mx-auto h-10 w-10 animate-spin text-amber-600" />
        <p className="mt-4 text-sm text-stone-500">Vérification de votre paiement…</p>
      </div>
    );
  }

  if (state === "unknown-order") {
    return (
      <div className="mx-auto max-w-2xl px-4 py-8">
        <h1 className="mb-6 text-3xl font-bold tracking-tight text-stone-900">Retour de paiement</h1>
        <EmptyState
          title="Commande introuvable sur cet appareil"
          description="Nous ne retrouvons pas cette commande dans ce navigateur. Utilisez le suivi de commande avec votre référence et votre jeton pour vérifier l'état du paiement."
          action={{ href: "/suivi", label: "Suivre ma commande" }}
        />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <h1 className="mb-8 text-3xl font-bold tracking-tight text-stone-900">Retour de paiement</h1>

      <section className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
        {state === "paid" ? (
          <div className="flex items-start gap-4">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
              <CheckCircle2 aria-hidden="true" className="h-6 w-6" />
            </span>
            <div>
              <h2 className="text-lg font-semibold text-stone-900">Paiement confirmé</h2>
              <p className="mt-1 text-sm text-stone-500">
                Merci ! Votre paiement a bien été reçu. La boutique prépare votre commande
                — suivez son avancement avec votre référence.
              </p>
            </div>
          </div>
        ) : null}

        {state === "pending" ? (
          <div className="flex items-start gap-4">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
              <Loader2 aria-hidden="true" className="h-6 w-6 animate-spin" />
            </span>
            <div>
              <h2 className="text-lg font-semibold text-stone-900">Confirmation en cours…</h2>
              <p className="mt-1 text-sm text-stone-500">
                Votre paiement est en cours de confirmation par le prestataire. Cette page
                se met à jour automatiquement, cela ne prend généralement que quelques
                secondes.
              </p>
            </div>
          </div>
        ) : null}

        {state === "timeout" ? (
          <div className="flex items-start gap-4">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
              <Clock3 aria-hidden="true" className="h-6 w-6" />
            </span>
            <div>
              <h2 className="text-lg font-semibold text-stone-900">
                Confirmation toujours en attente
              </h2>
              <p className="mt-1 text-sm text-stone-500">
                Nous n&apos;avons pas encore reçu la confirmation du prestataire. Pas
                d&apos;inquiétude : si votre paiement a abouti, il sera pris en compte
                automatiquement. Vous pouvez revérifier maintenant ou consulter le suivi
                plus tard.
              </p>
              <button
                type="button"
                onClick={retry}
                className="mt-3 inline-flex h-10 items-center gap-2 rounded-full border border-stone-300 bg-white px-5 text-sm font-medium text-stone-700 transition hover:border-amber-400 hover:text-amber-700"
              >
                <RotateCcw aria-hidden="true" className="h-4 w-4" />
                Revérifier
              </button>
            </div>
          </div>
        ) : null}

        {state === "refunded" ? (
          <div className="flex items-start gap-4">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-stone-100 text-stone-600">
              <Undo2 aria-hidden="true" className="h-6 w-6" />
            </span>
            <div>
              <h2 className="text-lg font-semibold text-stone-900">Paiement remboursé</h2>
              <p className="mt-1 text-sm text-stone-500">
                Cette commande a été remboursée par la boutique. Le délai de réception
                dépend de votre moyen de paiement.
              </p>
            </div>
          </div>
        ) : null}

        {state === "error" ? (
          <div role="alert" className="flex items-start gap-4">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 text-red-700">
              <Clock3 aria-hidden="true" className="h-6 w-6" />
            </span>
            <div>
              <h2 className="text-lg font-semibold text-stone-900">Vérification impossible</h2>
              <p className="mt-1 text-sm text-stone-500">{errorMessage}</p>
              <button
                type="button"
                onClick={retry}
                className="mt-3 inline-flex h-10 items-center gap-2 rounded-full border border-stone-300 bg-white px-5 text-sm font-medium text-stone-700 transition hover:border-amber-400 hover:text-amber-700"
              >
                <RotateCcw aria-hidden="true" className="h-4 w-4" />
                Réessayer
              </button>
            </div>
          </div>
        ) : null}

        {order ? (
          <dl className="mt-6 space-y-2 border-t border-stone-100 pt-4 text-sm">
            <div className="flex flex-wrap items-center gap-2">
              <dt className="font-medium text-stone-500">Boutique :</dt>
              <dd className="text-stone-900">{order.sellerName}</dd>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <dt className="font-medium text-stone-500">Référence :</dt>
              <dd className="rounded bg-stone-100 px-2 py-0.5 font-mono text-stone-900">
                {order.reference}
              </dd>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <dt className="font-medium text-stone-500">Montant :</dt>
              <dd>
                <Price
                  priceMinor={order.totalMinor}
                  currency={order.currency}
                  className="font-semibold text-stone-900"
                />
              </dd>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <dt className="flex items-center gap-1 font-medium text-stone-500">
                <KeyRound aria-hidden="true" className="h-3.5 w-3.5" />
                Jeton de suivi :
              </dt>
              <dd className="break-all rounded bg-stone-100 px-2 py-0.5 font-mono text-xs text-stone-900">
                {order.trackingToken}
              </dd>
            </div>
          </dl>
        ) : null}
      </section>

      <div className="mt-6 flex flex-wrap gap-3">
        <Link
          href={trackingHref}
          className="inline-flex h-11 items-center rounded-full bg-amber-600 px-6 text-sm font-semibold text-white transition hover:bg-amber-700"
        >
          Suivre ma commande
        </Link>
        <Link
          href="/produits"
          className="inline-flex h-11 items-center rounded-full border border-stone-300 bg-white px-6 text-sm font-medium text-stone-700 transition hover:border-amber-400 hover:text-amber-700"
        >
          Continuer mes achats
        </Link>
      </div>
    </div>
  );
}

export default function PaymentReturnPage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-2xl px-4 py-16 text-center">
          <Loader2 aria-hidden="true" className="mx-auto h-10 w-10 animate-spin text-amber-600" />
        </div>
      }
    >
      <PaymentReturnContent />
    </Suspense>
  );
}
