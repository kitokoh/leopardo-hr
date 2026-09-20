import type { Metadata } from "next";

import { searchTripsServer } from "@/lib/api";
import type { MarketplaceTrip, SearchMeta } from "@/lib/types";
import { ResultsContent } from "@/components/results-content";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Trajets disponibles",
  description:
    "Liste des départs disponibles auprès de toutes les agences partenaires : horaires, prix et sièges restants.",
  alternates: { canonical: "/trips" },
};

type SearchParams = Promise<{
  origin?: string;
  destination?: string;
  date?: string;
}>;

export default async function TripsPage({
  searchParams,
}: {
  searchParams: SearchParams;
}) {
  const params = await searchParams;

  let trips: MarketplaceTrip[] = [];
  let meta: SearchMeta | null = null;
  let backendDown = false;

  try {
    const result = await searchTripsServer({
      origin_city_id: params.origin,
      destination_city_id: params.destination,
      date: params.date,
      per_page: "50",
    });
    trips = result.data;
    meta = result.meta;
  } catch {
    backendDown = true;
  }

  return (
    <ResultsContent
      trips={trips}
      meta={meta}
      backendDown={backendDown}
      initialOrigin={params.origin ?? ""}
      initialDestination={params.destination ?? ""}
      initialDate={params.date ?? ""}
    />
  );
}
