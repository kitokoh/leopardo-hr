import type { Metadata } from 'next';

/**
 * #SEO-OPS — balises de vérification des consoles pour les moteurs.
 *
 * Le site vit sur `leopardo-prod.vercel.app` : un domaine `*.vercel.app` est un
 * sous-domaine d'un suffixe public, donc Google Search Console n'accepte PAS de
 * propriété « Domaine » — il faut une propriété **URL prefix** et une
 * vérification par balise HTML (ou fichier). Idem Bing Webmaster Tools.
 *
 * Les jetons sont fournis par variable d'environnement plutôt que codés en dur :
 * un jeton de vérification change (rotation, nouvelle propriété, nouvel
 * environnement) et ne doit pas exiger une modification de code.
 *
 * Sans jeton, AUCUNE balise n'est émise — pas de `<meta>` vide, qui ferait
 * échouer la vérification en silence.
 */
export function verificationMetadata(): Metadata['verification'] {
  const google = process.env.NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION?.trim();
  const bing = process.env.NEXT_PUBLIC_BING_SITE_VERIFICATION?.trim();
  const yandex = process.env.NEXT_PUBLIC_YANDEX_SITE_VERIFICATION?.trim();

  const other: Record<string, string> = {};
  if (bing) other['msvalidate.01'] = bing;

  if (!google && !yandex && Object.keys(other).length === 0) {
    return undefined;
  }

  return {
    ...(google ? { google } : {}),
    ...(yandex ? { yandex } : {}),
    ...(Object.keys(other).length > 0 ? { other } : {}),
  };
}
