import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Confirmation de commande",
  description: "Références et jetons de suivi de vos commandes Leopardo Marché.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
