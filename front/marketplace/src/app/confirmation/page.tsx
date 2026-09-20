"use client";

/**
 * Confirmation — /confirmation.
 * Affiche les commandes enregistrées en localStorage (`leopardo_marche_orders`)
 * avec référence + jeton de suivi et lien direct vers /suivi.
 */

import { CheckCircle2, Copy, CreditCard, KeyRound, ShieldAlert } from "lucide-react";
import Link from "next/link";
import { useMemo, useState } from "react";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { useHydrated } from "@/hooks/useCart";
import { formatDateTime } from "@/lib/format";
import { readSavedOrders, type SavedOrder } from "@/lib/orders";

export default function ConfirmationPage() {
  const ready = useHydrated();
  const [copied, setCopied] = useState<string | null>(null);
  // Lecture unique après hydratation — la liste n'évolue pas sur cette page.
  const orders = useMemo<SavedOrder[]>(() => (ready ? readSavedOrders() : []), [ready]);

  const copy = async (order: SavedOrder) => {
    try {
      await navigator.clipboard.writeText(
        `Commande ${order.reference} — jeton de suivi : ${order.trackingToken}`,
      );
      setCopied(order.reference);
      setTimeout(() => setCopied(null), 2000);
    } catch {
      // Presse-papiers indisponible : les valeurs restent affichées.
    }
  };

  if (!ready) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-8">
        <div className="h-9 w-64 animate-pulse rounded-lg bg-stone-200" />
        <div className="mt-6 h-48 animate-pulse rounded-2xl bg-stone-100" />
      </div>
    );
  }

  if (orders.length === 0) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-8">
        <h1 className="mb-6 text-3xl font-bold tracking-tight text-stone-900">Mes commandes</h1>
        <EmptyState
          title="Aucune commande récente sur cet appareil"
          description="Les références de vos commandes sont conservées localement dans votre navigateur. Si vous avez une référence et un jeton, utilisez le suivi de commande."
          action={{ href: "/suivi", label: "Suivre une commande" }}
        />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <div className="mb-8 flex items-start gap-4">
        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
          <CheckCircle2 aria-hidden="true" className="h-6 w-6" />
        </span>
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-stone-900">Merci !</h1>
          <p className="mt-1 text-sm text-stone-500">
            Vos commandes ont bien été transmises aux boutiques. Chacune vous
            contactera au numéro indiqué pour confirmer la livraison.
          </p>
        </div>
      </div>

      <div
        role="note"
        className="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"
      >
        <ShieldAlert aria-hidden="true" className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
        <p className="text-sm text-stone-700">
          <strong>Conservez précieusement vos jetons de suivi.</strong> Sans compte,
          le couple référence + jeton est le seul moyen d&apos;accéder au suivi de vos
          commandes. Ils sont enregistrés dans ce navigateur, mais notez-les par
          sécurité.
        </p>
      </div>

      <ul className="space-y-4">
        {orders.map((order) => (
          <li
            key={order.reference}
            className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"
          >
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-stone-900">{order.sellerName}</p>
                <p className="text-xs text-stone-400">
                  {formatDateTime(order.createdAt) ?? ""}
                </p>
              </div>
              <Price
                priceMinor={order.totalMinor}
                currency={order.currency}
                className="text-base font-bold text-stone-900"
              />
            </div>

            <dl className="mt-4 space-y-2 text-sm">
              <div className="flex flex-wrap items-center gap-2">
                <dt className="font-medium text-stone-500">Référence :</dt>
                <dd className="rounded bg-stone-100 px-2 py-0.5 font-mono text-stone-900">
                  {order.reference}
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

            <div className="mt-4 flex flex-wrap gap-3">
              {order.paymentMethod === "online" && order.checkoutUrl ? (
                <a
                  href={order.checkoutUrl}
                  className="inline-flex h-10 items-center gap-2 rounded-full bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                >
                  <CreditCard aria-hidden="true" className="h-4 w-4" />
                  Payer en ligne
                </a>
              ) : null}
              <Link
                href={`/suivi?ref=${encodeURIComponent(order.reference)}&token=${encodeURIComponent(order.trackingToken)}`}
                className="inline-flex h-10 items-center rounded-full bg-amber-600 px-5 text-sm font-semibold text-white transition hover:bg-amber-700"
              >
                Suivre cette commande
              </Link>
              <button
                type="button"
                onClick={() => copy(order)}
                className="inline-flex h-10 items-center gap-2 rounded-full border border-stone-300 bg-white px-5 text-sm font-medium text-stone-700 transition hover:border-amber-400 hover:text-amber-700"
              >
                <Copy aria-hidden="true" className="h-4 w-4" />
                {copied === order.reference ? "Copié !" : "Copier réf. + jeton"}
              </button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
