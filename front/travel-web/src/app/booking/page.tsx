import type { Metadata } from "next";

import { TrackContent } from "@/components/track-content";

export const metadata: Metadata = {
  title: "Retrouver ma réservation",
  description:
    "Retrouvez votre réservation de billet par référence et code de validation : statut, billets, e-billet PDF et annulation en ligne.",
  alternates: { canonical: "/booking" },
};

export default function BookingLookupPage() {
  return <TrackContent />;
}
