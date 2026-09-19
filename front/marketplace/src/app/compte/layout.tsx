import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Mon compte",
  description:
    "Compte acheteur Leopardo Marché : profil, historique de commandes, favoris et avis.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
