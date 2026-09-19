import { Compass } from "lucide-react";
import type { Metadata } from "next";

import { EmptyState } from "@/components/EmptyState";

export const metadata: Metadata = {
  title: "Page introuvable",
};

export default function NotFound() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-16">
      <EmptyState
        icon={Compass}
        title="Cette page n'existe pas (ou plus)"
        description="Le produit ou la boutique que vous cherchez a peut-être été retiré du marché."
        action={{ href: "/", label: "Retour à l'accueil" }}
      />
    </div>
  );
}
