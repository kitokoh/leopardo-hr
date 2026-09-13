import { buildLlmsTxt, LLMS_CACHE_CONTROL, LLMS_CONTENT_TYPE } from '@/lib/ai-search';
import { resolveSsrVitrineLang } from '@/lib/i18n';

/**
 * `/llms.txt` — inventaire markdown du site public pour les moteurs de
 * réponse et les assistants IA (convention llmstxt.org).
 *
 * Localisé via `?lang=` (même règle que le reste de la vitrine : `?lang=`
 * prime, puis Accept-Language, défaut `fr`) — cohérent avec les alternates
 * hreflang. Per-request : lecture de l'URL et des en-têtes → `force-dynamic`.
 */
export const dynamic = 'force-dynamic';

export function GET(request: Request): Response {
  const url = new URL(request.url);
  const locale = resolveSsrVitrineLang(
    url.searchParams.get('lang'),
    request.headers.get('accept-language'),
  );

  return new Response(buildLlmsTxt(locale), {
    status: 200,
    headers: {
      'content-type': LLMS_CONTENT_TYPE,
      'cache-control': LLMS_CACHE_CONTROL,
    },
  });
}
