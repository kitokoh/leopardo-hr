'use client';

/**
 * Compte acheteur — verticale Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/compte/page.tsx`. Sans session :
 * connexion ou inscription légère (bascule). Avec session : profil + liens
 * vers les commandes et les favoris.
 *
 * La session est un cookie `HttpOnly` : ce composant ne manipule **aucun**
 * jeton, seulement le profil d'affichage.
 */

import Link from 'next/link';
import { useEffect, useState, type FormEvent } from 'react';

import { RETAIL_ACCOUNT_COPY as C } from '../../copy/retail-copy';
import { SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { forgetSession, persistSession, restoreSession } from '../application/load-buyer-session';
import type { BuyerSession } from '../domain/buyer';
import {
  AccountApiError,
  loginBuyer,
  logoutBuyer,
  registerBuyer,
} from '../infrastructure/account-gateway';

type Mode = 'login' | 'register';

const INPUT_STYLE = {
  height: '2.75rem',
  width: '100%',
  borderRadius: '0.75rem',
  border: '1px solid #D6D3D1',
  padding: '0 0.9rem',
  fontSize: '0.9rem',
} as const;

const LABEL_STYLE = {
  display: 'flex',
  flexDirection: 'column',
  gap: '0.25rem',
  fontSize: '0.85rem',
  fontWeight: 600,
  color: '#44403C',
} as const;

export function BuyerAccountSurface({ identity }: { identity: SurfaceIdentity }) {
  const [ready, setReady] = useState(false);
  const [session, setSession] = useState<BuyerSession | null>(null);
  const [mode, setMode] = useState<Mode>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    void restoreSession().then((restored) => {
      if (!cancelled) {
        setSession(restored);
        setReady(true);
      }
    });

    return () => {
      cancelled = true;
    };
  }, []);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (pending) {
      return;
    }

    setPending(true);
    setError(null);

    try {
      const next =
        mode === 'login'
          ? await loginBuyer({ email: email.trim(), password })
          : await registerBuyer({
              name: name.trim(),
              email: email.trim(),
              password,
              ...(phone.trim() !== '' ? { phone: phone.trim() } : {}),
            });

      setSession(persistSession(next.buyer));
    } catch (caught) {
      if (caught instanceof AccountApiError && caught.status === 401) {
        setError(C.errorInvalidCredentials);
      } else if (caught instanceof AccountApiError && caught.status === 422) {
        setError(
          mode === 'register'
            ? C.errorRegisterFields
            : C.errorFields,
        );
      } else {
        setError(caught instanceof Error ? caught.message : C.errorGeneric);
      }
    } finally {
      setPending(false);
    }
  };

  const signOut = async () => {
    try {
      await logoutBuyer();
    } catch {
      // La session locale est purgée de toute façon : un échec réseau ne doit
      // pas laisser l'acheteur « connecté » à l'écran.
    }

    forgetSession();
    setSession(null);
  };

  if (!ready) {
    return <SurfaceNotice identity={identity}>{C.loading}</SurfaceNotice>;
  }

  if (session !== null) {
    return (
      <div style={{ display: 'grid', gap: '1rem' }}>
        <SurfaceCard identity={identity}>
          <h2 style={{ margin: 0, fontSize: '1.15rem', fontWeight: 800 }}>
            {C.greeting(session.buyer.name)}
          </h2>
          <p style={{ margin: 0, fontSize: '0.85rem', color: '#78716C' }}>{session.buyer.email}</p>
        </SurfaceCard>

        <div style={{ display: 'grid', gap: '0.75rem', gridTemplateColumns: '1fr 1fr' }}>
          <Link
            href="/compte/commandes"
            style={{
              display: 'block',
              padding: '1rem',
              borderRadius: '0.9rem',
              border: '1px solid #E7E5E4',
              background: '#FFFFFF',
              textDecoration: 'none',
              color: '#1C1917',
            }}
          >
            <span style={{ display: 'block', fontSize: '0.9rem', fontWeight: 700 }}>
              {C.ordersTitle}
            </span>
            <span style={{ fontSize: '0.78rem', color: '#78716C' }}>
              {C.ordersHint}
            </span>
          </Link>

          <Link
            href="/compte/favoris"
            style={{
              display: 'block',
              padding: '1rem',
              borderRadius: '0.9rem',
              border: '1px solid #E7E5E4',
              background: '#FFFFFF',
              textDecoration: 'none',
              color: '#1C1917',
            }}
          >
            <span style={{ display: 'block', fontSize: '0.9rem', fontWeight: 700 }}>
              {C.favoritesTitle}
            </span>
            <span style={{ fontSize: '0.78rem', color: '#78716C' }}>
              {C.favoritesHint}
            </span>
          </Link>
        </div>

        <button
          type="button"
          onClick={() => void signOut()}
          style={{
            justifySelf: 'start',
            padding: '0.6rem 1.1rem',
            borderRadius: '999px',
            border: '1px solid #D6D3D1',
            background: '#FFFFFF',
            fontSize: '0.85rem',
            fontWeight: 600,
            cursor: 'pointer',
          }}
        >
          {C.signOut}
        </button>
      </div>
    );
  }

  return (
    <div style={{ display: 'grid', gap: '1rem', maxWidth: '28rem' }}>
      <div>
        <h2 style={{ margin: 0, fontSize: '1.15rem', fontWeight: 800 }}>
          {mode === 'login' ? C.loginTitle : C.registerTitle}
        </h2>
        <p style={{ margin: '0.2rem 0 0', fontSize: '0.85rem', color: '#78716C' }}>
          {mode === 'login'
            ? C.loginHint
            : C.registerHint}
        </p>
      </div>

      <div
        role="tablist"
        aria-label={C.tablistLabel}
        style={{
          display: 'grid',
          gridTemplateColumns: '1fr 1fr',
          gap: '0.25rem',
          padding: '0.25rem',
          borderRadius: '999px',
          background: '#F5F5F4',
        }}
      >
        {(
          [
            ['login', C.tabLogin],
            ['register', C.tabRegister],
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
            style={{
              padding: '0.5rem',
              borderRadius: '999px',
              border: 'none',
              background: mode === value ? '#FFFFFF' : 'transparent',
              fontWeight: 700,
              fontSize: '0.85rem',
              cursor: 'pointer',
              boxShadow: mode === value ? '0 1px 2px rgba(15,23,42,0.06)' : 'none',
            }}
          >
            {label}
          </button>
        ))}
      </div>

      <form onSubmit={(event) => void submit(event)} style={{ display: 'grid', gap: '0.75rem' }}>
        {mode === 'register' ? (
          <label style={LABEL_STYLE}>
            {C.fieldName}
            <input
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
              maxLength={160}
              autoComplete="name"
              style={INPUT_STYLE}
            />
          </label>
        ) : null}

        <label style={LABEL_STYLE}>
          {C.fieldEmail}
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
            maxLength={160}
            autoComplete="email"
            style={INPUT_STYLE}
          />
        </label>

        {mode === 'register' ? (
          <label style={LABEL_STYLE}>
            {C.fieldPhone}
            <input
              type="tel"
              value={phone}
              onChange={(event) => setPhone(event.target.value)}
              maxLength={40}
              autoComplete="tel"
              style={INPUT_STYLE}
            />
          </label>
        ) : null}

        <label style={LABEL_STYLE}>
          {C.fieldPassword}
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            minLength={mode === 'register' ? 8 : 1}
            maxLength={100}
            autoComplete={mode === 'register' ? 'new-password' : 'current-password'}
            style={INPUT_STYLE}
          />
        </label>

        {error !== null ? (
          <p
            role="alert"
            style={{
              margin: 0,
              padding: '0.7rem 0.9rem',
              borderRadius: '0.75rem',
              background: '#FFF1F2',
              color: '#9F1239',
              fontSize: '0.85rem',
            }}
          >
            {error}
          </p>
        ) : null}

        <button
          type="submit"
          disabled={pending}
          style={{
            marginTop: '0.25rem',
            padding: '0.75rem',
            borderRadius: '999px',
            border: 'none',
            background: identity.palette.primary,
            color: identity.palette.onPrimary,
            fontWeight: 700,
            cursor: pending ? 'not-allowed' : 'pointer',
            opacity: pending ? 0.6 : 1,
          }}
        >
          {mode === 'login' ? C.submitLogin : C.submitRegister}
        </button>
      </form>

      <p style={{ margin: 0, fontSize: '0.75rem', color: '#A8A29E' }}>
        {C.privacy}
      </p>
    </div>
  );
}
