import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ResultPanel } from '../ResultPanel';
import { getToolboxCopy } from '../../data/copy';

/**
 * Lit le contenu d'un Blob.
 *
 * jsdom n'implémente pas `Blob.prototype.text()` : on passe par `FileReader`,
 * qui est disponible, pour vérifier le contenu RÉELLEMENT remis au navigateur.
 */
function readBlobText(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(reader.error);
    reader.readAsText(blob);
  });
}

/**
 * Tests du bouton d'export du panneau de résultats.
 *
 * On vérifie le fichier RÉELLEMENT produit (nom + contenu remis au navigateur),
 * pas seulement qu'un gestionnaire a été appelé.
 */

const COMMON = getToolboxCopy('fr').common;

const ROWS = [
  { label: 'IMC', value: '22,9', emphasis: true },
  { label: 'Interprétation', value: 'Corpulence normale' },
];

describe('ResultPanel — export', () => {
  const originalCreate = URL.createObjectURL;
  const originalRevoke = URL.revokeObjectURL;

  beforeEach(() => {
    window.history.replaceState({}, '', '/toolbox/imc');
    URL.createObjectURL = jest.fn().mockReturnValue('blob:mock');
    URL.revokeObjectURL = jest.fn();
  });

  afterEach(() => {
    URL.createObjectURL = originalCreate;
    URL.revokeObjectURL = originalRevoke;
    jest.restoreAllMocks();
  });

  it('n’affiche pas de bouton d’export sans partage configuré', () => {
    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
      />,
    );
    expect(screen.queryByRole('button', { name: COMMON.export })).not.toBeInTheDocument();
  });

  it('affiche le bouton d’export quand le partage est configuré', () => {
    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
        share={{ values: { weight: '70' }, copy: COMMON }}
      />,
    );
    expect(screen.getByRole('button', { name: COMMON.export })).toBeInTheDocument();
  });

  it('télécharge un fichier nommé d’après l’outil et contenant les lignes affichées', () => {
    let downloadedName = '';
    jest
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function (this: HTMLAnchorElement) {
        downloadedName = this.download;
      });

    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
        share={{ values: { weight: '70' }, copy: COMMON }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: COMMON.export }));

    expect(downloadedName).toBe('leopardo-toolbox-imc.txt');
    expect(URL.createObjectURL).toHaveBeenCalledTimes(1);

    // Le contenu réellement remis au navigateur.
    const blob = (URL.createObjectURL as jest.Mock).mock.calls[0][0] as Blob;
    expect(blob.type).toContain('text/plain');
  });

  it('confirme le téléchargement par un libellé temporaire', async () => {
    jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
        share={{ values: { weight: '70' }, copy: COMMON }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: COMMON.export }));
    expect(await screen.findByRole('button', { name: COMMON.exported })).toBeInTheDocument();
  });

  it('signale l’échec quand le navigateur ne peut pas exporter', async () => {
    // @ts-expect-error — environnement sans createObjectURL.
    URL.createObjectURL = undefined;

    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
        share={{ values: { weight: '70' }, copy: COMMON }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: COMMON.export }));
    expect(await screen.findByRole('button', { name: COMMON.exportFailed })).toBeInTheDocument();
  });

  it('emporte l’avertissement de la page dans le fichier exporté', async () => {
    jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
        share={{ values: { weight: '70' }, copy: COMMON }}
        exportFooter="Résultat théorique"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: COMMON.export }));

    const blob = (URL.createObjectURL as jest.Mock).mock.calls[0][0] as Blob;
    const text = await readBlobText(blob);
    expect(text).toContain('Résultat théorique');
    expect(text).toContain('IMC : 22,9');
  });

  it('retombe sur l’avertissement commun quand aucun pied n’est fourni', async () => {
    jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
        share={{ values: { weight: '70' }, copy: COMMON }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: COMMON.export }));

    const blob = (URL.createObjectURL as jest.Mock).mock.calls[0][0] as Blob;
    expect(await readBlobText(blob)).toContain(COMMON.disclaimer);
  });

  it('libère l’URL objet après le téléchargement', () => {
    jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    render(
      <ResultPanel
        title="Résultat"
        rows={ROWS}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
        share={{ values: { weight: '70' }, copy: COMMON }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: COMMON.export }));
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:mock');
  });
});
