"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";

import { ApiError } from "@/lib/api";
import { useAccount } from "@/lib/account-provider";
import { useLocale } from "@/lib/locale-provider";

/**
 * Connexion au compte client (issue #7739) — formulaire e-mail + mot de
 * passe, erreurs 401 (indifférenciées) et 423 (verrouillage) traduites.
 */
export function AccountLoginForm() {
  const { dict } = useLocale();
  const { login } = useAccount();
  const router = useRouter();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      await login(email.trim(), password);
      router.push("/account");
    } catch (err) {
      if (err instanceof ApiError && err.status === 423) {
        setError(dict.account.locked);
      } else if (err instanceof ApiError && err.status === 401) {
        setError(dict.account.invalidCredentials);
      } else {
        setError(err instanceof ApiError ? err.message : dict.common.error);
      }
      setSubmitting(false);
    }
  };

  return (
    <div className="mx-auto w-full max-w-md px-4 py-10">
      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 className="text-xl font-bold text-slate-900">{dict.account.loginTitle}</h1>
        <p className="mt-1 text-sm text-slate-500">{dict.account.loginSubtitle}</p>

        <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">{dict.account.email}</span>
            <input
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">{dict.account.password}</span>
            <input
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none"
            />
          </label>

          {error ? (
            <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={submitting}
            className="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60"
          >
            {submitting ? dict.account.loggingIn : dict.account.loginSubmit}
          </button>
        </form>

        <p className="mt-5 text-sm text-slate-600">
          {dict.account.noAccount}{" "}
          <Link href="/account/register" className="font-semibold text-brand-700 hover:underline">
            {dict.account.registerCta}
          </Link>
        </p>
      </section>
    </div>
  );
}
