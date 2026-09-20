"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";

import { ApiError } from "@/lib/api";
import { useAccount } from "@/lib/account-provider";
import { useLocale } from "@/lib/locale-provider";

/**
 * Inscription au compte client (issue #7739). Les erreurs de validation
 * backend (e-mail déjà pris, mot de passe trop faible…) sont affichées
 * telles quelles (message Laravel localisé).
 */
export function AccountRegisterForm() {
  const { dict } = useLocale();
  const { register } = useAccount();
  const router = useRouter();

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const { claimedBookings } = await register({
        name: name.trim(),
        email: email.trim(),
        phone: phone.trim() || undefined,
        password,
      });
      router.push(
        claimedBookings > 0 ? `/account?claimed=${claimedBookings}` : "/account",
      );
    } catch (err) {
      setError(err instanceof ApiError ? err.message : dict.common.error);
      setSubmitting(false);
    }
  };

  return (
    <div className="mx-auto w-full max-w-md px-4 py-10">
      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 className="text-xl font-bold text-slate-900">{dict.account.registerTitle}</h1>
        <p className="mt-1 text-sm text-slate-500">{dict.account.registerSubtitle}</p>

        <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">{dict.account.name}</span>
            <input
              type="text"
              required
              maxLength={160}
              autoComplete="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none"
            />
          </label>

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
            <span className="font-medium text-slate-700">{dict.account.phone}</span>
            <input
              type="tel"
              maxLength={40}
              autoComplete="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">{dict.account.password}</span>
            <input
              type="password"
              required
              minLength={12}
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-brand-500 focus:outline-none"
            />
            <span className="text-xs text-slate-500">{dict.account.passwordHint}</span>
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
            {submitting ? dict.account.registering : dict.account.registerSubmit}
          </button>
        </form>

        <p className="mt-5 text-sm text-slate-600">
          {dict.account.haveAccount}{" "}
          <Link href="/account/login" className="font-semibold text-brand-700 hover:underline">
            {dict.account.loginCta}
          </Link>
        </p>
      </section>
    </div>
  );
}
