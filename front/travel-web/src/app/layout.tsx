import type { Metadata } from "next";
import { cookies } from "next/headers";

import "./globals.css";
import {
  DEFAULT_LOCALE,
  getDict,
  isLocale,
  LOCALE_COOKIE,
  type Locale,
} from "@/lib/i18n";
import { LocaleProvider } from "@/lib/locale-provider";
import { SiteHeader } from "@/components/site-header";
import { SiteFooter } from "@/components/site-footer";

const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL || "https://leopardo-travel.vercel.app";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await resolveLocale();
  const dict = getDict(locale);

  return {
    metadataBase: new URL(SITE_URL),
    title: {
      default: dict.home.metaTitle,
      template: "%s — Leopardo Travel",
    },
    description: dict.home.metaDescription,
    openGraph: {
      type: "website",
      siteName: dict.common.siteName,
      title: dict.home.metaTitle,
      description: dict.home.metaDescription,
      locale: locale === "fr" ? "fr_FR" : "en_US",
    },
  };
}

async function resolveLocale(): Promise<Locale> {
  const store = await cookies();
  const value = store.get(LOCALE_COOKIE)?.value;
  return isLocale(value) ? value : DEFAULT_LOCALE;
}

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const locale = await resolveLocale();

  return (
    <html lang={locale}>
      <body className="flex min-h-screen flex-col">
        <LocaleProvider initialLocale={locale}>
          <SiteHeader />
          <main className="flex-1">{children}</main>
          <SiteFooter />
        </LocaleProvider>
      </body>
    </html>
  );
}
