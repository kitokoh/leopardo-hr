import { buildToolSchema, serializeSchema } from './structured-data';
import { TOOLS } from '../data/catalog';

/**
 * Tests des données structurées (schema.org `WebApplication`).
 *
 * Le schéma est du balisage lu par les moteurs : une valeur fausse (note
 * inventée, prix non nul, URL erronée) est une déclaration mensongère. Ces
 * tests verrouillent donc les propriétés factuelles, pas seulement la forme.
 */
describe('buildToolSchema', () => {
  const imc = TOOLS.find((tool) => tool.slug === 'imc')!;

  it('produit un WebApplication conforme à ce que la page est réellement', () => {
    const schema = buildToolSchema(imc, 'IMC', 'Indice de masse corporelle', 'fr');

    expect(schema['@context']).toBe('https://schema.org');
    expect(schema['@type']).toBe('WebApplication');
    expect(schema.name).toBe('IMC');
    expect(schema.description).toBe('Indice de masse corporelle');
    expect(schema.applicationCategory).toBe('UtilitiesApplication');
    expect(schema.operatingSystem).toBe('Any');
    expect(schema.isAccessibleForFree).toBe(true);
  });

  it('déclare un outil gratuit (aucun prix inventé)', () => {
    const schema = buildToolSchema(imc, 'IMC', 'x', 'fr');
    expect(schema.offers.price).toBe('0');
    expect(schema.offers.priceCurrency).toBe('EUR');
  });

  it('pointe vers l’URL canonique de l’outil et du catalogue', () => {
    const schema = buildToolSchema(imc, 'IMC', 'x', 'fr');
    expect(schema.url).toMatch(/\/toolbox\/imc$/);
    expect(schema.isPartOf.url).toMatch(/\/toolbox$/);
  });

  it('localise `inLanguage` pour les 4 locales', () => {
    expect(buildToolSchema(imc, 'a', 'b', 'fr').inLanguage).toBe('fr-FR');
    expect(buildToolSchema(imc, 'a', 'b', 'en').inLanguage).toBe('en-GB');
    expect(buildToolSchema(imc, 'a', 'b', 'tr').inLanguage).toBe('tr-TR');
    expect(buildToolSchema(imc, 'a', 'b', 'ar').inLanguage).toBe('ar-MA');
  });

  it('retombe sur fr-FR pour une locale inconnue', () => {
    expect(buildToolSchema(imc, 'a', 'b', 'de').inLanguage).toBe('fr-FR');
  });

  it('n’invente ni note agrégée ni avis', () => {
    const schema = buildToolSchema(imc, 'a', 'b', 'fr') as unknown as Record<string, unknown>;
    expect(schema.aggregateRating).toBeUndefined();
    expect(schema.review).toBeUndefined();
  });
});

describe('serializeSchema', () => {
  it('produit du JSON valide et réversible', () => {
    const schema = buildToolSchema(TOOLS[0], 'Nom', 'Description', 'fr');
    const parsed = JSON.parse(serializeSchema(schema));
    expect(parsed['@type']).toBe('WebApplication');
    expect(parsed.name).toBe('Nom');
  });

  it('échappe `<` pour qu’une description ne puisse pas fermer la balise script', () => {
    const payload = { description: '</script><img src=x onerror=alert(1)>' };
    const serialized = serializeSchema(payload);

    expect(serialized).not.toContain('</script>');
    expect(serialized).not.toContain('<');
    // Le JSON reste valide et la valeur est restituée à l'identique.
    expect(JSON.parse(serialized).description).toBe(payload.description);
  });

  it('échappe `&` et `>`', () => {
    const serialized = serializeSchema({ value: 'a & b > c' });
    expect(serialized).not.toContain('&');
    expect(serialized).not.toContain('>');
    expect(JSON.parse(serialized).value).toBe('a & b > c');
  });
});
