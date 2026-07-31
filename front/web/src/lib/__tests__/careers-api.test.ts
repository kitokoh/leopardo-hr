/**
 * Unit tests for the public careers portal data layer (issue #1325).
 */

import {
  getPublicJobPostings,
  getPublicJobPosting,
  submitPublicApplication,
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
});
