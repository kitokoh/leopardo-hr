import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { ApiError, getTripServer } from "@/lib/api";
import { cityLabel } from "@/lib/format";
import type { MarketplaceTripDetail } from "@/lib/types";
import { BookingFlow } from "@/components/booking-flow";

export const dynamic = "force-dynamic";

type Params = Promise<{ id: string }>;

async function loadTrip(id: string): Promise<MarketplaceTripDetail | null> {
  try {
    const { data } = await getTripServer(id);
    return data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}

export async function generateMetadata({
  params,
}: {
  params: Params;
}): Promise<Metadata> {
  const { id } = await params;
  const trip = await loadTrip(id).catch(() => null);

  if (!trip) {
    return { title: "Trajet — Leopardo Travel" };
  }

  const title = `${cityLabel(trip.origin_city)} → ${cityLabel(trip.destination_city)} · ${trip.departure_date}`;

  return {
    title,
    description: `Réservez votre billet ${cityLabel(trip.origin_city)} → ${cityLabel(trip.destination_city)} du ${trip.departure_date} : choix du siège et réservation en ligne.`,
    alternates: { canonical: `/trips/${trip.id}` },
    openGraph: { title },
  };
}

export default async function TripPage({ params }: { params: Params }) {
  const { id } = await params;
  const trip = await loadTrip(id);

  if (!trip) {
    notFound();
  }

  return <BookingFlow trip={trip} />;
}
