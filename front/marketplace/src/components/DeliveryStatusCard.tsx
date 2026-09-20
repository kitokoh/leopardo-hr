import { Bike, type LucideIcon } from "lucide-react";

import type { DeliveryStatus } from "@/lib/api";
import { formatDateTime } from "@/lib/format";

/**
 * Statut livraison BC-26 (#7811) — encart optionnel de la page /suivi,
 * affiché uniquement quand l'API expose `delivery` (livraison
 * `retail_online` créée à la confirmation). Libellés FR fail-safe : un
 * statut inconnu affiche le libellé générique sans casser la page.
 */

const STATUS_LABELS: Record<string, string> = {
  created: "Livraison créée",
  assigned: "Livreur assigné",
  picked_up: "Colis récupéré par le livreur",
  out_for_delivery: "En cours de livraison",
  arrived: "Le livreur est arrivé",
  delivered: "Livrée",
  failed: "Échec de livraison",
  returned: "Retournée à la boutique",
  cancelled: "Livraison annulée",
};

const NEGATIVE_STATUSES = new Set(["failed", "returned", "cancelled"]);

interface DeliveryStatusCardProps {
  delivery: DeliveryStatus;
}

export function DeliveryStatusCard({ delivery }: DeliveryStatusCardProps) {
  const label = STATUS_LABELS[delivery.status] ?? "Livraison en préparation";
  const negative = NEGATIVE_STATUSES.has(delivery.status);
  const done = delivery.status === "delivered";

  const timestamp = done
    ? formatDateTime(delivery.delivered_at)
    : delivery.status === "failed"
      ? formatDateTime(delivery.failed_at)
      : delivery.status === "returned"
        ? formatDateTime(delivery.returned_at)
        : formatDateTime(delivery.created_at);

  const Icon: LucideIcon = Bike;

  return (
    <div
      role="status"
      className={`flex items-start gap-3 rounded-2xl border p-4 ${
        negative
          ? "border-red-200 bg-red-50"
          : done
            ? "border-emerald-200 bg-emerald-50"
            : "border-amber-200 bg-amber-50"
      }`}
    >
      <Icon
        aria-hidden="true"
        className={`mt-0.5 h-5 w-5 shrink-0 ${
          negative ? "text-red-600" : done ? "text-emerald-600" : "text-amber-600"
        }`}
      />
      <div>
        <p
          className={`text-sm font-semibold ${
            negative ? "text-red-800" : done ? "text-emerald-800" : "text-amber-800"
          }`}
        >
          Suivi livreur : {label}
        </p>
        {timestamp ? (
          <p
            className={`text-xs ${
              negative ? "text-red-700" : done ? "text-emerald-700" : "text-amber-700"
            }`}
          >
            {done ? `Livrée le ${timestamp}.` : `Mise à jour : ${timestamp}.`}
          </p>
        ) : null}
      </div>
    </div>
  );
}
