import type { Metadata } from "next";

import { AccountRegisterForm } from "@/components/account-register-form";

export const metadata: Metadata = {
  title: "Créer un compte",
  description:
    "Créez votre compte Leopardo Travel : vos réservations existantes sont rattachées automatiquement à votre e-mail.",
  alternates: { canonical: "/account/register" },
};

export default function AccountRegisterPage() {
  return <AccountRegisterForm />;
}
