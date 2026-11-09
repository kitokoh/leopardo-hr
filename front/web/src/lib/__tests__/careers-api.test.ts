/**
 * Unit tests for the public careers portal data layer (issue #1325).
 */

import {
  getPublicJobPostings,
  getPublicJobPosting,
  getPublicCareersCompany,
  submitPublicApplication,
  isExternalOffer,
  type PublicJobEntry,
  type PublicExternalJobOffer,
  type PublicJobPosting,
} from '../careers-api';

describe('careers-api', () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    global.fetch = originalFetch;
    jest.restoreAllMocks();
  });

  describe('getPublicJobPostings', () => {
    it('returns null when the company slug is unknown (404)', async () => {
      global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 404 } as Response);

      const result = await getPublicJobPostings('unknown-company');

      expect(result).toBeNull();
    });

    it('returns the job list on success', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [{ id: 1, title: 'Backend Engineer' }] }),
      } as unknown as Response);

      const result = await getPublicJobPostings('acme');

      expect(result).toEqual([{ id: 1, title: 'Backend Engineer' }]);
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/public/careers/acme'),
        expect.any(Object)
      );
    });

    it('throws on unexpected server errors', async () => {
      global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 500 } as Response);

      await expect(getPublicJobPostings('acme')).rejects.toThrow('HTTP 500');
    });
  });

  describe('getPublicCareersCompany', () => {
    it('returns null when the company slug is unknown (404)', async () => {
      global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 404 } as Response);

      const result = await getPublicCareersCompany('unknown-company');

      expect(result).toBeNull();
    });

    it('returns the tenant branding from meta.company on success', async () => {
      const company = {
        id: 1,
        name: 'Acme Corp',
        slug: 'acme',
        display_name: 'Acme Careers',
        logo_url: 'https://cdn.example.com/acme-logo.png',
        primary_color: '#FF0000',
        accent_color: '#00FF00',
      };
      global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [], meta: { company } }),
      } as unknown as Response);

      const result = await getPublicCareersCompany('acme');

      expect(result).toEqual(company);
    });

    it('returns null when the response has no meta.company', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [] }),
      } as unknown as Response);

      const result = await getPublicCareersCompany('acme');

      expect(result).toBeNull();
    });

    it('throws on unexpected server errors', async () => {
      global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 500 } as Response);

      await expect(getPublicCareersCompany('acme')).rejects.toThrow('HTTP 500');
    });
  });

  describe('getPublicJobPosting', () => {
    it('returns null for a job that does not exist / is not published', async () => {
      global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 404 } as Response);

      const result = await getPublicJobPosting('acme', '42');

      expect(result).toBeNull();
    });

    it('returns the job payload on success', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: { id: 42, title: 'Support Engineer' } }),
      } as unknown as Response);

      const result = await getPublicJobPosting('acme', '42');

      expect(result).toEqual({ id: 42, title: 'Support Engineer' });
    });
  });

  describe('submitPublicApplication', () => {
    it('reports success on HTTP 201', async () => {
      global.fetch = jest.fn().mockResolvedValue({ status: 201 } as Response);

      const result = await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: 'nadia@example.com',
      });

      expect(result).toEqual({ success: true, status: 201 });
      expect(global.fetch).toHaveBeenCalledWith(
        '/api/v1/public/careers/acme/jobs/42/apply',
        expect.objectContaining({ method: 'POST' })
      );
    });

    it('surfaces field validation errors on HTTP 422', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        status: 422,
        json: async () => ({ errors: { email: ['The email field is required.'] } }),
      } as unknown as Response);

      const result = await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: '',
      });

      expect(result.success).toBe(false);
      expect(result.status).toBe(422);
      expect(result.errors).toEqual({ email: ['The email field is required.'] });
    });

    it('falls back to a generic message on unexpected errors', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        status: 500,
        json: async () => {
          throw new Error('not json');
        },
      } as unknown as Response);

      const result = await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: 'nadia@example.com',
      });

      expect(result.success).toBe(false);
      expect(result.status).toBe(500);
      expect(result.message).toBeTruthy();
    });
  });

  // -------------------------------------------------------------------
  // BC-10 (#9219) — offres agrégées, filtres, RGPD, anti-spam
  // -------------------------------------------------------------------

  describe('isExternalOffer', () => {
    const tenantJob = { id: 1, title: 'Développeur PHP' } as PublicJobPosting;
    const externalJob = {
      id: 2,
      external_id: 'adz-1',
      title: 'Data Engineer',
      company_name: 'DataCorp',
      apply_url: 'https://partner.example/jobs/1',
      origin: 'external',
    } as PublicExternalJobOffer;

    it('reconnaît une offre agrégée par la présence de apply_url', () => {
      expect(isExternalOffer(externalJob)).toBe(true);
    });

    it('ne confond pas une offre du tenant avec une offre agrégée', () => {
      // Une offre du tenant se candidate sur NOTRE formulaire : elle n'a pas
      // d'URL sortante. La confondre enverrait le candidat vers un formulaire
      // inexistant.
      expect(isExternalOffer(tenantJob)).toBe(false);
    });

    it('discrimine correctement une liste mixte', () => {
      const entries: PublicJobEntry[] = [tenantJob, externalJob];

      const external = entries.filter(isExternalOffer);
      const own = entries.filter((e) => !isExternalOffer(e));

      expect(external).toHaveLength(1);
      expect(own).toHaveLength(1);
      expect(external[0].company_name).toBe('DataCorp');
    });
  });

  describe('getPublicJobPostings — filtres et pagination', () => {
    it('transmet les filtres actifs et la pagination', async () => {
      const fetchMock = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [] }),
      } as unknown as Response);
      global.fetch = fetchMock;

      await getPublicJobPostings('acme', {
        q: 'php',
        location: 'Paris',
        contractType: 'cdi',
        remotePolicy: 'hybrid',
      });

      const url = String(fetchMock.mock.calls[0][0]);
      expect(url).toContain('q=php');
      expect(url).toContain('location=Paris');
      expect(url).toContain('contract_type=cdi');
      expect(url).toContain('remote_policy=hybrid');
      expect(url).toContain('per_page=100');
    });

    it("n'ajoute pas de paramètre pour un filtre vide", async () => {
      const fetchMock = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [] }),
      } as unknown as Response);
      global.fetch = fetchMock;

      await getPublicJobPostings('acme', { q: '', location: undefined });

      const url = String(fetchMock.mock.calls[0][0]);
      expect(url).not.toContain('q=');
      expect(url).not.toContain('location=');
    });

    it('encode le slug pour ne pas casser l’URL', async () => {
      const fetchMock = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [] }),
      } as unknown as Response);
      global.fetch = fetchMock;

      await getPublicJobPostings('acme corp/../x');

      const url = String(fetchMock.mock.calls[0][0]);
      expect(url).toContain('acme%20corp%2F..%2Fx');
    });

    it('rend les deux populations d’offres dans la même liste', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({
          data: [
            { id: 1, title: 'Interne' },
            { id: 2, title: 'Externe', apply_url: 'https://partner.example/1' },
          ],
        }),
      } as unknown as Response);

      const jobs = await getPublicJobPostings('acme');

      expect(jobs).toHaveLength(2);
      expect(jobs?.filter(isExternalOffer)).toHaveLength(1);
    });
  });

  describe('submitPublicApplication — RGPD et anti-spam', () => {
    it('transmet le consentement RGPD et la source par défaut', async () => {
      const fetchMock = jest.fn().mockResolvedValue({ status: 201 } as Response);
      global.fetch = fetchMock;

      await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: 'nadia@example.com',
        consent: true,
      });

      const body = fetchMock.mock.calls[0][1].body as FormData;
      // Oublier le consentement ferait rejeter TOUTE candidature en 422 côté
      // serveur : c'est une exigence RGPD, pas un champ décoratif.
      expect(body.get('consent')).toBe('1');
      expect(body.get('source')).toBe('website');
    });

    it('transmet consent=0 quand le candidat n’a pas consenti', async () => {
      const fetchMock = jest.fn().mockResolvedValue({ status: 201 } as Response);
      global.fetch = fetchMock;

      await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: 'nadia@example.com',
        consent: false,
      });

      const body = fetchMock.mock.calls[0][1].body as FormData;
      expect(body.get('consent')).toBe('0');
    });

    it('joint le CV au corps multipart', async () => {
      const fetchMock = jest.fn().mockResolvedValue({ status: 201 } as Response);
      global.fetch = fetchMock;
      const resume = new File(['contenu'], 'cv.pdf', { type: 'application/pdf' });

      await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: 'nadia@example.com',
        consent: true,
        resume,
      });

      const body = fetchMock.mock.calls[0][1].body as FormData;
      expect(body.get('resume')).toBe(resume);
    });

    it('remonte le message serveur sur un rejet antivirus', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        status: 422,
        json: async () => ({
          error: 'RESUME_REJECTED',
          message: "Le fichier joint a été refusé par l'analyse de sécurité.",
        }),
      } as unknown as Response);

      const result = await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: 'nadia@example.com',
        consent: true,
      });

      expect(result.success).toBe(false);
      expect(result.message).toContain('analyse de sécurité');
    });

    it('remonte le conflit de candidature en double', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        status: 409,
        json: async () => ({ error: 'ALREADY_APPLIED', message: 'You have already applied.' }),
      } as unknown as Response);

      const result = await submitPublicApplication('acme', 42, {
        first_name: 'Nadia',
        last_name: 'Candidate',
        email: 'nadia@example.com',
        consent: true,
      });

      expect(result.status).toBe(409);
      expect(result.message).toContain('already applied');
    });
  });
});
