import type { MetadataRoute } from 'next';

import { PROTECTED_PREFIXES } from '@/lib/protected-prefixes';
import { SITE_URL as siteUrl } from '@/lib/site-url';

// Miroir du matcher du proxy (src/proxy.ts) — routes session-protégées
// (#3375). Source unique : src/lib/protected-prefixes.ts (#3377).
const DISALLOWED = [
  ...PROTECTED_PREFIXES,
  '/admin',
  '/api',
  '/auth',
  // Portail client documents partagés (issue #5233) : URL tokenisées — jamais
  // indexables (RGPD, accès limité au destinataire du lien).
  '/documents/shared',
  '/.env',
  '/.git',
  '/node_modules',
];

/**
 * #AI-SEO — crawlers des moteurs de réponse et assistants génératifs.
 *
 * Ils sont autorisés EXPLICITEMENT sur les pages publiques (mêmes exclusions
 * que le groupe `*`) pour deux raisons :
 *  1. visibilité IA (GEO/AEO) : ChatGPT Search, Perplexity, Claude, Gemini
 *     (Google-Extended), Copilot et consorts ne se contentent pas toujours de
 *     l'index Google — il faut qu'ils puissent lire la vitrine ;
 *  2. intention lisible : sans groupe dédié, ces agents tombent sous `*` par
 *     défaut ; une politique explicite évite les interprétations divergentes
 *     côté éditeurs (certains robots exécutent des politiques par défaut
 *     propres, ex. opt-out IA).
 *
 * `Google-Extended` ne contrôle PAS l'indexation Googlebot (déjà autorisée
 * ci-dessus) : il régit l'usage du contenu par Gemini et Vertex AI grounding.
 * `Swiftbot`/`Bytespider` sont volontairement absents (aspirateurs de volume,
 * aucun bénéfice de citation).
 */
const AI_CRAWLERS = [
  // OpenAI (ChatGPT Search, ChatGPT browse, entretien du RAG)
  'GPTBot',
  'OAI-SearchBot',
  'ChatGPT-User',
  // Anthropic (Claude, recherche et exploration utilisateur)
  'ClaudeBot',
  'Claude-User',
  'Claude-SearchBot',
  'anthropic-ai',
  // Perplexity
  'PerplexityBot',
  'Perplexity-User',
  // Google (Gemini / Vertex AI grounding) et Apple
  'Google-Extended',
  'Applebot',
  'Applebot-Extended',
  // Autres moteurs de réponse / assistants
  'Amazonbot',
  'CCBot',
  'cohere-ai',
  'Meta-ExternalAgent',
  'DuckAssistBot',
];

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: DISALLOWED,
      },
      {
        // #AI-SEO : groupe dédié aux crawlers IA — un groupe écrase `*` pour
        // ces agents, d'où la répétition explicite de DISALLOWED.
        userAgent: AI_CRAWLERS,
        allow: '/',
        disallow: DISALLOWED,
      },
      {
        // #3377 : un groupe dédié ÉCRASE le groupe `*` pour ce bot — sans
        // disallow explicite ici, Googlebot crawlait les 14 préfixes protégés.
        userAgent: 'Googlebot',
        allow: '/',
        disallow: DISALLOWED,
      },
      {
        userAgent: 'Bingbot',
        allow: '/',
        disallow: DISALLOWED,
      },
      {
        userAgent: 'MJ12bot',
        disallow: '/',
      },
      {
        userAgent: 'AhrefsBot',
        disallow: '/',
      },
      {
        userAgent: 'SemrushBot',
        disallow: '/',
      },
    ],
    sitemap: `${siteUrl}/sitemap.xml`,
  };
}
