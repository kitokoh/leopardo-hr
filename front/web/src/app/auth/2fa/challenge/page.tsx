import { Suspense } from 'react';
import { ChallengeForm } from './ChallengeForm';

export const metadata = {
  robots: { index: false, follow: false },
};

/**
 * Issue #5612 — page de challenge 2FA (code TOTP / code de récupération).
 * Accessible sans session (pré-auth) : le challenge_token à usage unique
 * est lu dans sessionStorage, posé par /auth/login quand le backend répond
 * mfa_challenge:true.
 */
export default function TwoFactorChallengePage() {
  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10 text-slate-950 dark:text-white sm:px-6 lg:px-8">
      <div className="auth-surface-dots absolute inset-0 z-0 opacity-10" />
      <div className="auth-surface-glow absolute inset-0 z-0 opacity-70" />

      <div className="relative z-10 w-full max-w-md">
        <Suspense>
          <ChallengeForm />
        </Suspense>
      </div>
    </main>
  );
}
