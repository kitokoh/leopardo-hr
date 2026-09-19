"use client";

import { LogIn } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

import { ApiError } from "@/lib/api";
import { loginAccount } from "@/lib/account";

/** Connexion acheteur (#7814) — formulaire sobre, erreurs mises en mots. */
export function AccountLoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await loginAccount({ email: email.trim(), password });
      router.push("/compte");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <div className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
        <span className="mb-4 inline-flex rounded-full bg-amber-50 p-3">
          <LogIn aria-hidden="true" className="h-6 w-6 text-amber-600" />
        </span>
        <h1 className="text-2xl font-bold tracking-tight text-stone-900">Connexion</h1>
        <p className="mt-1 text-sm text-stone-500">
          Retrouvez vos commandes, favoris et avis.
        </p>

        <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
          <div>
            <label htmlFor="login-email" className="mb-1 block text-sm font-medium text-stone-700">
              Adresse e-mail
            </label>
            <input
              id="login-email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="h-11 w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 text-sm text-stone-900 transition focus:border-amber-500 focus:bg-white"
            />
          </div>
          <div>
            <label htmlFor="login-password" className="mb-1 block text-sm font-medium text-stone-700">
              Mot de passe
            </label>
            <input
              id="login-password"
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="h-11 w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 text-sm text-stone-900 transition focus:border-amber-500 focus:bg-white"
            />
          </div>

          {error ? (
            <p role="alert" className="rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={busy}
            className="h-11 w-full rounded-full bg-amber-600 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {busy ? "Connexion…" : "Se connecter"}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-stone-500">
          Pas encore de compte ?{" "}
          <Link href="/compte/inscription" className="font-semibold text-amber-700 transition hover:text-amber-800">
            Créer un compte
          </Link>
        </p>
      </div>
    </div>
  );
}
