"use client";

import { Heart } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { useAccount } from "@/hooks/useAccount";
import { ApiError } from "@/lib/api";
import { addFavorite } from "@/lib/account";

interface FavoriteButtonProps {
  target: { target_type: "product"; product_id: number } | { target_type: "seller"; seller: string };
  className?: string;
}

/**
 * Bouton « Ajouter aux favoris » (#7814) — visiteur non connecté : renvoi
 * vers la connexion ; ajout idempotent côté serveur.
 */
export function FavoriteButton({ target, className }: FavoriteButtonProps) {
  const router = useRouter();
  const { session } = useAccount();
  const [state, setState] = useState<"idle" | "busy" | "done">("idle");
  const [error, setError] = useState<string | null>(null);

  const add = async () => {
    if (!session) {
      router.push("/compte/connexion");
      return;
    }
    setError(null);
    setState("busy");
    try {
      await addFavorite(target);
      setState("done");
    } catch (err) {
      setState("idle");
      setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    }
  };

  return (
    <div className={className}>
      <button
        type="button"
        onClick={add}
        disabled={state !== "idle"}
        className={`inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition ${
          state === "done"
            ? "border-amber-500 bg-amber-50 text-amber-700"
            : "border-stone-300 text-stone-700 hover:border-amber-500 hover:text-amber-700"
        } disabled:cursor-default`}
      >
        <Heart
          aria-hidden="true"
          className={`h-4 w-4 ${state === "done" ? "fill-amber-500 text-amber-500" : ""}`}
        />
        {state === "done" ? "Dans vos favoris" : state === "busy" ? "Ajout…" : "Ajouter aux favoris"}
      </button>
      {error ? (
        <p role="alert" className="mt-2 text-sm text-red-600">
          {error}
        </p>
      ) : null}
    </div>
  );
}
