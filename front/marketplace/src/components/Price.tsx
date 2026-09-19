import { formatPrice } from "@/lib/format";

interface PriceProps {
  priceMinor: number;
  currency: string;
  className?: string;
}

/** Prix formaté fr-FR à partir des minor units. */
export function Price({ priceMinor, currency, className }: PriceProps) {
  return (
    <span className={className ?? "font-semibold text-stone-900"}>
      {formatPrice(priceMinor, currency)}
    </span>
  );
}
