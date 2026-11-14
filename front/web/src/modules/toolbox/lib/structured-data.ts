import { SITE_URL } from '@/lib/site-url';
import type { ToolDefinition } from '../types';

/**
 * Données structurées (schema.org) des pages d'outil.
 *
 * La vitrine émet déjà du JSON-LD (`app/business/layout.tsx`) ; la Toolbox
 * n'en émettait aucun, donc ses 14 pages n'étaient pas éligibles aux résultats
 * enrichis. Ce module produit un `WebApplication` — le type schema.org adapté
 * à un outil logiciel utilisable dans le navigateur.
 *
 * ⚠️ Le schéma décrit ce que la page EST réellement : un outil gratuit, sans
 * compte, exécuté côté client. Il n'annonce ni note agrégée, ni avis, ni prix —
 * aucune de ces données n'existe et en inventer serait un balisage mensonger
 * (sanctionné par les moteurs).
 *
 * Fonctions PURES : testées directement, aucun accès réseau.
 */

export interface WebApplicationSchema {
  '@context': 'https://schema.org';
  '@type': 'WebApplication';
  name: string;
  description: string;
  url: string;
  applicationCategory: 'UtilitiesApplication';
  operatingSystem: 'Any';
  browserRequirements: string;
  inLanguage: string;
  isAccessibleForFree: true;
  offers: {
    '@type': 'Offer';
    price: '0';
    priceCurrency: 'EUR';
  };
  isPartOf: {
    '@type': 'WebSite';
    name: string;
    url: string;
  };
}

/** Locale Toolbox → code BCP-47 attendu par schema.org. */
const SCHEMA_LOCALES: Record<string, string> = {
  fr: 'fr-FR',
  en: 'en-GB',
  tr: 'tr-TR',
  ar: 'ar-MA',
};

/**
 * Construit le schéma `WebApplication` d'un outil.
 *
 * @param tool   Définition du catalogue (métadonnées uniquement).
 * @param name   Nom localisé de l'outil.
 * @param description Description localisée.
 * @param locale Locale de la page (fr/en/tr/ar).
 */
export function buildToolSchema(
  tool: ToolDefinition,
  name: string,
  description: string,
  locale: string,
): WebApplicationSchema {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebApplication',
    name,
    description,
    url: `${SITE_URL}/toolbox/${tool.slug}`,
    applicationCategory: 'UtilitiesApplication',
    operatingSystem: 'Any',
    browserRequirements: 'Requires JavaScript',
    inLanguage: SCHEMA_LOCALES[locale] ?? SCHEMA_LOCALES.fr,
    isAccessibleForFree: true,
    offers: {
      '@type': 'Offer',
      price: '0',
      priceCurrency: 'EUR',
    },
    isPartOf: {
      '@type': 'WebSite',
      name: 'Leopardo Toolbox',
      url: `${SITE_URL}/toolbox`,
    },
  };
}

/**
 * Sérialise un schéma pour insertion dans `<script type="application/ld+json">`.
 *
 * `JSON.stringify` n'échappe pas `<` : une description contenant `</script>`
 * fermerait la balise et injecterait du HTML. On échappe donc `<`, `>` et `&`
 * en séquences Unicode — le JSON reste valide et identique après parsing.
 */
export function serializeSchema(schema: unknown): string {
  return JSON.stringify(schema)
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026');
}
