'use client';

/**
 * global-error.tsx — filet de sécurité racine (App Router).
 *
 * Attrape les erreurs non gérées qui remontent jusqu'au layout racine (donc
 * hors du périmètre de `(dashboard)/error.tsx` et `(landing)/error.tsx`).
 * Il remplace le layout racine : il doit donc rendre ses propres <html>/<body>.
 *
 * Audit UX 2026-11 — le rapport relevait l'absence de `global-error.tsx` :
 * une erreur racine affichait l'écran d'erreur par défaut de Next (non
 * localisé, sans reprise possible).
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <html lang="fr">
      <body
        style={{
          margin: 0,
          minHeight: '100vh',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontFamily:
            'system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
          background: '#f8fafc',
          color: '#0f172a',
          padding: '1.5rem',
        }}
      >
        <main
          role="alert"
          style={{
            maxWidth: '32rem',
            width: '100%',
            background: '#ffffff',
            border: '1px solid #e2e8f0',
            borderRadius: '1rem',
            padding: '2rem',
            boxShadow: '0 10px 30px rgba(15, 23, 42, 0.08)',
            textAlign: 'center',
          }}
        >
          <h1 style={{ fontSize: '1.5rem', fontWeight: 800, margin: '0 0 0.5rem' }}>
            Une erreur est survenue
          </h1>
          <p style={{ fontSize: '0.95rem', color: '#475569', margin: '0 0 1.5rem' }}>
            L’application n’a pas pu s’afficher correctement. Vous pouvez réessayer ;
            si le problème persiste, rechargez la page.
          </p>
          {error.digest ? (
            <p style={{ fontSize: '0.75rem', color: '#94a3b8', margin: '0 0 1.5rem' }}>
              Référence : {error.digest}
            </p>
          ) : null}
          <button
            type="button"
            onClick={reset}
            style={{
              cursor: 'pointer',
              border: 'none',
              borderRadius: '0.75rem',
              background: '#047857',
              color: '#ffffff',
              fontSize: '0.95rem',
              fontWeight: 700,
              padding: '0.75rem 1.5rem',
            }}
          >
            Réessayer
          </button>
        </main>
      </body>
    </html>
  );
}
