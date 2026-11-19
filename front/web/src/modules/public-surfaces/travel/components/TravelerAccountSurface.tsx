'use client';

/**
 * Compte voyageur — verticale Billets (#9628).
 *
 * Repris de `front/travel-web/src/components/account-dashboard.tsx` (#7739).
 *
 * ## Ce que ce compte ajoute au suivi par référence
 *
 * Le suivi par référence + code reste le moyen d'accès principal, sans compte.
 * Le compte apporte la **vue cross-agences** : toutes les réservations d'un
 * même voyageur, quel que soit l'opérateur, bornées au compte côté backend.
 *
 * ## Session
 *
 * Le jeton vit dans un cookie `HttpOnly` posé par l'API : ce composant ne
 * manipule aucun credential, seulement le profil d'affichage.
 */

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';

import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { formatPrice } from '../../kernel/domain/money';
import { restoreSession, persistSession, forgetSession } from '../../retail/application/load-buyer-session';
import type { BuyerSession } from '../../retail/domain/buyer';
import { loginBuyer, logoutBuyer, registerBuyer, AccountApiError } from '../../retail/infrastructure/account-gateway';
import { fetchMyBookings, type CustomerBooking } from '../infrastructure/account-gateway';

type Mode = 'login' | 'register';

const INPUT_STYLE = {
  height: '2.5rem',
  width: '100%',
  borderRadius: '0.6rem',
  border: '1px solid #D6D3D1',
  padding: '0 0.75rem',
  fontSize: '0.88rem',
} as const;

export function TravelerAccountSurface({ identity }: { identity: SurfaceIdentity }) {
  const [ready, setReady] = useState(false);
  const [session, setSession] = useState<BuyerSession | null>(null);
  const [bookings, setBookings] = useState<CustomerBooking[] | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [mode, setMode] = useState<Mode>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadBookings = useCallback(async () => {
    setLoadError(null);

    const result = await fetchMyBookings();

    if (result === null) {
      setLoadError('Impossible de charger vos réservations pour le moment.');

      return;
    }

    setBookings(result);
  }, []);

  useEffect(() => {
    let cancelled = false;

    void restoreSession().then((restored) => {
      if (cancelled) {
        return;
      }

      setSession(restored);
      setReady(true);

      if (restored !== null) {
        void loadBookings();
      }
    });

    return () => {
      cancelled = true;
    };
  }, [loadBookings]);

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
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
          : await registerBuyer({ name: name.trim(), email: email.trim(), password });

      setSession(persistSession(next.buyer));
      void loadBookings();
    } catch (caught) {
      if (caught instanceof AccountApiError && caught.status === 401) {
        setError('E-mail ou mot de passe incorrect.');
      } else {
        setError(caught instanceof Error ? caught.message : 'Une erreur est survenue.');
      }
    } finally {
      setPending(false);
    }
  };

  const signOut = async () => {
    try {
      await logoutBuyer();
    } catch {
      // La session locale est purgée de toute façon.
    }

    forgetSession();
    setSession(null);
    setBookings(null);
  };

  if (!ready) {
    return <SurfaceNotice identity={identity}>Chargement de votre compte…</SurfaceNotice>;
  }

  if (session === null) {
    return (
      <div style={{ display: 'grid', gap: '0.75rem', maxWidth: '26rem' }}>
        <div
          role="tablist"
          aria-label="Connexion ou inscription"
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
              ['login', 'Se connecter'],
              ['register', "S'inscrire"],
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
              }}
            >
              {label}
            </button>
          ))}
        </div>

        <form onSubmit={(event) => void submit(event)} style={{ display: 'grid', gap: '0.6rem' }}>
          {mode === 'register' ? (
            <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
              Nom complet
              <input
                type="text"
                required
                value={name}
                onChange={(event) => setName(event.target.value)}
                style={INPUT_STYLE}
              />
            </label>
          ) : null}

          <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
            Adresse e-mail
            <input
              type="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              style={INPUT_STYLE}
            />
          </label>

          <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
            Mot de passe
            <input
              type="password"
              required
              minLength={mode === 'register' ? 8 : 1}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              style={INPUT_STYLE}
            />
          </label>

          {error !== null ? (
            <p role="alert" style={{ margin: 0, fontSize: '0.8rem', color: '#9F1239' }}>
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={pending}
            style={{
              padding: '0.7rem',
              borderRadius: '999px',
              border: 'none',
              background: identity.palette.primary,
              color: identity.palette.onPrimary,
              fontWeight: 700,
              cursor: pending ? 'not-allowed' : 'pointer',
              opacity: pending ? 0.6 : 1,
            }}
          >
            {mode === 'login' ? 'Se connecter' : 'Créer mon compte'}
          </button>
        </form>

        <p style={{ margin: 0, fontSize: '0.75rem', color: '#A8A29E' }}>
          Sans compte, vous pouvez suivre une réservation avec sa référence et son code.
        </p>
      </div>
    );
  }

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <SurfaceCard identity={identity}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem' }}>
          <div>
            <h2 style={{ margin: 0, fontSize: '1.05rem', fontWeight: 800 }}>
              {session.buyer.name}
            </h2>
            <p style={{ margin: '0.15rem 0 0', fontSize: '0.82rem', color: '#78716C' }}>
              {session.buyer.email}
            </p>
          </div>
          <button
            type="button"
            onClick={() => void signOut()}
            style={{
              padding: '0.45rem 0.9rem',
              borderRadius: '999px',
              border: '1px solid #D6D3D1',
              background: '#FFFFFF',
              fontSize: '0.8rem',
              fontWeight: 600,
              cursor: 'pointer',
            }}
          >
            Se déconnecter
          </button>
        </div>
      </SurfaceCard>

      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Mes réservations</h2>
        <p style={{ margin: '0.15rem 0 0', fontSize: '0.78rem', color: '#78716C' }}>
          Toutes agences confondues.
        </p>

        {loadError !== null ? (
          <SurfaceNotice identity={identity} alert>
            {loadError}
          </SurfaceNotice>
        ) : bookings === null ? (
          <p style={{ margin: '0.4rem 0 0', fontSize: '0.82rem', color: '#78716C' }}>
            Chargement…
          </p>
        ) : bookings.length === 0 ? (
          <p style={{ margin: '0.4rem 0 0', fontSize: '0.82rem', color: '#78716C' }}>
            Aucune réservation pour l&apos;instant. <Link href="/billets">Rechercher un trajet</Link>
          </p>
        ) : (
          <ul style={{ margin: '0.5rem 0 0', padding: 0, listStyle: 'none', display: 'grid', gap: '0.5rem' }}>
            {bookings.map((booking) => (
              <li
                key={booking.reference}
                style={{ borderTop: '1px solid #F5F5F4', paddingTop: '0.5rem' }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.5rem' }}>
                  <div>
                    <p style={{ margin: 0, fontSize: '0.85rem', fontWeight: 700 }}>
                      {booking.originCity ?? '—'} → {booking.destinationCity ?? '—'}
                    </p>
                    <p style={{ margin: '0.1rem 0 0', fontSize: '0.78rem', color: '#78716C' }}>
                      {booking.departureDate ?? ''} {booking.departureTime ?? ''}
                      {booking.agencyName !== null ? ` · ${booking.agencyName}` : ''}
                    </p>
                  </div>
                  <div style={{ textAlign: 'right' }}>
                    <p style={{ margin: 0, fontSize: '0.85rem', fontWeight: 700 }}>
                      {formatPrice(booking.totalMinor, booking.currency) ?? '—'}
                    </p>
                    <SurfaceBadge identity={identity} tone="neutral">
                      {booking.status}
                    </SurfaceBadge>
                  </div>
                </div>
                <p style={{ margin: '0.25rem 0 0', fontSize: '0.75rem', color: '#A8A29E' }}>
                  {booking.reference} · {booking.passengerCount} passager
                  {booking.passengerCount > 1 ? 's' : ''}
                </p>
              </li>
            ))}
          </ul>
        )}
      </SurfaceCard>
    </div>
  );
}
