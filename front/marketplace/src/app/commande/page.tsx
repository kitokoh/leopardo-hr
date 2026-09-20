"use client";

/**
 * Checkout invité — /commande.
 *
 * Côté API, 1 commande = 1 vendeur : la soumission envoie donc UN POST
 * `/public/market/orders` PAR boutique présente dans le panier. Chaque
 * boutique reçoit une clé d'idempotence `crypto.randomUUID()` persistée en
 * localStorage AVANT le premier envoi : en cas d'échec réseau ou de nouvel
 * essai, la même clé est rejouée et le serveur répond de façon idempotente
 * (pas de commande dupliquée). Succès partiel géré : les boutiques servies
 * sont retirées du panier, les autres restent et peuvent être re-soumises.
 *
 * Paiement (#7812) : « Paiement à la livraison » (défaut) ou « Paiement en
 * ligne ». En ligne : le serveur crée un intent et renvoie une
 * `checkout_url` — une seule boutique → redirection immédiate vers le PSP
 * (retour sur /paiement/retour) ; plusieurs boutiques → /confirmation, un
 * bouton « Payer en ligne » par commande.
 */

import { AlertTriangle, CreditCard, HandCoins, Loader2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

import { EmptyState } from "@/components/EmptyState";
import { Price } from "@/components/Price";
import { useCart } from "@/hooks/useCart";
import { createOrder, type OrderCreated, type PaymentMethod } from "@/lib/api";
import { removeSellerFromCart, type CartGroup } from "@/lib/cart";
import { idempotencyKeyFor, releaseIdempotencyKey, saveOrders, type SavedOrder } from "@/lib/orders";

interface CustomerForm {
  name: string;
  phone: string;
  email: string;
  address: string;
  city: string;
  notes: string;
}

const EMPTY_FORM: CustomerForm = {
  name: "",
  phone: "",
  email: "",
  address: "",
  city: "",
  notes: "",
};

export default function CheckoutPage() {
  const router = useRouter();
  const { ready, groups } = useCart();
  const [form, setForm] = useState<CustomerForm>(EMPTY_FORM);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>("cash");
  const [submitting, setSubmitting] = useState(false);
  const [failures, setFailures] = useState<{ sellerName: string; message: string }[]>([]);

  const set = (field: keyof CustomerForm) => (value: string) =>
    setForm((previous) => ({ ...previous, [field]: value }));

  const submitOrders = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting || groups.length === 0) return;
    setSubmitting(true);
    setFailures([]);

    const created: SavedOrder[] = [];
    const failed: { sellerName: string; message: string }[] = [];

    // Envois séquentiels : lisible pour l'utilisateur et doux pour le throttle public.
    for (const group of groups) {
      const key = idempotencyKeyFor(group.seller.slug);
      try {
        const order: OrderCreated = await createOrder({
          seller: group.seller.slug,
          items: group.items.map((item) => ({
            product_id: item.productId,
            quantity: item.quantity,
          })),
          customer: {
            name: form.name.trim(),
            phone: form.phone.trim(),
            ...(form.email.trim() ? { email: form.email.trim() } : {}),
          },
          delivery: {
            address: form.address.trim(),
            city: form.city.trim(),
            ...(form.notes.trim() ? { notes: form.notes.trim() } : {}),
          },
          payment_method: paymentMethod,
          idempotency_key: key,
        });
        created.push({
          reference: order.reference,
          trackingToken: order.tracking_token,
          totalMinor: order.total_minor,
          currency: order.currency,
          sellerName: group.seller.name,
          sellerSlug: group.seller.slug,
          createdAt: new Date().toISOString(),
          paymentMethod,
          checkoutUrl: order.payment?.checkout_url ?? null,
        });
        // Boutique servie : clé libérée + articles retirés du panier.
        releaseIdempotencyKey(group.seller.slug);
        removeSellerFromCart(group.seller.slug);
      } catch (error) {
        // Échec : la clé d'idempotence est CONSERVÉE pour une re-soumission sûre.
        failed.push({
          sellerName: group.seller.name,
          message: error instanceof Error ? error.message : "Une erreur est survenue.",
        });
      }
    }

    if (created.length > 0) {
      saveOrders(created);
    }

    if (failed.length === 0) {
      // Paiement en ligne, une seule commande avec URL de paiement :
      // redirection immédiate vers la page hébergée du PSP (le retour se
      // fait sur /paiement/retour). Plusieurs commandes : /confirmation
      // affiche un bouton « Payer en ligne » par commande.
      const payable = created.filter((order) => order.checkoutUrl);
      if (paymentMethod === "online" && payable.length === 1 && payable[0].checkoutUrl) {
        window.location.assign(payable[0].checkoutUrl);
        return;
      }
      router.push("/confirmation");
      return;
    }

    setFailures(failed);
    setSubmitting(false);
    if (created.length > 0) {
      // Succès partiel : on signale les commandes déjà créées.
      setFailures([
        ...failed,
        {
          sellerName: "Bonne nouvelle",
          message: `${created.length} commande${created.length > 1 ? "s ont" : " a"} déjà été enregistrée${created.length > 1 ? "s" : ""} — retrouvez-la${created.length > 1 ? "" : ""} sur la page de confirmation. Re-soumettez le formulaire pour les boutiques restantes.`,
        },
      ]);
    }
  };

  if (!ready) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8">
        <div className="h-9 w-56 animate-pulse rounded-lg bg-stone-200" />
        <div className="mt-6 h-64 animate-pulse rounded-2xl bg-stone-100" />
      </div>
    );
  }

  if (groups.length === 0) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8">
        <h1 className="mb-6 text-3xl font-bold tracking-tight text-stone-900">Commande</h1>
        <EmptyState
          title="Votre panier est vide"
          description="Ajoutez des articles avant de passer commande. Si vous venez de commander, vos références sont sur la page de confirmation."
          action={{ href: "/produits", label: "Voir les produits" }}
        />
        <p className="mt-4 text-center text-sm text-stone-500">
          <Link href="/confirmation" className="font-medium text-amber-700 hover:underline">
            Voir mes commandes récentes
          </Link>
        </p>
      </div>
    );
  }

  const inputClass =
    "h-11 w-full rounded-xl border border-stone-300 bg-white px-4 text-sm text-stone-900 placeholder:text-stone-400 focus:border-amber-500";

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <h1 className="mb-1 text-3xl font-bold tracking-tight text-stone-900">Finaliser ma commande</h1>
      <p className="mb-8 text-sm text-stone-500">
        Sans compte — vos coordonnées ne servent qu&apos;à la livraison.
      </p>

      <form onSubmit={submitOrders} className="grid gap-8 lg:grid-cols-[1fr_380px]">
        {/* Coordonnées */}
        <div className="space-y-6">
          <fieldset className="space-y-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            <legend className="px-2 text-sm font-semibold text-stone-900">Vos coordonnées</legend>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label htmlFor="customer-name" className="mb-1 block text-xs font-medium text-stone-600">
                  Nom complet <span aria-hidden="true" className="text-red-500">*</span>
                </label>
                <input
                  id="customer-name"
                  required
                  autoComplete="name"
                  value={form.name}
                  onChange={(event) => set("name")(event.target.value)}
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="customer-phone" className="mb-1 block text-xs font-medium text-stone-600">
                  Téléphone <span aria-hidden="true" className="text-red-500">*</span>
                </label>
                <input
                  id="customer-phone"
                  required
                  type="tel"
                  autoComplete="tel"
                  value={form.phone}
                  onChange={(event) => set("phone")(event.target.value)}
                  className={inputClass}
                />
              </div>
            </div>
            <div>
              <label htmlFor="customer-email" className="mb-1 block text-xs font-medium text-stone-600">
                E-mail (facultatif)
              </label>
              <input
                id="customer-email"
                type="email"
                autoComplete="email"
                value={form.email}
                onChange={(event) => set("email")(event.target.value)}
                className={inputClass}
              />
            </div>
          </fieldset>

          <fieldset className="space-y-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            <legend className="px-2 text-sm font-semibold text-stone-900">Livraison</legend>
            <div>
              <label htmlFor="delivery-address" className="mb-1 block text-xs font-medium text-stone-600">
                Adresse <span aria-hidden="true" className="text-red-500">*</span>
              </label>
              <input
                id="delivery-address"
                required
                autoComplete="street-address"
                value={form.address}
                onChange={(event) => set("address")(event.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label htmlFor="delivery-city" className="mb-1 block text-xs font-medium text-stone-600">
                Ville <span aria-hidden="true" className="text-red-500">*</span>
              </label>
              <input
                id="delivery-city"
                required
                autoComplete="address-level2"
                value={form.city}
                onChange={(event) => set("city")(event.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label htmlFor="delivery-notes" className="mb-1 block text-xs font-medium text-stone-600">
                Instructions pour le livreur (facultatif)
              </label>
              <textarea
                id="delivery-notes"
                rows={3}
                value={form.notes}
                onChange={(event) => set("notes")(event.target.value)}
                className="w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 placeholder:text-stone-400 focus:border-amber-500"
              />
            </div>
          </fieldset>

          <fieldset className="space-y-3 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            <legend className="px-2 text-sm font-semibold text-stone-900">Mode de paiement</legend>

            <label
              className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition ${
                paymentMethod === "cash"
                  ? "border-amber-500 bg-amber-50"
                  : "border-stone-200 hover:border-stone-300"
              }`}
            >
              <input
                type="radio"
                name="payment-method"
                value="cash"
                checked={paymentMethod === "cash"}
                onChange={() => setPaymentMethod("cash")}
                className="mt-1 h-4 w-4 accent-amber-600"
              />
              <span className="flex items-start gap-3">
                <HandCoins aria-hidden="true" className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                <span>
                  <span className="block text-sm font-semibold text-stone-900">
                    Paiement à la livraison
                  </span>
                  <span className="block text-xs text-stone-500">
                    Vous réglez chaque commande en espèces, directement au livreur de la boutique.
                  </span>
                </span>
              </span>
            </label>

            <label
              className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition ${
                paymentMethod === "online"
                  ? "border-amber-500 bg-amber-50"
                  : "border-stone-200 hover:border-stone-300"
              }`}
            >
              <input
                type="radio"
                name="payment-method"
                value="online"
                checked={paymentMethod === "online"}
                onChange={() => setPaymentMethod("online")}
                className="mt-1 h-4 w-4 accent-amber-600"
              />
              <span className="flex items-start gap-3">
                <CreditCard aria-hidden="true" className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                <span>
                  <span className="block text-sm font-semibold text-stone-900">
                    Paiement en ligne
                  </span>
                  <span className="block text-xs text-stone-500">
                    Carte ou mobile money, sur une page de paiement sécurisée. Vous serez
                    redirigé après la validation de la commande.
                  </span>
                </span>
              </span>
            </label>
          </fieldset>

          <p className="text-xs leading-relaxed text-stone-400">
            Vos données (nom, téléphone, adresse, e-mail facultatif) sont transmises
            uniquement aux boutiques concernées pour préparer et livrer votre commande.
            Elles ne sont ni revendues ni utilisées à des fins publicitaires.
          </p>
        </div>

        {/* Récapitulatif par boutique */}
        <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
          <h2 className="text-sm font-semibold text-stone-900">
            Récapitulatif — {groups.length} commande{groups.length > 1 ? "s" : ""}
          </h2>
          {groups.map((group: CartGroup) => (
            <section
              key={group.seller.slug}
              aria-label={`Commande ${group.seller.name}`}
              className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"
            >
              <h3 className="mb-3 text-sm font-semibold text-stone-900">{group.seller.name}</h3>
              <ul className="space-y-2 text-sm text-stone-600">
                {group.items.map((item) => (
                  <li key={item.productId} className="flex justify-between gap-3">
                    <span className="line-clamp-1">
                      {item.quantity} × {item.name}
                    </span>
                    <Price
                      priceMinor={item.priceMinor * item.quantity}
                      currency={item.currency}
                      className="shrink-0 font-medium text-stone-900"
                    />
                  </li>
                ))}
              </ul>
              <div className="mt-3 flex justify-between border-t border-stone-100 pt-3 text-sm">
                <span className="text-stone-500">Sous-total</span>
                <Price
                  priceMinor={group.subtotalMinor}
                  currency={group.currency}
                  className="font-semibold text-stone-900"
                />
              </div>
            </section>
          ))}

          {failures.length > 0 ? (
            <div role="alert" className="space-y-2 rounded-2xl border border-red-200 bg-red-50 p-4">
              {failures.map((failure, index) => (
                <p key={index} className="flex items-start gap-2 text-sm text-red-800">
                  <AlertTriangle aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" />
                  <span>
                    <strong>{failure.sellerName} :</strong> {failure.message}
                  </span>
                </p>
              ))}
            </div>
          ) : null}

          <button
            type="submit"
            disabled={submitting}
            className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-amber-600 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {submitting ? (
              <>
                <Loader2 aria-hidden="true" className="h-5 w-5 animate-spin" />
                Envoi en cours…
              </>
            ) : (
              `Confirmer ${groups.length > 1 ? `les ${groups.length} commandes` : "la commande"}`
            )}
          </button>
        </aside>
      </form>
    </div>
  );
}
