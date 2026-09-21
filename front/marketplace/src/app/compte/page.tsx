"use client";

/**
 * Mon compte — /compte (#7814).
 *
 * Sans session : formulaire connexion OU inscription légère (bascule).
 * Avec session : profil + liens Mes commandes / Mes favoris + déconnexion.
 * Depuis #8022, le jeton acheteur (`mkb_…`) vit en cookie HttpOnly posé
 * par l'API — seul le profil public reste persisté côté navigateur.
 */

import { Heart, Loader2, LogOut, PackageSearch, UserRound } from "lucide-react";
import Link from "next/link";
import { useState, type FormEvent } from "react";

import { useBuyer } from "@/hooks/useBuyer";
import { ApiError, loginBuyer, registerBuyer } from "@/lib/api";

type Mode = "login" | "register";

const inputClass =
  "h-11 w-full rounded-xl border border-stone-300 bg-white px-3.5 text-sm text-stone-900 placeholder:text-stone-400 transition focus:border-amber-500 focus:outline-none";

export default function AccountPage() {
  const { ready, session, signIn, signOut } = useBuyer();
  const [mode, setMode] = useState<Mode>("login");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (pending) return;
    setPending(true);
    setError(null);
    try {
      const nextSession =
        mode === "login"
          ? await loginBuyer({ email: email.trim(), password })
          : await registerBuyer({
              name: name.trim(),
              email: email.trim(),
              password,
              ...(phone.trim().length > 0 ? { phone: phone.trim() } : {}),
            });
      signIn(nextSession);
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        setError("E-mail ou mot de passe incorrect.");
      } else if (err instanceof ApiError && err.status === 422) {
        setError(
          mode === "register"
            ? "Vérifiez les champs : e-mail déjà utilisé ou mot de passe trop court (8 caractères minimum)."
            : "Vérifiez les champs saisis.",
        );
      } else {
        setError(err instanceof Error ? err.message : "Une erreur est survenue.");
      }
    } finally {
      setPending(false);
    }
  };

  if (!ready) {
    return (
      <div className="mx-auto flex max-w-md items-center justify-center px-4 py-24" aria-busy="true">
        <Loader2 aria-hidden="true" className="h-6 w-6 animate-spin text-amber-600" />
        <span className="sr-only">Chargement…</span>
      </div>
    );
  }

  if (session) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-10">
        <div className="flex items-center gap-4">
          <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
            <UserRound aria-hidden="true" className="h-7 w-7" />
          </span>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-stone-900">
              Bonjour, {session.buyer.name}
            </h1>
            <p className="text-sm text-stone-500">{session.buyer.email}</p>
          </div>
        </div>

        <nav aria-label="Mon compte" className="mt-8 grid gap-3 sm:grid-cols-2">
          <Link
            href="/compte/commandes"
            className="group flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:border-amber-300"
          >
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
              <PackageSearch aria-hidden="true" className="h-5 w-5" />
            </span>
            <span>
              <span className="block text-sm font-semibold text-stone-900 group-hover:text-amber-700">
                Mes commandes
              </span>
              <span className="text-xs text-stone-500">
                Historique, suivi et avis après livraison
              </span>
            </span>
          </Link>
          <Link
            href="/compte/favoris"
            className="group flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:border-amber-300"
          >
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
              <Heart aria-hidden="true" className="h-5 w-5" />
            </span>
            <span>
              <span className="block text-sm font-semibold text-stone-900 group-hover:text-amber-700">
                Mes favoris
              </span>
              <span className="text-xs text-stone-500">Vos produits mis de côté</span>
            </span>
          </Link>
        </nav>

        <button
          type="button"
          onClick={() => void signOut()}
          className="mt-8 inline-flex items-center gap-2 rounded-full border border-stone-300 px-5 py-2.5 text-sm font-medium text-stone-700 transition hover:border-rose-300 hover:text-rose-600"
        >
          <LogOut aria-hidden="true" className="h-4 w-4" />
          Se déconnecter
        </button>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-md px-4 py-10">
      <h1 className="text-2xl font-bold tracking-tight text-stone-900">
        {mode === "login" ? "Connexion" : "Créer un compte"}
      </h1>
      <p className="mt-1 text-sm text-stone-500">
        {mode === "login"
          ? "Retrouvez vos commandes, favoris et avis."
          : "Inscription rapide : nom, e-mail et mot de passe suffisent."}
      </p>

      <div role="tablist" aria-label="Connexion ou inscription" className="mt-6 grid grid-cols-2 rounded-full bg-stone-100 p-1">
        {(
          [
            ["login", "Se connecter"],
            ["register", "S'inscrire"],
          ] as [Mode, string][]
        ).map(([value, label]) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={mode === value}
            onClick={() => {
              setMode(value);
              setError(null);
            }}
            className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
              mode === value ? "bg-white text-stone-900 shadow-sm" : "text-stone-500 hover:text-stone-700"
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      <form onSubmit={(event) => void submit(event)} className="mt-6 flex flex-col gap-4">
        {mode === "register" ? (
          <label className="flex flex-col gap-1 text-sm font-medium text-stone-700">
            Nom complet
            <input
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
              maxLength={160}
              autoComplete="name"
              placeholder="Awa Ndiaye"
              className={inputClass}
            />
          </label>
        ) : null}

        <label className="flex flex-col gap-1 text-sm font-medium text-stone-700">
          Adresse e-mail
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
            maxLength={160}
            autoComplete="email"
            placeholder="vous@exemple.com"
            className={inputClass}
          />
        </label>

        {mode === "register" ? (
          <label className="flex flex-col gap-1 text-sm font-medium text-stone-700">
            Téléphone (optionnel)
            <input
              type="tel"
              value={phone}
              onChange={(event) => setPhone(event.target.value)}
              maxLength={40}
              autoComplete="tel"
              placeholder="+221 77 000 00 00"
              className={inputClass}
            />
          </label>
        ) : null}

        <label className="flex flex-col gap-1 text-sm font-medium text-stone-700">
          Mot de passe
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            minLength={mode === "register" ? 8 : 1}
            maxLength={100}
            autoComplete={mode === "register" ? "new-password" : "current-password"}
            placeholder={mode === "register" ? "8 caractères minimum" : "Votre mot de passe"}
            className={inputClass}
          />
        </label>

        {error ? (
          <p role="alert" className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {error}
          </p>
        ) : null}

        <button
          type="submit"
          disabled={pending}
          className="mt-1 flex h-11 items-center justify-center gap-2 rounded-full bg-amber-600 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {pending ? <Loader2 aria-hidden="true" className="h-4 w-4 animate-spin" /> : null}
          {mode === "login" ? "Se connecter" : "Créer mon compte"}
        </button>
      </form>

      <p className="mt-6 text-xs text-stone-400">
        Vos données restent minimales (RGPD) : elles servent uniquement à suivre vos commandes,
        favoris et avis sur Leopardo Marché.
      </p>
    </div>
  );
}
