import type { Metadata } from "next";

import { VtcBookingFlow } from "@/components/vtc-booking-flow";

export const metadata: Metadata = {
  title: "VTC & Taxi — réservez votre chauffeur en ligne",
  description:
    "Choisissez un opérateur VTC partenaire, estimez votre course et réservez votre chauffeur en quelques minutes, sans créer de compte.",
  alternates: { canonical: "/vtc" },
};

export default function VtcPage() {
  return <VtcBookingFlow />;
}
