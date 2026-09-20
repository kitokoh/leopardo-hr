import type { Metadata } from "next";

import { ConfirmationContent } from "@/components/confirmation-content";

export const metadata: Metadata = {
  title: "Confirmation de réservation",
  robots: { index: false },
};

type Params = Promise<{ reference: string }>;

export default async function ConfirmationPage({ params }: { params: Params }) {
  const { reference } = await params;
  return <ConfirmationContent reference={decodeURIComponent(reference)} />;
}
