import { defaultShowcaseSections, publicShowcaseUrl } from '../showcase';

/**
 * BC-27 SHOWCASE (#6862) — amorçage « 1 clic » de la page vitrine.
 *
 * La création doit produire une page présentable sans saisie : les sections
 * par défaut doivent respecter les JSON Schemas v1 côté API
 * (`ShowcaseSectionSchemaRegistry`) — `hero.heading` et `contact.email` sont
 * REQUIS, sinon la création de section répond 422 et la vitrine reste vide.
 */
describe('showcase default sections (#6862)', () => {
  it('produces schema-conformant hero/contact/footer defaults', () => {
    const sections = defaultShowcaseSections('Acme Industries', 'contact@acme.test');

    const hero = sections.find((s) => s.type === 'hero');
    const contact = sections.find((s) => s.type === 'contact');
    const footer = sections.find((s) => s.type === 'footer');

    expect(typeof hero?.content.heading).toBe('string');
    expect((hero?.content.heading as string).length).toBeGreaterThan(0);
    expect(typeof contact?.content.email).toBe('string');
    expect((contact?.content.email as string).length).toBeGreaterThan(0);
    expect(footer).toBeDefined();
  });

  it('falls back to a non-empty heading when the company name is blank', () => {
    const sections = defaultShowcaseSections('   ', '');
    const hero = sections.find((s) => s.type === 'hero');
    expect((hero?.content.heading as string).trim().length).toBeGreaterThan(0);
  });

  it('builds the public URL and the draft preview URL', () => {
    expect(publicShowcaseUrl({ slug: 'acme-industries' })).toBe('/vitrine/acme-industries');
    expect(publicShowcaseUrl({ slug: 'acme-industries' }, { preview: true })).toBe('/vitrine/acme-industries?preview=1');
  });
});
