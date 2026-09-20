import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Mes favoris",
  description: "Vos produits favoris sur Leopardo Marché.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
