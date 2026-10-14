import type { Metadata } from "next";
import { Suspense } from "react";

import { VtcTrackContent } from "@/components/vtc-track-content";

export const metadata: Metadata = {
  title: "Suivre ma course VTC",
  description:
    "Suivez votre course VTC par référence et code de suivi : statut en temps réel, durée estimée et prix.",
  alternates: { canonical: "/vtc/suivi" },
};

export default function VtcTrackPage() {
  // useSearchParams exige une frontière Suspense au prerender (Next 16).
  return (
    <Suspense>
      <VtcTrackContent />
    </Suspense>
  );
}
