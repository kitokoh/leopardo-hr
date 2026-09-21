"use client";

/**
 * Mes commandes — /compte/commandes (#7814).
 *
 * Historique cross-tenant des commandes liées au compte acheteur
 * (GET /public/market/account/orders). Chaque commande livrée expose un
 * formulaire d'avis vérifié par article. Lien direct vers le suivi public
 * (référence + jeton).
 */

import { Loader2, PackageSearch, Star } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { ReviewForm } from "@/components/ReviewForm";
import { useBuyer } from "@/hooks/useBuyer";
import { fetchBuyerOrders, type AccountOrder, type FulfillmentStatus } from "@/lib/api";
import { formatDateTime } from "@/lib/format";

const STATUS_LABELS: Record<FulfillmentStatus, string> = {
  pending: "En attente de confirmation",
  confirmed: "Confirmée",
  ready: "Prête",
  shipped: "Expédiée",
  delivered: "Livrée",
  cancelled: "Annulée",
};

const STATUS_STYLES: Record<FulfillmentStatus, string> = {
  pending: "bg-stone-100 text-stone-600",
  confirmed: "bg-sky-50 text-sky-700",
  ready: "bg-indigo-50 text-indigo-700",
  shipped: "bg-amber-50 text-amber-700",
  delivered: "bg-emerald-50 text-emerald-700",
  cancelled: "bg-rose-50 text-rose-600",
};

export default function AccountOrdersPage() {
  const router = useRouter();
  const { ready, session, handleUnauthorized } = useBuyer();
  const [orders, setOrders] = useState<AccountOrder[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Formulaire d'avis ouvert : `${reference}:${productId}`.
  const [openReview, setOpenReview] = useState<string | null>(null);

  const load = useCallback(
    async () => {
      setError(null);
      try {
        const page = await fetchBuyerOrders();
        setOrders(page.data);
      } catch (err) {
        if (handleUnauthorized(err)) {
          router.push("/compte");
          return;
        }
        setError(err instanceof Error ? err.message : "Une erreur est survenue.");
      }
    },
    [handleUnauthorized, router],
  );

  useEffect(() => {
    if (!ready) return;
    if (!session) {
      router.push("/compte");
      return;
    }
    // Fetch-au-montage légitime (synchronisation avec l'API) : la règle
    // « React Compiler readiness » flaguerait le setError synchrone.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load();
  }, [ready, session, router, load]);

  if (!ready || (session && orders === null && error === null)) {
    return (
      <div className="mx-auto flex max-w-3xl items-center justify-center px-4 py-24" aria-busy="true">
        <Loader2 aria-hidden="true" className="h-6 w-6 animate-spin text-amber-600" />
        <span className="sr-only">Chargement de vos commandes…</span>
      </div>
    );
  }

  if (!session) return null;

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <nav aria-label="Fil d'Ariane" className="mb-6 text-sm text-stone-500">
        <Link href="/compte" className="transition hover:text-amber-700">
          Mon compte
        </Link>
        <span aria-hidden="true" className="mx-2">/</span>
        <span className="text-stone-900">Mes commandes</span>
      </nav>

      <h1 className="text-2xl font-bold tracking-tight text-stone-900">Mes commandes</h1>
      <p className="mt-1 text-sm text-stone-500">
        Une fois la commande livrée, donnez votre avis sur chaque article.
      </p>

      {error ? (
        <div className="mt-8">
          <EmptyState
            title="Impossible de charger vos commandes"
            description={error}
            action={{ href: "/compte/commandes", label: "Réessayer" }}
          />
        </div>
      ) : orders !== null && orders.length === 0 ? (
        <div className="mt-8">
          <EmptyState
            icon={PackageSearch}
            title="Aucune commande pour l'instant"
            description="Vos prochaines commandes passées en étant connecté apparaîtront ici."
            action={{ href: "/produits", label: "Découvrir les produits" }}
          />
        </div>
      ) : (
        <ul className="mt-8 flex flex-col gap-4">
          {(orders ?? []).map((order) => (
            <li
              key={order.reference}
              className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="font-mono text-sm font-semibold text-stone-900">
                    {order.reference}
                  </p>
                  <p className="text-xs text-stone-500">
                    {order.seller.name ?? "Boutique"}
                    {order.seller.city ? ` · ${order.seller.city}` : ""}
                    {order.created_at ? ` · ${formatDateTime(order.created_at)}` : ""}
                  </p>
                </div>
                <span
                  className={`rounded-full px-3 py-1 text-xs font-semibold ${STATUS_STYLES[order.fulfillment_status]}`}
                >
                  {STATUS_LABELS[order.fulfillment_status]}
                </span>
              </div>

              <ul className="mt-4 flex flex-col gap-3 border-t border-stone-100 pt-4">
                {order.items.map((item) => {
                  const reviewKey = `${order.reference}:${item.product_id}`;
                  return (
                    <li key={reviewKey} className="flex flex-col gap-2">
                      <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span className="text-stone-700">
                          {item.product_name}
                          <span className="text-stone-400"> × {item.quantity}</span>
                        </span>
                        <span className="flex items-center gap-3">
                          <Price
                            priceMinor={item.line_total_minor}
                            currency={order.currency}
                            className="text-sm font-medium text-stone-900"
                          />
                          {order.fulfillment_status === "delivered" ? (
                            <button
                              type="button"
                              onClick={() =>
                                setOpenReview(openReview === reviewKey ? null : reviewKey)
                              }
                              aria-expanded={openReview === reviewKey}
                              className="inline-flex items-center gap-1 rounded-full border border-amber-300 px-3 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-50"
                            >
                              <Star aria-hidden="true" className="h-3.5 w-3.5" />
                              Laisser un avis
                            </button>
                          ) : null}
                        </span>
                      </div>
                      {openReview === reviewKey ? (
                        <ReviewForm
                          orderReference={order.reference}
                          productId={item.product_id}
                          productName={item.product_name}
                          onDone={() => undefined}
                          onUnauthorized={(err) => {
                            if (handleUnauthorized(err)) {
                              router.push("/compte");
                              return true;
                            }
                            return false;
                          }}
                        />
                      ) : null}
                    </li>
                  );
                })}
              </ul>

              <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-4">
                <Price
                  priceMinor={order.total_minor}
                  currency={order.currency}
                  className="text-base font-bold text-stone-900"
                />
                <Link
                  href={`/suivi?ref=${encodeURIComponent(order.reference)}&token=${encodeURIComponent(order.tracking_token)}`}
                  className="text-sm font-semibold text-amber-700 transition hover:text-amber-800"
                >
                  Suivre la commande →
                </Link>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
