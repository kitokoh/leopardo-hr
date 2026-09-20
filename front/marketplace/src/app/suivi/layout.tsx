import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Suivi de commande",
  description: "Suivez votre commande Leopardo Marché avec votre référence et votre jeton de suivi.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
