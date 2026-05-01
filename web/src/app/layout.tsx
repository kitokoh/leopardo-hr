import type { Metadata } from "next";
import "./globals.css";
import { LocaleSync } from "@/components/locale-sync";

export const metadata: Metadata = {
  title: "Leopardo RH - Solution de gestion RH simplifiée",
  description: "Optimisez votre gestion du personnel avec Leopardo RH. Pointage, Paie, et gestion des absences tout-en-un.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr">
      <body className="font-sans antialiased">
        <LocaleSync />
        {children}
      </body>
    </html>
  );
}
