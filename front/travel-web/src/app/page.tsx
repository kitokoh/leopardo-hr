import type { Metadata } from "next";

import { HomeContent } from "@/components/home-content";

export const metadata: Metadata = {
  title: "Leopardo Travel — Réservez vos billets de voyage en ligne",
  description:
    "Comparez les départs de toutes les agences partenaires, choisissez votre siège et réservez votre billet en quelques minutes.",
  alternates: { canonical: "/" },
};

export default function HomePage() {
  return <HomeContent />;
}
