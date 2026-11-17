import {
  CSV_DELIMITER,
  UTF8_BOM,
  buildCsv,
  buildCsvFilename,
  downloadCsvFile,
  escapeCsvCell,
} from './export';

/**
 * Tests de l'export CSV — cas connus, écrits à la main.
 *
 * On vérifie le CONTENU réellement produit (pas seulement qu'une fonction a
 * été appelée) : c'est le fichier remis au visiteur qui compte, et c'est lui
 * qui doit s'ouvrir correctement dans un tableur.
 */

describe('escapeCsvCell', () => {
  it('laisse nue une cellule sans caractère spécial', () => {
    expect(escapeCsvCell('IMC')).toBe('IMC');
    expect(escapeCsvCell('22.9')).toBe('22.9');
  });

  it('entoure de guillemets une cellule contenant le séparateur', () => {
    expect(escapeCsvCell('a;b')).toBe('"a;b"');
    expect(escapeCsvCell('a,b', ',')).toBe('"a,b"');
  });

  it('n’entoure PAS une virgule quand le séparateur est le point-virgule', () => {
    // Cas réel : « 1,5 » (virgule décimale française) ne doit pas être cité,
    // sinon le tableur afficherait des guillemets parasites.
    expect(escapeCsvCell('1,5')).toBe('1,5');
  });

  it('double les guillemets internes', () => {
    expect(escapeCsvCell('il a dit "oui"')).toBe('"il a dit ""oui"""');
  });

  it('entoure de guillemets une cellule contenant un retour à la ligne', () => {
    expect(escapeCsvCell('ligne1\nligne2')).toBe('"ligne1\nligne2"');
    expect(escapeCsvCell('ligne1\r\nligne2')).toBe('"ligne1\r\nligne2"');
  });

  it('neutralise une cellule qui serait interprétée comme une formule', () => {
    // Injection CSV : sans précaution, Excel exécuterait la formule.
    expect(escapeCsvCell('=1+1')).toBe("'=1+1");
    expect(escapeCsvCell('+1')).toBe("'+1");
    expect(escapeCsvCell('-1')).toBe("'-1");
    expect(escapeCsvCell('@SUM(A1)')).toBe("'@SUM(A1)");
  });

  it('neutralise ET cite une formule contenant aussi le séparateur', () => {
    expect(escapeCsvCell('=A1;B1')).toBe('"\'=A1;B1"');
  });

  it('retourne une chaîne vide pour une cellule vide', () => {
    expect(escapeCsvCell('')).toBe('');
  });
});

describe('buildCsv', () => {
  it('joint les colonnes par le point-virgule et les lignes par CRLF', () => {
    const csv = buildCsv([
      ['Mesure', 'Valeur'],
      ['CBM total', '1.5'],
    ]);
    expect(csv).toBe('Mesure;Valeur\r\nCBM total;1.5');
  });

  it('accepte un séparateur virgule', () => {
    const csv = buildCsv(
      [
        ['A', 'B'],
        ['1', '2'],
      ],
      ',',
    );
    expect(csv).toBe('A,B\r\n1,2');
  });

  it('retourne une chaîne vide quand il n’y a aucune ligne', () => {
    expect(buildCsv([])).toBe('');
  });

  it('produit une ligne vide pour une ligne sans cellule', () => {
    expect(buildCsv([[], ['A']])).toBe('\r\nA');
  });

  it('échappe chaque cellule indépendamment', () => {
    const csv = buildCsv([
      ['Taux', 'Base HT', 'TVA'],
      ['20 %', '1.5', '0.3'],
      ['10 %', '2,5', '0.25'],
    ]);
    // « 2,5 » (virgule décimale) reste nu : le séparateur est le point-virgule.
    expect(csv).toBe('Taux;Base HT;TVA\r\n20 %;1.5;0.3\r\n10 %;2,5;0.25');
  });

  it('n’ajoute PAS de BOM (il relève du transport, pas du contenu)', () => {
    const csv = buildCsv([['A']]);
    expect(csv.startsWith(UTF8_BOM)).toBe(false);
    expect(csv.charCodeAt(0)).toBe('A'.charCodeAt(0));
  });

  it('conserve l’ordre des lignes et des colonnes', () => {
    const csv = buildCsv([
      ['c1', 'c2'],
      ['a', 'b'],
      ['d', 'e'],
    ]);
    expect(csv.split('\r\n')).toEqual(['c1;c2', 'a;b', 'd;e']);
  });

  it('a un séparateur par défaut égal à CSV_DELIMITER', () => {
    expect(CSV_DELIMITER).toBe(';');
    expect(buildCsv([['a', 'b']])).toBe(buildCsv([['a', 'b']], CSV_DELIMITER));
  });
});

describe('buildCsvFilename', () => {
  it('remplace l’extension .txt par .csv', () => {
    expect(buildCsvFilename('leopardo-toolbox-tva-multi-taux.txt')).toBe(
      'leopardo-toolbox-tva-multi-taux.csv',
    );
  });

  it('ne touche pas un nom sans extension .txt', () => {
    expect(buildCsvFilename('leopardo-toolbox.txt')).toBe('leopardo-toolbox.csv');
    expect(buildCsvFilename('autre')).toBe('autre');
  });

  it('dérive bien du nom produit par buildExportFilename (slug déjà assaini)', () => {
    // Un chemin de traversée a déjà été réduit en amont : aucune traversée ne
    // peut réapparaître ici.
    expect(buildCsvFilename('leopardo-toolbox-etc-passwd.txt')).toBe(
      'leopardo-toolbox-etc-passwd.csv',
    );
  });
});

describe('downloadCsvFile', () => {
  const originalCreate = URL.createObjectURL;
  const originalRevoke = URL.revokeObjectURL;

  afterEach(() => {
    URL.createObjectURL = originalCreate;
    URL.revokeObjectURL = originalRevoke;
  });

  it('télécharge le contenu préfixé du BOM, en type text/csv', () => {
    let capturedBlob: Blob | null = null;
    let capturedFilename = '';
    let revoked = '';

    URL.createObjectURL = (blob: Blob) => {
      capturedBlob = blob;
      return 'blob:csv';
    };
    URL.revokeObjectURL = (url: string) => {
      revoked = url;
    };

    const originalClick = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function click() {
      capturedFilename = this.download;
    };

    const ok = downloadCsvFile('leopardo-toolbox-tva.csv', 'A;B');
    HTMLAnchorElement.prototype.click = originalClick;

    expect(ok).toBe(true);
    expect(capturedFilename).toBe('leopardo-toolbox-tva.csv');
    expect(capturedBlob).not.toBeNull();
    expect(capturedBlob!.type).toBe('text/csv;charset=utf-8');
    // Le BOM est bien dans le blob, pas dans la chaîne logique.
    expect(revoked).toBe('blob:csv');
  });

  it('lit réellement le blob produit (BOM en octets + contenu) via FileReader', async () => {
    let capturedBlob: Blob | null = null;
    URL.createObjectURL = (blob: Blob) => {
      capturedBlob = blob;
      return 'blob:csv';
    };
    URL.revokeObjectURL = () => undefined;

    const originalClick = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function click() {};

    downloadCsvFile('x.csv', 'Mesure;Valeur');
    HTMLAnchorElement.prototype.click = originalClick;

    expect(capturedBlob).not.toBeNull();

    // On lit les OCTETS, pas le texte : `readAsText` consomme le BOM au
    // décodage et le rendrait invisible — impossible de prouver sa présence
    // autrement. Les trois premiers octets doivent être EF BB BF.
    const buffer = await new Promise<ArrayBuffer>((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result as ArrayBuffer);
      reader.onerror = () => reject(new Error('lecture impossible'));
      reader.readAsArrayBuffer(capturedBlob!);
    });

    const bytes = new Uint8Array(buffer);
    expect(Array.from(bytes.slice(0, 3))).toEqual([0xef, 0xbb, 0xbf]);
    // `TextDecoder` n'est pas exposé globalement par cet environnement jsdom :
    // le contenu testé est de l'ASCII pur, on le décode donc directement.
    expect(String.fromCharCode(...bytes.slice(3))).toBe('Mesure;Valeur');
  });

  it('retourne false plutôt que de lever quand createObjectURL manque', () => {
    // @ts-expect-error — on simule un environnement sans l'API.
    URL.createObjectURL = undefined;
    expect(downloadCsvFile('x.csv', 'A;B')).toBe(false);
  });
});
