import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Mon compte",
  description:
    "Créez votre compte Leopardo Marché ou connectez-vous : historique de commandes, favoris et avis vérifiés.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
