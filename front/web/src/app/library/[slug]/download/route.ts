import { NextResponse } from 'next/server';
import { getResource } from '@/modules/library/lib/registry';
import { getAccessCapabilities } from '@/modules/library/lib/access';

/**
 * Téléchargement d'une ressource (`/library/[slug]/download`).
 *
 * ⚠️ GARDE CÔTÉ SERVEUR : c'est ICI que le droit de téléchargement est
 * vérifié, jamais dans l'interface. Le fichier n'est JAMAIS exposé par une URL
 * publique permanente : la route renvoie une URL TEMPORAIRE SIGNÉE (ou 403).
 *
 * Règles appliquées :
 * - ressource inconnue / non publiée → 404 ;
 * - licence `allowDownload: false` ou modèle `read_only` → 403 ;
 * - `purchase` / `subscription` / `private` sans droit acquis → 403 ;
 * - `free` avec licence autorisant le téléchargement → URL signée temporaire.
 *
 * NOTE : le stockage objet n'est pas encore branché (aucun fichier réel dans le
 * catalogue de démarrage). La route renvoie donc 501 « non configuré » pour les
 * ressources autorisées, au lieu de prétendre servir un fichier inexistant.
 */

export const dynamic = 'force-dynamic';

export async function GET(
  _request: Request,
  { params }: { params: Promise<{ slug: string }> },
) {
  const { slug } = await params;
  const resource = getResource(slug);
  if (!resource) {
    return NextResponse.json({ error: 'not_found' }, { status: 404 });
  }

  // Contexte anonyme : aucun droit acquis n'est supposé.
  const capabilities = getAccessCapabilities(resource, { authenticated: false });

  if (!capabilities.canDownload) {
    return NextResponse.json(
      { error: 'download_forbidden', reason: resource.accessModel },
      { status: 403 },
    );
  }

  // Le stockage objet n'est pas encore branché : on ne sert pas de faux fichier.
  return NextResponse.json(
    {
      error: 'storage_not_configured',
      message:
        'Le stockage objet n’est pas encore configuré. Une URL temporaire signée sera renvoyée une fois le stockage branché.',
      slug: resource.slug,
    },
    { status: 501 },
  );
}
