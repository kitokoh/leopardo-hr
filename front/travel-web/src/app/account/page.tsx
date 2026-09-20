import { Suspense } from "react";
import type { Metadata } from "next";

import { AccountDashboard } from "@/components/account-dashboard";

export const metadata: Metadata = {
  title: "Mon compte",
  description:
    "Votre compte Leopardo Travel : profil et historique de vos réservations, toutes agences confondues.",
  robots: { index: false },
  alternates: { canonical: "/account" },
};

export default function AccountPage() {
  return (
    <Suspense fallback={null}>
      <AccountDashboard />
    </Suspense>
  );
}
