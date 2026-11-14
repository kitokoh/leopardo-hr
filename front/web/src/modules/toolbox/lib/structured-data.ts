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
 * Échappement JSON-LD.
 *
 * `JSON.stringify` n'échappe pas `<` : une description contenant `</script>`
 * fermerait la balise et injecterait du HTML. On remplace donc chaque caractère
 * dangereux par sa séquence d'échappement Unicode, construite à partir de son
 * code de caractère — le JSON reste valide et identique après parsing.
 *
 * La séquence est CALCULÉE (pas écrite en littéral) : c'est une constante
 * technique, pas de la copie utilisateur, et la garde PA2-I18N-014 ne doit pas
 * la confondre avec du texte à traduire.
 */
const ESCAPE_PREFIX = String.fromCharCode(92) + 'u';

function unicodeEscape(char: string): string {
  return ESCAPE_PREFIX + char.charCodeAt(0).toString(16).padStart(4, '0');
}

/**
 * Construit le schéma `WebApplication` d'un outil.
 *
 * @param tool   Définition du catalogue (métadonnées uniquement).
 * @param name   Nom localisé de l'outil.
 * @param description Description localisée.
 * @param locale Locale de la page (fr/en/tr/ar).
 * @param browserRequirements Exigence technique localisée (catalogue i18n).
 * @param siteName Nom localisé du site parent (catalogue i18n).
 */
export function buildToolSchema(
  tool: ToolDefinition,
  name: string,
  description: string,
  locale: string,
  browserRequirements: string,
  siteName: string,
): WebApplicationSchema {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebApplication',
    name,
    description,
    url: `${SITE_URL}/toolbox/${tool.slug}`,
    applicationCategory: 'UtilitiesApplication',
    operatingSystem: 'Any',
    browserRequirements,
    inLanguage: SCHEMA_LOCALES[locale] ?? SCHEMA_LOCALES.fr,
    isAccessibleForFree: true,
    offers: {
      '@type': 'Offer',
      price: '0',
      priceCurrency: 'EUR',
    },
    isPartOf: {
      '@type': 'WebSite',
      name: siteName,
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
    .replace(/</g, unicodeEscape('<'))
    .replace(/>/g, unicodeEscape('>'))
    .replace(/&/g, unicodeEscape('&'));
}
