"use client";

import { Minus, Plus } from "lucide-react";

import { MAX_QUANTITY } from "@/lib/cart";

interface QuantityInputProps {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  label?: string;
  /** Taille compacte pour le panier. */
  compact?: boolean;
}

/** Sélecteur de quantité accessible (− / champ / +). */
export function QuantityInput({
  value,
  onChange,
  min = 1,
  label = "Quantité",
  compact = false,
}: QuantityInputProps) {
  const clamp = (next: number) => Math.min(Math.max(Math.round(next), min), MAX_QUANTITY);
  const size = compact ? "h-8 w-8" : "h-10 w-10";

  return (
    <div className="inline-flex items-center rounded-full border border-stone-300 bg-white">
      <button
        type="button"
        aria-label={`Diminuer la ${label.toLowerCase()}`}
        disabled={value <= min}
        onClick={() => onChange(clamp(value - 1))}
        className={`${size} flex items-center justify-center rounded-full text-stone-600 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-40`}
      >
        <Minus aria-hidden="true" className="h-4 w-4" />
      </button>
      <input
        type="number"
        inputMode="numeric"
        aria-label={label}
        min={min}
        max={MAX_QUANTITY}
        value={value}
        onChange={(event) => {
          const parsed = Number(event.target.value);
          if (Number.isFinite(parsed)) onChange(clamp(parsed));
        }}
        className={`${compact ? "w-10 text-sm" : "w-12"} border-0 bg-transparent text-center font-medium text-stone-900 [appearance:textfield] focus:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none`}
      />
      <button
        type="button"
        aria-label={`Augmenter la ${label.toLowerCase()}`}
        disabled={value >= MAX_QUANTITY}
        onClick={() => onChange(clamp(value + 1))}
        className={`${size} flex items-center justify-center rounded-full text-stone-600 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-40`}
      >
        <Plus aria-hidden="true" className="h-4 w-4" />
      </button>
    </div>
  );
}
