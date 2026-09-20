import { redirect } from 'next/navigation';

import TeamSettingsPage from '../page';

jest.mock('next/navigation', () => ({
  redirect: jest.fn(),
}));

const mockedRedirect = redirect as unknown as jest.Mock;

/**
 * #7862 — « Collaborateurs et rôles » est fusionné dans /employees : la route
 * /settings/team est conservée pour compatibilité (liens du menu, favoris)
 * mais ne rend plus rien : elle redirige côté serveur. Les capacités de
 * l'ancienne page (invitations, rôles, modules délégués, archivage) sont
 * couvertes par `employees/__tests__/employees-page.test.tsx`.
 */
describe('TeamSettingsPage (#7862) — redirection', () => {
  beforeEach(() => {
    mockedRedirect.mockClear();
  });

  it('redirige vers /employees, la page unique de gestion d’équipe', () => {
    TeamSettingsPage();

    expect(mockedRedirect).toHaveBeenCalledTimes(1);
    expect(mockedRedirect).toHaveBeenCalledWith('/employees');
  });
});
