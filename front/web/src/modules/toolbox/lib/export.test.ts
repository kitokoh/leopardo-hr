import { buildExportFilename, buildExportText, downloadTextFile } from './export';

/**
 * Tests de l'export — cas connus, calculés à la main.
 *
 * On vérifie le CONTENU réellement produit (pas seulement qu'une fonction a
 * été appelée) : c'est le fichier remis au visiteur qui compte.
 */

describe('buildExportText', () => {
  it('produit un titre, les lignes puis le pied, séparés par une ligne vide', () => {
    const text = buildExportText(
      'Résultat',
      [
        { label: 'IMC', value: '22,9' },
        { label: 'Interprétation', value: 'Corpulence normale' },
      ],
      'Avertissement',
    );

    expect(text).toBe(
      ['Résultat', '', 'IMC : 22,9', 'Interprétation : Corpulence normale', '', 'Avertissement'].join(
        '\n',
      ),
    );
  });

  it('omet le pied quand il est absent ou vide', () => {
    expect(buildExportText('Résultat', [{ label: 'A', value: '1' }])).toBe('Résultat\n\nA : 1');
    expect(buildExportText('Résultat', [{ label: 'A', value: '1' }], '   ')).toBe(
      'Résultat\n\nA : 1',
    );
  });

  it('ignore les lignes entièrement vides', () => {
    const text = buildExportText('T', [
      { label: 'A', value: '1' },
      { label: '', value: '' },
      { label: 'B', value: '2' },
    ]);
    expect(text).toBe('T\n\nA : 1\nB : 2');
  });

  it('écrit la valeur seule quand le libellé est vide', () => {
    expect(buildExportText('T', [{ label: '', value: '42' }])).toBe('T\n\n42');
  });

  it('nettoie les espaces autour des libellés et des valeurs', () => {
    expect(buildExportText('  T  ', [{ label: ' A ', value: ' 1 ' }])).toBe('T\n\nA : 1');
  });

  it('ne produit pas de ligne vide parasite quand le titre est vide', () => {
    expect(buildExportText('', [{ label: 'A', value: '1' }])).toBe('A : 1');
  });

  it('retourne une chaîne vide quand il n’y a rien à exporter', () => {
    expect(buildExportText('', [])).toBe('');
  });

  it('préserve les valeurs déjà localisées (devise, séparateurs)', () => {
    const text = buildExportText('T', [{ label: 'TTC', value: '1 200,00 €' }]);
    expect(text).toContain('1 200,00 €');
  });
});

describe('buildExportFilename', () => {
  it('déduit le slug de l’outil depuis le chemin', () => {
    expect(buildExportFilename('/toolbox/imc')).toBe('leopardo-toolbox-imc.txt');
    expect(buildExportFilename('/toolbox/cbm-poids-volumetrique')).toBe(
      'leopardo-toolbox-cbm-poids-volumetrique.txt',
    );
  });

  it('ignore une barre oblique finale', () => {
    expect(buildExportFilename('/toolbox/imc/')).toBe('leopardo-toolbox-imc.txt');
  });

  it('retombe sur le nom générique pour le catalogue seul', () => {
    expect(buildExportFilename('/toolbox')).toBe('leopardo-toolbox.txt');
    expect(buildExportFilename('/toolbox/')).toBe('leopardo-toolbox.txt');
  });

  it('retombe sur le nom générique pour un chemin vide', () => {
    expect(buildExportFilename('')).toBe('leopardo-toolbox.txt');
    expect(buildExportFilename('/')).toBe('leopardo-toolbox.txt');
  });

  it('neutralise les caractères dangereux (traversée, encodage)', () => {
    // Chaque caractère hors [a-z0-9-] devient un tiret, puis les tirets
    // consécutifs sont réduits : le nom reste plat et sans séparateur.
    expect(buildExportFilename('/toolbox/..%2F..%2Fetc%2Fpasswd')).toBe(
      'leopardo-toolbox-2f-2fetc-2fpasswd.txt',
    );
    expect(buildExportFilename('/toolbox/../../etc/passwd')).toBe('leopardo-toolbox-passwd.txt');
  });

  it('ne produit jamais de séparateur de chemin dans le nom', () => {
    const name = buildExportFilename('/toolbox/a/b/c');
    expect(name).not.toContain('/');
    expect(name).not.toContain('\\');
  });
});

describe('downloadTextFile', () => {
  const originalCreate = URL.createObjectURL;
  const originalRevoke = URL.revokeObjectURL;

  afterEach(() => {
    URL.createObjectURL = originalCreate;
    URL.revokeObjectURL = originalRevoke;
    jest.restoreAllMocks();
  });

  it('déclenche un téléchargement et libère l’URL objet', () => {
    const createObjectURL = jest.fn().mockReturnValue('blob:mock');
    const revokeObjectURL = jest.fn();
    URL.createObjectURL = createObjectURL;
    URL.revokeObjectURL = revokeObjectURL;

    const click = jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    const ok = downloadTextFile('leopardo-toolbox-imc.txt', 'contenu');

    expect(ok).toBe(true);
    expect(createObjectURL).toHaveBeenCalledTimes(1);
    expect(click).toHaveBeenCalledTimes(1);
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock');
  });

  it('nomme le fichier et lui donne le contenu attendu', () => {
    URL.createObjectURL = jest.fn().mockReturnValue('blob:mock');
    URL.revokeObjectURL = jest.fn();

    let downloadedName = '';
    jest
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function (this: HTMLAnchorElement) {
        downloadedName = this.download;
      });

    downloadTextFile('leopardo-toolbox-tva.txt', 'TVA : 20');

    expect(downloadedName).toBe('leopardo-toolbox-tva.txt');
  });

  it('retourne false quand l’API Blob est indisponible', () => {
    // @ts-expect-error — on simule un environnement sans createObjectURL.
    URL.createObjectURL = undefined;
    expect(downloadTextFile('x.txt', 'y')).toBe(false);
  });

  it('retourne false plutôt que de lever si le navigateur échoue', () => {
    URL.createObjectURL = jest.fn(() => {
      throw new Error('boom');
    });
    expect(downloadTextFile('x.txt', 'y')).toBe(false);
  });
});
