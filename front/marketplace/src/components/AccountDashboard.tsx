"use client";

import { Heart, LogOut, Package, UserRound } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { useAccount } from "@/hooks/useAccount";
import { ApiError } from "@/lib/api";
import { fetchAccountOrders, logoutAccount, type AccountOrder } from "@/lib/account";
import { formatDate, fulfillmentLabel } from "@/lib/format";

/**
 * Tableau de bord du compte acheteur (#7814) : profil, historique de
 * commandes cross-boutiques (avec lien de suivi), accès favoris,
 * déconnexion. Redirige vers la connexion si aucune session.
 */
export function AccountDashboard() {
  const router = useRouter();
  const { ready, session } = useAccount();
  const [orders, setOrders] = useState<AccountOrder[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!ready) return;
    if (!session) {
      router.replace("/compte/connexion");
      return;
    }
    let cancelled = false;
    fetchAccountOrders()
      .then((data) => {
        if (!cancelled) setOrders(data);
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
        }
      });
    return () => {
      cancelled = true;
    };
  }, [ready, session, router]);

  if (!ready || !session) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-12">
        <div className="h-40 animate-pulse rounded-2xl bg-stone-100" aria-hidden="true" />
      </div>
    );
  }

  const logout = async () => {
    await logoutAccount();
    router.push("/");
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <span className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700">
            <UserRound aria-hidden="true" className="h-6 w-6" />
          </span>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-stone-900">{session.account.name}</h1>
            <p className="text-sm text-stone-500">{session.account.email}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Link
            href="/compte/favoris"
            className="inline-flex items-center gap-1.5 rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-amber-500 hover:text-amber-700"
          >
            <Heart aria-hidden="true" className="h-4 w-4" />
            Mes favoris
          </Link>
          <button
            type="button"
            onClick={logout}
            className="inline-flex items-center gap-1.5 rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-red-400 hover:text-red-600"
          >
            <LogOut aria-hidden="true" className="h-4 w-4" />
            Déconnexion
          </button>
        </div>
      </div>

      <h2 className="mt-10 flex items-center gap-2 text-lg font-semibold text-stone-900">
        <Package aria-hidden="true" className="h-5 w-5 text-amber-600" />
        Mes commandes
      </h2>

      {error ? (
        <p role="alert" className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </p>
      ) : orders === null ? (
        <div className="mt-4 space-y-3" aria-busy="true" aria-label="Chargement des commandes">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-24 animate-pulse rounded-2xl bg-stone-100" />
          ))}
        </div>
      ) : orders.length === 0 ? (
        <div className="mt-4">
          <EmptyState
            title="Aucune commande pour le moment"
            description="Vos commandes passées connecté — ou avec l'e-mail de votre compte — apparaîtront ici."
            action={{ href: "/produits", label: "Découvrir les produits" }}
          />
        </div>
      ) : (
        <ul className="mt-4 space-y-3">
          {orders.map((order) => (
            <li
              key={order.reference}
              className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-5"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="font-semibold text-stone-900">{order.reference}</p>
                  <p className="text-sm text-stone-500">
                    {order.seller.name ?? "Boutique"}
                    {order.created_at ? ` · ${formatDate(order.created_at)}` : ""}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                    {fulfillmentLabel(order.fulfillment_status)}
                  </span>
                  <Price priceMinor={order.total_minor} currency={order.currency} />
                </div>
              </div>
              <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-3 text-sm">
                <p className="text-stone-500">
                  {order.items.length} article{order.items.length > 1 ? "s" : ""}
                </p>
                {order.tracking_token ? (
                  <Link
                    href={`/suivi?ref=${encodeURIComponent(order.reference)}&token=${encodeURIComponent(order.tracking_token)}`}
                    className="font-semibold text-amber-700 transition hover:text-amber-800"
                  >
                    Suivre la commande →
                  </Link>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
