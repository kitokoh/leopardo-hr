"use client";

import { RefreshCcw, ServerCrash } from "lucide-react";

/** Filet de sécurité global : erreur inattendue (API injoignable, etc.). */
export default function GlobalError({ reset }: { error: Error; reset: () => void }) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-16">
      <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
        <span className="rounded-full bg-red-50 p-4">
          <ServerCrash aria-hidden="true" className="h-8 w-8 text-red-500" />
        </span>
        <h1 className="text-lg font-semibold text-stone-900">Une erreur est survenue</h1>
        <p className="max-w-md text-sm text-stone-500">
          Le marché est momentanément indisponible. Vérifiez votre connexion puis réessayez.
        </p>
        <button
          type="button"
          onClick={reset}
          className="mt-2 inline-flex items-center gap-2 rounded-full bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700"
        >
          <RefreshCcw aria-hidden="true" className="h-4 w-4" />
          Réessayer
        </button>
      </div>
    </div>
  );
}
