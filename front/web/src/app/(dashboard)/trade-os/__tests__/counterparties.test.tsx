import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import TradeDashboardPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status = 0;
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const counterpartiesPayload = {
  data: [
    {
      id: 11,
      name: 'Anadolu Tekstil A.Ş.',
      country: 'TR',
      city: 'Bursa',
      email: 'contact@anadolu.example',
      phone: null,
      website: null,
      roles: ['supplier', 'manufacturer'],
      verification_status: 'verified',
      verified_by_employee_id: 3,
      verified_at: '2026-11-01T09:00:00+00:00',
      verification_note: 'Documents contrôlés',
      capabilities: [],
      notes: null,
      crm_account_id: null,
    },
    {
      id: 12,
      name: 'SARL Import Ouest',
      country: 'FR',
      city: 'Nantes',
      email: null,
      phone: null,
      website: null,
      roles: ['buyer'],
      verification_status: 'none',
      verified_by_employee_id: null,
      verified_at: null,
      verification_note: null,
      capabilities: [],
      notes: null,
      crm_account_id: null,
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

/**
 * #9780 — répertoire des contreparties dans `/trade-os`.
 *
 * Les assertions portent sur ce que l'écran AFFIRME : le badge de vérification
 * dit « vérifiée par l'équipe » (jamais « vérifiée » tout court), et le
 * formulaire n'envoie ni `verification_status` ni `company_id`.
 */
describe('Trade OS — contreparties (#9780)', () => {
  const mockAll = (counterparties = counterpartiesPayload) => {
    mockedApiFetch.mockImplementation(async (url: string) => {
      if (url.startsWith('/trade/counterparties')) {
        return { json: async () => counterparties } as Response;
      }
      return { json: async () => ({ data: [] }) } as Response;
    });
  };

  it('liste les contreparties avec leurs rôles et leur statut de vérification', async () => {
    mockAll();

    render(<TradeDashboardPage />);

    await userEvent.click(await screen.findByRole('tab', { name: /contreparties/i }));

    expect(await screen.findByText('Anadolu Tekstil A.Ş.')).toBeInTheDocument();
    expect(screen.getByText('SARL Import Ouest')).toBeInTheDocument();

    // Rôles traduits, pas les valeurs brutes de l'enum.
    expect(screen.getByText(/Fournisseur · Fabricant/)).toBeInTheDocument();
    expect(screen.getByText(/^Client$/)).toBeInTheDocument();

    // Formulation honnête : la vérification est humaine et datée.
    expect(screen.getByText('Vérifiée par l’équipe')).toBeInTheDocument();
    expect(screen.getByText('Non vérifiée')).toBeInTheDocument();
    expect(screen.getByText(/Vérifié par l’équipe le/)).toBeInTheDocument();
  });

  it('filtre la liste par rôle côté client', async () => {
    mockAll();

    render(<TradeDashboardPage />);
    await userEvent.click(await screen.findByRole('tab', { name: /contreparties/i }));
    await screen.findByText('Anadolu Tekstil A.Ş.');

    await userEvent.selectOptions(
      screen.getByRole('combobox'),
      screen.getByRole('option', { name: 'Client' }),
    );

    expect(screen.queryByText('Anadolu Tekstil A.Ş.')).not.toBeInTheDocument();
    expect(screen.getByText('SARL Import Ouest')).toBeInTheDocument();
  });

  it('crée une contrepartie sans jamais envoyer verification_status ni company_id', async () => {
    mockAll({ data: [] });

    render(<TradeDashboardPage />);
    await userEvent.click(await screen.findByRole('tab', { name: /contreparties/i }));

    await userEvent.click(await screen.findByRole('button', { name: /nouvelle contrepartie/i }));

    await userEvent.type(screen.getByLabelText(/nom de la contrepartie/i), 'Nouveau Fournisseur');
    // La case à cocher du fieldset, pas l'option du filtre `<select>`.
    await userEvent.click(screen.getByRole('checkbox', { name: 'Fournisseur' }));
    await userEvent.type(screen.getByLabelText(/^pays/i), 'tr');

    await userEvent.click(screen.getByRole('button', { name: /^enregistrer$/i }));

    await waitFor(() => {
      const post = mockedApiFetch.mock.calls.find(
        ([url, init]) => url === '/trade/counterparties' && init?.method === 'POST',
      );
      expect(post).toBeDefined();

      const body = JSON.parse(String(post?.[1]?.body));
      expect(body.name).toBe('Nouveau Fournisseur');
      expect(body.roles).toEqual(['supplier']);
      // Pays normalisé en majuscules, jamais laissé tel que tapé.
      expect(body.country).toBe('TR');
      // Les deux champs interdits ne partent JAMAIS.
      expect(body).not.toHaveProperty('verification_status');
      expect(body).not.toHaveProperty('company_id');
    });
  });

  it('refuse un formulaire sans rôle métier', async () => {
    mockAll({ data: [] });

    render(<TradeDashboardPage />);
    await userEvent.click(await screen.findByRole('tab', { name: /contreparties/i }));
    await userEvent.click(await screen.findByRole('button', { name: /nouvelle contrepartie/i }));

    await userEvent.type(screen.getByLabelText(/nom de la contrepartie/i), 'Sans rôle');
    await userEvent.click(screen.getByRole('button', { name: /^enregistrer$/i }));

    expect(await screen.findByText(/au moins un rôle métier/i)).toBeInTheDocument();
    expect(
      mockedApiFetch.mock.calls.some(([url]) => url === '/trade/counterparties'),
    ).toBe(false);
  });

  /**
   * #9780 (édition) — le formulaire sert aussi à MODIFIER une fiche existante.
   *
   * Les assertions portent sur ce qui part réellement sur le réseau : la
   * méthode (`PUT`, jamais `POST`), l'URL de la fiche visée, et l'absence des
   * champs que le client ne choisit pas (`verification_status`, `company_id`).
   */
  describe('édition d’une contrepartie existante', () => {
    const EDITED_NAME = 'Anadolu Tekstil (renommée)';

    /** Mock qui répond au `PUT` de la fiche 11 et sert la liste au `GET`. */
    const mockEdit = (putBehaviour: 'ok' | 'fail' = 'ok') => {
      mockedApiFetch.mockImplementation(async (url: string, init?: RequestInit) => {
        if (url === '/trade/counterparties/11' && init?.method === 'PUT') {
          if (putBehaviour === 'fail') {
            throw new Error('réseau indisponible');
          }

          return {
            json: async () => ({
              data: { ...counterpartiesPayload.data[0], name: EDITED_NAME },
            }),
          } as Response;
        }

        if (url.startsWith('/trade/counterparties')) {
          return { json: async () => counterpartiesPayload } as Response;
        }

        return { json: async () => ({ data: [] }) } as Response;
      });
    };

    const openEditForm = async () => {
      render(<TradeDashboardPage />);
      await userEvent.click(await screen.findByRole('tab', { name: /contreparties/i }));
      await screen.findByText('Anadolu Tekstil A.Ş.');
      await userEvent.click(
        screen.getByRole('button', { name: 'Modifier Anadolu Tekstil A.Ş.' }),
      );
    };

    it('pré-remplit les champs depuis la contrepartie sélectionnée', async () => {
      mockEdit();
      await openEditForm();

      expect(screen.getByLabelText(/nom de la contrepartie/i)).toHaveValue(
        'Anadolu Tekstil A.Ş.',
      );
      expect(screen.getByLabelText(/^pays/i)).toHaveValue('TR');
      expect(screen.getByLabelText(/^ville/i)).toHaveValue('Bursa');
      expect(screen.getByLabelText(/^email/i)).toHaveValue('contact@anadolu.example');

      // Les rôles de la fiche sont cochés — pas un formulaire vide.
      expect(screen.getByRole('checkbox', { name: 'Fournisseur' })).toBeChecked();
      expect(screen.getByRole('checkbox', { name: 'Fabricant' })).toBeChecked();
      expect(screen.getByRole('checkbox', { name: 'Client' })).not.toBeChecked();
    });

    it('envoie un PUT sur la fiche éditée, sans verification_status ni company_id', async () => {
      mockEdit();
      await openEditForm();

      const nameInput = screen.getByLabelText(/nom de la contrepartie/i);
      await userEvent.clear(nameInput);
      await userEvent.type(nameInput, EDITED_NAME);

      await userEvent.click(screen.getByRole('button', { name: /^modifier$/i }));

      await waitFor(() => {
        const put = mockedApiFetch.mock.calls.find(
          ([url, init]) => url === '/trade/counterparties/11' && init?.method === 'PUT',
        );
        expect(put).toBeDefined();

        const body = JSON.parse(String(put?.[1]?.body));
        expect(body.name).toBe(EDITED_NAME);
        // Les rôles pré-remplis repartent tels quels, sans doublon.
        expect(body.roles).toEqual(['supplier', 'manufacturer']);
        // Les champs interdits ne partent JAMAIS, en édition non plus.
        expect(body).not.toHaveProperty('verification_status');
        expect(body).not.toHaveProperty('company_id');
        expect(body).not.toHaveProperty('verified_at');
        expect(body).not.toHaveProperty('verified_by_employee_id');
      });

      // Aucun POST : éditer ne crée pas de doublon.
      expect(
        mockedApiFetch.mock.calls.some(
          ([url, init]) => url === '/trade/counterparties' && init?.method === 'POST',
        ),
      ).toBe(false);
    });

    it('met à jour la liste en place après un PUT réussi', async () => {
      mockEdit();
      await openEditForm();

      const nameInput = screen.getByLabelText(/nom de la contrepartie/i);
      await userEvent.clear(nameInput);
      await userEvent.type(nameInput, EDITED_NAME);
      await userEvent.click(screen.getByRole('button', { name: /^modifier$/i }));

      expect(await screen.findByText(EDITED_NAME)).toBeInTheDocument();
      // L'ancienne valeur a disparu : la liste reflète la mise à jour.
      expect(screen.queryByText('Anadolu Tekstil A.Ş.')).not.toBeInTheDocument();
      // L'autre fiche n'a pas été touchée.
      expect(screen.getByText('SARL Import Ouest')).toBeInTheDocument();
    });

    it('affiche l’erreur et conserve la saisie quand le PUT échoue', async () => {
      mockEdit('fail');
      await openEditForm();

      const nameInput = screen.getByLabelText(/nom de la contrepartie/i);
      await userEvent.clear(nameInput);
      await userEvent.type(nameInput, 'Saisie conservée');
      await userEvent.click(screen.getByRole('button', { name: /^modifier$/i }));

      expect(await screen.findByText(/opération impossible/i)).toBeInTheDocument();

      // Le formulaire reste ouvert et la saisie n'est pas perdue.
      expect(screen.getByLabelText(/nom de la contrepartie/i)).toHaveValue('Saisie conservée');
      // La liste n'a pas été modifiée par un échec.
      expect(screen.getByText('Anadolu Tekstil A.Ş.')).toBeInTheDocument();
    });

    /**
     * #9780 — « Nouvelle contrepartie » doit TOUJOURS repasser en création.
     *
     * Sans cette remise à zéro, ouvrir le formulaire après une édition
     * laisserait `counterpartyEditing` renseigné : le bouton dirait encore
     * « Modifier » et l'envoi ferait un `PUT` sur la fiche précédente — une
     * nouvelle contrepartie écraserait silencieusement une fiche existante.
     */
    it('repasse en mode création après une édition', async () => {
      mockEdit();
      await openEditForm();

      // On est bien en édition : le bouton le dit.
      expect(screen.getByRole('button', { name: /^modifier$/i })).toBeInTheDocument();

      await userEvent.click(screen.getByRole('button', { name: /nouvelle contrepartie/i }));

      // Le formulaire est vide : aucun reste de la fiche éditée.
      expect(screen.getByLabelText(/nom de la contrepartie/i)).toHaveValue('');
      expect(screen.getByLabelText(/^pays/i)).toHaveValue('');
      expect(screen.getByRole('checkbox', { name: 'Fournisseur' })).not.toBeChecked();

      // Et le bouton est redevenu celui de la création.
      expect(screen.getByRole('button', { name: /^enregistrer$/i })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: /^modifier$/i })).not.toBeInTheDocument();

      // Preuve réseau : l'envoi part en POST, jamais en PUT sur la fiche 11.
      await userEvent.type(screen.getByLabelText(/nom de la contrepartie/i), 'Fiche Neuve');
      await userEvent.click(screen.getByRole('checkbox', { name: 'Client' }));
      await userEvent.click(screen.getByRole('button', { name: /^enregistrer$/i }));

      await waitFor(() => {
        expect(
          mockedApiFetch.mock.calls.some(
            ([url, init]) => url === '/trade/counterparties' && init?.method === 'POST',
          ),
        ).toBe(true);
      });

      expect(
        mockedApiFetch.mock.calls.some(
          ([url, init]) => url === '/trade/counterparties/11' && init?.method === 'PUT',
        ),
      ).toBe(false);
    });

    /**
     * #9780 — retirer un rôle déjà coché est un acte, pas une faute de frappe.
     *
     * Un fournisseur qui n'est plus fournisseur ne peut plus être choisi sur
     * une demande de prix : le formulaire l'annonce à l'écran, puis demande
     * confirmation. Un refus n'envoie RIEN.
     */
    it('annonce le retrait d’un rôle et n’envoie rien si l’utilisateur refuse', async () => {
      mockEdit();
      const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(false);

      try {
        await openEditForm();

        // Aucun avertissement tant que rien n'est retiré.
        expect(screen.queryByText(/rôles retirés à l'enregistrement/i)).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('checkbox', { name: 'Fabricant' }));

        // L'avertissement nomme le rôle qui va disparaître. On cible le
        // paragraphe d'avertissement, pas la case à cocher « Fabricant » qui
        // porte le même mot.
        const warning = screen.getByText(/rôles retirés à l'enregistrement/i);
        expect(warning).toBeInTheDocument();
        expect(warning).toHaveTextContent(/Fabricant/);

        await userEvent.click(screen.getByRole('button', { name: /^modifier$/i }));

        // La confirmation a bien été demandée, et le refus a bloqué l'envoi.
        expect(confirmSpy).toHaveBeenCalled();
        expect(
          mockedApiFetch.mock.calls.some(
            ([url, init]) => url === '/trade/counterparties/11' && init?.method === 'PUT',
          ),
        ).toBe(false);

        // La saisie est intacte : l'utilisateur peut recocher le rôle.
        expect(screen.getByRole('checkbox', { name: 'Fabricant' })).not.toBeChecked();
      } finally {
        confirmSpy.mockRestore();
      }
    });

    it('envoie le PUT sans le rôle retiré quand l’utilisateur confirme', async () => {
      mockEdit();
      const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);

      try {
        await openEditForm();

        await userEvent.click(screen.getByRole('checkbox', { name: 'Fabricant' }));
        await userEvent.click(screen.getByRole('button', { name: /^modifier$/i }));

        await waitFor(() => {
          const put = mockedApiFetch.mock.calls.find(
            ([url, init]) => url === '/trade/counterparties/11' && init?.method === 'PUT',
          );
          expect(put).toBeDefined();

          const body = JSON.parse(String(put?.[1]?.body));
          // Seul le rôle conservé repart — le retrait est bien appliqué.
          expect(body.roles).toEqual(['supplier']);
          // Les champs interdits ne partent toujours pas.
          expect(body).not.toHaveProperty('verification_status');
          expect(body).not.toHaveProperty('company_id');
        });
      } finally {
        confirmSpy.mockRestore();
      }
    });

    /**
     * #9780 — retirer TOUS les rôles est refusé par la validation locale.
     *
     * Une contrepartie sans rôle métier n'existe pas : elle n'est ni
     * fournisseur, ni client, ni transporteur, donc elle ne peut être choisie
     * nulle part. Le formulaire doit refuser AVANT le réseau — et avant même
     * la confirmation de retrait, qui n'a plus d'objet quand le retrait est
     * refusé.
     *
     * Le test vérifie les deux moitiés de la règle : l'erreur s'affiche, et
     * AUCUNE requête ne part (ni `PUT` sur la fiche, ni `POST` de création).
     */
    it('refuse le retrait de tous les rôles sans envoyer de requête', async () => {
      mockEdit();
      // `true` : même si l'utilisateur acceptait la confirmation, la
      // validation locale doit avoir bloqué avant.
      const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);

      try {
        await openEditForm();

        // La fiche 11 porte deux rôles : on les décoche tous les deux.
        await userEvent.click(screen.getByRole('checkbox', { name: 'Fournisseur' }));
        await userEvent.click(screen.getByRole('checkbox', { name: 'Fabricant' }));

        expect(screen.getByRole('checkbox', { name: 'Fournisseur' })).not.toBeChecked();
        expect(screen.getByRole('checkbox', { name: 'Fabricant' })).not.toBeChecked();

        await userEvent.click(screen.getByRole('button', { name: /^modifier$/i }));

        // L'erreur de validation locale s'affiche.
        expect(await screen.findByText(/au moins un rôle métier/i)).toBeInTheDocument();

        // Aucune requête : ni PUT sur la fiche éditée, ni POST de création.
        expect(
          mockedApiFetch.mock.calls.some(
            ([url, init]) => url === '/trade/counterparties/11' && init?.method === 'PUT',
          ),
        ).toBe(false);
        expect(
          mockedApiFetch.mock.calls.some(
            ([url, init]) => url === '/trade/counterparties' && init?.method === 'POST',
          ),
        ).toBe(false);

        // La validation locale court-circuite la confirmation de retrait :
        // demander « confirmer le retrait ? » pour un retrait refusé serait
        // une question sans objet.
        expect(confirmSpy).not.toHaveBeenCalled();

        // Le formulaire reste ouvert et la fiche n'a pas bougé dans la liste.
        expect(screen.getByLabelText(/nom de la contrepartie/i)).toHaveValue(
          'Anadolu Tekstil A.Ş.',
        );
        expect(screen.getByText('Anadolu Tekstil A.Ş.')).toBeInTheDocument();
      } finally {
        confirmSpy.mockRestore();
      }
    });
  });
});
