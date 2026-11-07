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
 *
 * Les styles vivent dans une balise <style> (et non dans des objets `style`
 * inline) : c'est le motif recommandé par Next.js pour un global-error, et
 * cela évite de faire passer des valeurs CSS pour des chaînes utilisateur.
 */
const styles = `
  .ge-body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
    background: #f8fafc;
    color: #0f172a;
    padding: 1.5rem;
  }
  .ge-card {
    max-width: 32rem;
    width: 100%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    text-align: center;
  }
  .ge-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem;
  }
  .ge-text {
    font-size: 0.95rem;
    color: #475569;
    margin: 0 0 1.5rem;
  }
  .ge-ref {
    font-size: 0.75rem;
    color: #94a3b8;
    margin: 0 0 1.5rem;
  }
  .ge-btn {
    cursor: pointer;
    border: none;
    border-radius: 0.75rem;
    background: #047857;
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 700;
    padding: 0.75rem 1.5rem;
  }
`;

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <html lang="fr">
      <body className="ge-body">
        <style>{styles}</style>
        <main role="alert" className="ge-card">
          <h1 className="ge-title">Une erreur est survenue</h1>
          <p className="ge-text">
            L’application n’a pas pu s’afficher correctement. Vous pouvez réessayer ;
            si le problème persiste, rechargez la page.
          </p>
          {error.digest ? (
            <p className="ge-ref">Référence : {error.digest}</p>
          ) : null}
          <button type="button" onClick={reset} className="ge-btn">
            Réessayer
          </button>
        </main>
      </body>
    </html>
  );
}
