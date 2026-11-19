'use client';

/**
 * Avis produit — verticale Retail (#9627).
 *
 * Repris de `front/marketplace/src/components/ReviewsSection.tsx` et
 * `ReviewForm.tsx`. Un avis est **vérifié** : il n'est possible qu'après une
 * commande livrée contenant le produit. Le domaine expose `canReview` pour que
 * le formulaire ne soit pas affiché quand il serait refusé.
 */

import { useEffect, useState, type FormEvent } from 'react';

import { SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { isValidRating, type ProductReviews } from '../domain/buyer';
import { fetchProductReviews, submitReview } from '../infrastructure/account-gateway';

export function ProductReviewsSurface({
  identity,
  productId,
  orderReference,
}: {
  identity: SurfaceIdentity;
  productId: number;
  /** Référence de commande livrée — active le formulaire d'avis. */
  orderReference?: string;
}) {
  const [reviews, setReviews] = useState<ProductReviews | null>(null);
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);

  useEffect(() => {
    let cancelled = false;

    void fetchProductReviews(productId).then((result) => {
      if (!cancelled) {
        setReviews(result);
      }
    });

    return () => {
      cancelled = true;
    };
  }, [productId]);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (orderReference === undefined || pending) {
      return;
    }

    if (!isValidRating(rating)) {
      setError('Choisissez une note entre 1 et 5.');

      return;
    }

    setPending(true);
    setError(null);

    try {
      await submitReview({ orderReference, productId, rating, comment });
      setSent(true);
      setComment('');
      setReviews(await fetchProductReviews(productId));
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "L'avis n'a pas pu être envoyé.");
    } finally {
      setPending(false);
    }
  };

  return (
    <div style={{ display: 'grid', gap: '0.9rem' }}>
      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>
          Avis
          {reviews !== null && reviews.ratingCount > 0
            ? ` — ${reviews.ratingAvg?.toFixed(1) ?? '—'}/5 (${reviews.ratingCount})`
            : ''}
        </h2>

        {reviews === null ? (
          <p style={{ margin: '0.3rem 0 0', fontSize: '0.82rem', color: '#78716C' }}>
            Avis indisponibles pour le moment.
          </p>
        ) : reviews.reviews.length === 0 ? (
          <p style={{ margin: '0.3rem 0 0', fontSize: '0.82rem', color: '#78716C' }}>
            Aucun avis pour l&apos;instant.
          </p>
        ) : (
          <ul style={{ margin: '0.4rem 0 0', padding: 0, listStyle: 'none', display: 'grid', gap: '0.5rem' }}>
            {reviews.reviews.map((review, index) => (
              <li key={index} style={{ borderTop: index === 0 ? 'none' : '1px solid #F5F5F4', paddingTop: index === 0 ? 0 : '0.5rem' }}>
                <p style={{ margin: 0, fontSize: '0.82rem', fontWeight: 700 }}>
                  {'★'.repeat(review.rating)}
                  <span style={{ color: '#D6D3D1' }}>{'★'.repeat(5 - review.rating)}</span>
                  <span style={{ marginLeft: '0.4rem', fontWeight: 500, color: '#78716C' }}>
                    {review.buyerName}
                  </span>
                </p>
                {review.comment !== null ? (
                  <p style={{ margin: '0.15rem 0 0', fontSize: '0.82rem', color: '#57534E' }}>
                    {review.comment}
                  </p>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </SurfaceCard>

      {orderReference !== undefined ? (
        <SurfaceCard identity={identity}>
          <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Laisser un avis</h2>

          {sent ? (
            <SurfaceNotice identity={identity}>Merci, votre avis a été publié.</SurfaceNotice>
          ) : (
            <form onSubmit={submit} style={{ display: 'grid', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
                Note
                <select
                  value={rating}
                  onChange={(event) => setRating(Number(event.target.value))}
                  style={{
                    height: '2.5rem',
                    width: '100%',
                    borderRadius: '0.6rem',
                    border: '1px solid #D6D3D1',
                    padding: '0 0.75rem',
                    fontSize: '0.88rem',
                  }}
                >
                  {[5, 4, 3, 2, 1].map((value) => (
                    <option key={value} value={value}>
                      {value} étoile{value > 1 ? 's' : ''}
                    </option>
                  ))}
                </select>
              </label>

              <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
                Commentaire (facultatif)
                <textarea
                  rows={3}
                  value={comment}
                  onChange={(event) => setComment(event.target.value)}
                  maxLength={1000}
                  style={{
                    width: '100%',
                    borderRadius: '0.6rem',
                    border: '1px solid #D6D3D1',
                    padding: '0.6rem 0.75rem',
                    fontSize: '0.88rem',
                  }}
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
                  justifySelf: 'start',
                  padding: '0.6rem 1.1rem',
                  borderRadius: '0.6rem',
                  border: 'none',
                  background: identity.palette.primary,
                  color: identity.palette.onPrimary,
                  fontWeight: 700,
                  cursor: pending ? 'not-allowed' : 'pointer',
                  opacity: pending ? 0.6 : 1,
                }}
              >
                {pending ? 'Envoi…' : 'Publier mon avis'}
              </button>
            </form>
          )}
        </SurfaceCard>
      ) : null}
    </div>
  );
}
