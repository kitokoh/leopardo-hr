import { Check, CheckCircle2, Clock, Package, Truck, XCircle, type LucideIcon } from "lucide-react";

import type { FulfillmentStatus, TimelineEntry } from "@/lib/api";
import { formatDateTime } from "@/lib/format";

const STEPS: { status: FulfillmentStatus; label: string; icon: LucideIcon }[] = [
  { status: "pending", label: "Commande reçue", icon: Clock },
  { status: "confirmed", label: "Confirmée par la boutique", icon: CheckCircle2 },
  { status: "ready", label: "Prête", icon: Package },
  { status: "shipped", label: "Expédiée", icon: Truck },
  { status: "delivered", label: "Livrée", icon: Check },
];

interface StatusTimelineProps {
  status: FulfillmentStatus;
  timeline: TimelineEntry[];
}

/**
 * Timeline visuelle des statuts pending → confirmed → ready → shipped →
 * delivered. Le statut `cancelled` remplace la progression par un encart.
 */
export function StatusTimeline({ status, timeline }: StatusTimelineProps) {
  if (status === "cancelled") {
    const cancelledAt = formatDateTime(
      timeline.find((entry) => entry.status === "cancelled")?.at,
    );
    return (
      <div
        role="status"
        className="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4"
      >
        <XCircle aria-hidden="true" className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
        <div>
          <p className="font-semibold text-red-800">Commande annulée</p>
          <p className="text-sm text-red-700">
            {cancelledAt
              ? `Annulée le ${cancelledAt}.`
              : "Cette commande a été annulée."}{" "}
            Contactez la boutique pour plus d&apos;informations.
          </p>
        </div>
      </div>
    );
  }

  const currentIndex = STEPS.findIndex((step) => step.status === status);

  return (
    <ol className="space-y-0" aria-label="Progression de la commande">
      {STEPS.map((step, index) => {
        const done = index < currentIndex;
        const current = index === currentIndex;
        const at = formatDateTime(timeline.find((entry) => entry.status === step.status)?.at);
        const Icon = step.icon;
        return (
          <li key={step.status} className="relative flex gap-4 pb-8 last:pb-0">
            {index < STEPS.length - 1 ? (
              <span
                aria-hidden="true"
                className={`absolute left-[19px] top-10 h-[calc(100%-2.5rem)] w-0.5 rounded ${
                  done ? "bg-amber-500" : "bg-stone-200"
                }`}
              />
            ) : null}
            <span
              className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 ${
                done
                  ? "border-amber-500 bg-amber-500 text-white"
                  : current
                    ? "border-amber-500 bg-amber-50 text-amber-600"
                    : "border-stone-200 bg-white text-stone-300"
              }`}
            >
              <Icon aria-hidden="true" className="h-5 w-5" />
            </span>
            <div className="pt-1.5">
              <p
                className={`text-sm font-semibold ${
                  done || current ? "text-stone-900" : "text-stone-400"
                }`}
              >
                {step.label}
                {current ? (
                  <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    Statut actuel
                  </span>
                ) : null}
              </p>
              {at ? <p className="text-xs text-stone-500">{at}</p> : null}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
