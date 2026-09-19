import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Mon panier",
  description: "Votre panier Leopardo Marché : articles groupés par boutique, quantités et totaux.",
  robots: { index: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return children;
}
