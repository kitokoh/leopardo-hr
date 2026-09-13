import { buildLlmsFullTxt, LLMS_CACHE_CONTROL, LLMS_CONTENT_TYPE } from '@/lib/ai-search';
import { resolveSsrVitrineLang } from '@/lib/i18n';

/**
 * `/llms-full.txt` — version longue de `/llms.txt` : offres et tarifs,
 * questions fréquentes localisées, articles de blog et inventaire des pages.
 * Destinée à l'ingestion RAG (pas à l'affichage) : le contenu de fond y est
 * inliné pour éviter une seconde passe de crawl.
 *
 * Localisé via `?lang=` (fr par défaut), comme `llms.txt`.
 */
export const dynamic = 'force-dynamic';

export function GET(request: Request): Response {
  const url = new URL(request.url);
  const locale = resolveSsrVitrineLang(
    url.searchParams.get('lang'),
    request.headers.get('accept-language'),
  );

  return new Response(buildLlmsFullTxt(locale), {
    status: 200,
    headers: {
      'content-type': LLMS_CONTENT_TYPE,
      'cache-control': LLMS_CACHE_CONTROL,
    },
  });
}
