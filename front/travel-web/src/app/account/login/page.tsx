import type { Metadata } from "next";

import { AccountLoginForm } from "@/components/account-login-form";

export const metadata: Metadata = {
  title: "Connexion",
  description:
    "Connectez-vous à votre compte Leopardo Travel pour retrouver toutes vos réservations.",
  alternates: { canonical: "/account/login" },
};

export default function AccountLoginPage() {
  return <AccountLoginForm />;
}
