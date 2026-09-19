"use client";

import { UserPlus } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

import { ApiError } from "@/lib/api";
import { registerAccount } from "@/lib/account";

/**
 * Inscription acheteur (#7814) — inscription légère : nom, e-mail, mot de
 * passe (min 12 + chiffres, règle serveur), téléphone optionnel. Les
 * commandes passées avec le même e-mail sont rattachées automatiquement.
 */
export function AccountRegisterForm() {
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    if (password.length < 12 || !/\d/.test(password)) {
      setError("Le mot de passe doit contenir au moins 12 caractères, dont un chiffre.");
      return;
    }

    setBusy(true);
    try {
      await registerAccount({
        name: name.trim(),
        email: email.trim(),
        ...(phone.trim() ? { phone: phone.trim() } : {}),
        password,
      });
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
          <UserPlus aria-hidden="true" className="h-6 w-6 text-amber-600" />
        </span>
        <h1 className="text-2xl font-bold tracking-tight text-stone-900">Créer un compte</h1>
        <p className="mt-1 text-sm text-stone-500">
          Vos commandes passées avec cet e-mail seront automatiquement rattachées.
        </p>

        <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
          <div>
            <label htmlFor="register-name" className="mb-1 block text-sm font-medium text-stone-700">
              Nom complet
            </label>
            <input
              id="register-name"
              type="text"
              required
              autoComplete="name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              className="h-11 w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 text-sm text-stone-900 transition focus:border-amber-500 focus:bg-white"
            />
          </div>
          <div>
            <label htmlFor="register-email" className="mb-1 block text-sm font-medium text-stone-700">
              Adresse e-mail
            </label>
            <input
              id="register-email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="h-11 w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 text-sm text-stone-900 transition focus:border-amber-500 focus:bg-white"
            />
          </div>
          <div>
            <label htmlFor="register-phone" className="mb-1 block text-sm font-medium text-stone-700">
              Téléphone <span className="font-normal text-stone-400">(optionnel)</span>
            </label>
            <input
              id="register-phone"
              type="tel"
              autoComplete="tel"
              value={phone}
              onChange={(event) => setPhone(event.target.value)}
              className="h-11 w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 text-sm text-stone-900 transition focus:border-amber-500 focus:bg-white"
            />
          </div>
          <div>
            <label htmlFor="register-password" className="mb-1 block text-sm font-medium text-stone-700">
              Mot de passe
            </label>
            <input
              id="register-password"
              type="password"
              required
              minLength={12}
              autoComplete="new-password"
              aria-describedby="register-password-help"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="h-11 w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 text-sm text-stone-900 transition focus:border-amber-500 focus:bg-white"
            />
            <p id="register-password-help" className="mt-1 text-xs text-stone-400">
              12 caractères minimum, dont un chiffre.
            </p>
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
            {busy ? "Création…" : "Créer mon compte"}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-stone-500">
          Déjà un compte ?{" "}
          <Link href="/compte/connexion" className="font-semibold text-amber-700 transition hover:text-amber-800">
            Se connecter
          </Link>
        </p>
      </div>
    </div>
  );
}
