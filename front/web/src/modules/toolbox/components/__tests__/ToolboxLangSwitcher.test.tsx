import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { ToolboxLangSwitcher } from '../ToolboxLangSwitcher';

/**
 * Tests du sélecteur de langue de la Toolbox.
 *
 * Le composant utilise `usePathname` / `useSearchParams` : on les mocke pour
 * piloter l'URL courante sans monter un routeur Next complet.
 */
const mockPathname = jest.fn(() => '/toolbox');
const mockSearchParams = jest.fn(() => new URLSearchParams(''));

jest.mock('next/navigation', () => ({
  usePathname: () => mockPathname(),
  useSearchParams: () => mockSearchParams(),
}));

const NAMES = { fr: 'Français', en: 'English', tr: 'Türkçe', ar: 'العربية' };

describe('ToolboxLangSwitcher', () => {
  beforeEach(() => {
    mockPathname.mockReturnValue('/toolbox');
    mockSearchParams.mockReturnValue(new URLSearchParams(''));
  });

  it('rend un select accessible avec les 4 langues', () => {
    render(<ToolboxLangSwitcher locale="fr" label="Langue" names={NAMES} />);

    const select = screen.getByLabelText('Langue');
    expect(select.tagName).toBe('SELECT');
    expect(select).toHaveValue('fr');

    const options = screen.getAllByRole('option');
    expect(options).toHaveLength(4);
    expect(options.map((option) => option.textContent)).toEqual([
      'Français',
      'English',
      'Türkçe',
      'العربية',
    ]);
  });

  it('affiche la locale réellement servie par le SSR', () => {
    render(<ToolboxLangSwitcher locale="ar" label="اللغة" names={NAMES} />);
    expect(screen.getByLabelText('اللغة')).toHaveValue('ar');
  });

  it('navigue vers l’URL portant la nouvelle langue au changement', () => {
    const navigate = jest.fn();
    render(
      <ToolboxLangSwitcher locale="fr" label="Langue" names={NAMES} navigate={navigate} />,
    );
    fireEvent.change(screen.getByLabelText('Langue'), { target: { value: 'en' } });
    expect(navigate).toHaveBeenCalledWith('/toolbox?lang=en');
  });

  it('conserve les filtres courants dans l’URL de bascule', () => {
    mockSearchParams.mockReturnValue(new URLSearchParams('category=finance'));
    const navigate = jest.fn();
    render(
      <ToolboxLangSwitcher locale="fr" label="Langue" names={NAMES} navigate={navigate} />,
    );
    fireEvent.change(screen.getByLabelText('Langue'), { target: { value: 'ar' } });
    expect(navigate).toHaveBeenCalledWith('/toolbox?category=finance&lang=ar');
  });

  it('revient à l’URL canonique en choisissant le français', () => {
    mockSearchParams.mockReturnValue(new URLSearchParams('lang=tr'));
    const navigate = jest.fn();
    render(
      <ToolboxLangSwitcher locale="tr" label="Dil" names={NAMES} navigate={navigate} />,
    );
    fireEvent.change(screen.getByLabelText('Dil'), { target: { value: 'fr' } });
    expect(navigate).toHaveBeenCalledWith('/toolbox');
  });
});
