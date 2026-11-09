import { normalizeFileFormat } from '../lib/reader-contract';

/**
 * Tests du contrat commun des lecteurs.
 *
 * Le point critique : le lecteur est choisi par le FORMAT RÉEL renvoyé par
 * l'API (`file_format`, dérivé du MIME vérifié côté serveur), jamais par
 * l'extension d'une URL fournie par le client.
 */
describe('reader-contract — normalizeFileFormat', () => {
  it('reconnaît les formats supportés', () => {
    expect(normalizeFileFormat('pdf')).toBe('pdf');
    expect(normalizeFileFormat('epub')).toBe('epub');
    expect(normalizeFileFormat('txt')).toBe('txt');
    expect(normalizeFileFormat('html')).toBe('html');
  });

  it('normalise la casse et les espaces', () => {
    expect(normalizeFileFormat('  PDF ')).toBe('pdf');
    expect(normalizeFileFormat('Epub')).toBe('epub');
  });

  it('retombe sur null pour un format inconnu ou absent', () => {
    expect(normalizeFileFormat('docx')).toBeNull();
    expect(normalizeFileFormat('')).toBeNull();
    expect(normalizeFileFormat(null)).toBeNull();
    expect(normalizeFileFormat(undefined)).toBeNull();
  });

  it('ne devine jamais un format depuis une extension d’URL', () => {
    // Une URL `.pdf` n'est pas un format : seule la valeur serveur compte.
    expect(normalizeFileFormat('https://exemple.test/livre.pdf')).toBeNull();
  });
});
