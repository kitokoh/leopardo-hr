import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ResultPanel } from '../ResultPanel';
import { getToolboxCopy } from '../../data/copy';

/**
 * Tests du bouton d'export CSV du panneau de résultats.
 *
 * Deux invariants sont verrouillés ici :
 * 1. le bouton CSV n'apparaît QUE sur un résultat tabulaire (`csv` fourni) ;
 * 2. le fichier remis au navigateur contient bien l'en-tête, les lignes et le
 *    BOM UTF-8 — on lit les octets réels du blob, on ne suppose rien.
 */

const COMMON = getToolboxCopy('fr').common;

const ROWS = [
  { label: 'CBM total', value: '1,5 m³', emphasis: true },
  { label: 'Poids taxable', value: '167 kg' },
];

const CSV = {
  headers: ['Mesure', 'Valeur'],
  rows: [
    ['CBM total', '1.5'],
    ['Poids taxable', '167'],
  ],
};

describe('ResultPanel — export CSV', () => {
  const originalCreate = URL.createObjectURL;
  const originalRevoke = URL.revokeObjectURL;

  beforeEach(() => {
    window.history.replaceState({}, '', '/toolbox/cbm-poids-volumetrique');
    URL.createObjectURL = jest.fn().mockReturnValue('blob:mock');
    URL.revokeObjectURL = jest.fn();
  });

  afterEach(() => {
    URL.createObjectURL = originalCreate;
    URL.revokeObjectURL = originalRevoke;
    jest.restoreAllMocks();
  });

  it('n’affiche AUCUN bouton CSV quand le résultat n’est pas tabulaire', () => {
    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel={COMMON.copy}
        copiedLabel={COMMON.copied}
        copyText="CBM total: 1,5 m³"
        share={{ values: { a: '1' }, copy: COMMON }}
      />,
    );
    // L'export texte est là, mais pas le CSV : rien à mettre dans un tableur.
    expect(screen.getByRole('button', { name: COMMON.export })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: COMMON.exportCsv })).not.toBeInTheDocument();
  });

  it('n’affiche pas non plus de bouton CSV sans partage configuré', () => {
    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel={COMMON.copy}
        copiedLabel={COMMON.copied}
        copyText="CBM total: 1,5 m³"
        csv={CSV}
      />,
    );
    expect(screen.queryByRole('button', { name: COMMON.exportCsv })).not.toBeInTheDocument();
  });

  it('affiche le bouton CSV quand l’outil déclare un résultat tabulaire', () => {
    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel={COMMON.copy}
        copiedLabel={COMMON.copied}
        copyText="CBM total: 1,5 m³"
        share={{ values: { a: '1' }, copy: COMMON }}
        csv={CSV}
      />,
    );
    expect(screen.getByRole('button', { name: COMMON.exportCsv })).toBeInTheDocument();
  });

  it('télécharge un fichier .csv contenant en-tête, lignes et BOM', async () => {
    let captured: Blob | null = null;
    let capturedName = '';
    URL.createObjectURL = jest.fn((blob: Blob) => {
      captured = blob;
      return 'blob:mock';
    });

    const originalClick = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function click() {
      capturedName = this.download;
    };

    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel={COMMON.copy}
        copiedLabel={COMMON.copied}
        copyText="CBM total: 1,5 m³"
        share={{ values: { a: '1' }, copy: COMMON }}
        csv={CSV}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: COMMON.exportCsv }));
    HTMLAnchorElement.prototype.click = originalClick;

    // Le nom dérive de la page courante : `/toolbox/cbm-poids-volumetrique`.
    expect(capturedName).toBe('leopardo-toolbox-cbm-poids-volumetrique.csv');
    expect(captured).not.toBeNull();
    expect(captured!.type).toBe('text/csv;charset=utf-8');

    // On lit les OCTETS : `readAsText` consomme le BOM au décodage, donc le
    // vérifier sur le texte ne prouverait rien. EF BB BF doit être présent.
    const buffer = await new Promise<ArrayBuffer>((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result as ArrayBuffer);
      reader.onerror = () => reject(reader.error);
      reader.readAsArrayBuffer(captured!);
    });

    const bytes = new Uint8Array(buffer);
    expect(Array.from(bytes.slice(0, 3))).toEqual([0xef, 0xbb, 0xbf]);
    // `TextDecoder` n'est pas exposé globalement par cet environnement jsdom :
    // les libellés testés sont de l'ASCII pur, on décode donc directement.
    expect(String.fromCharCode(...bytes.slice(3))).toBe(
      'Mesure;Valeur\r\nCBM total;1.5\r\nPoids taxable;167',
    );

    // Retour visuel réel, puis retour à l'état initial.
    expect(await screen.findByText(COMMON.exportedCsv)).toBeInTheDocument();
    await waitFor(
      () => expect(screen.getByRole('button', { name: COMMON.exportCsv })).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });
});
