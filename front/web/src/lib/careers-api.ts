/**
 * Server-side data access for the public careers portal
 * (issue #1325 — ATS Web: Portail Carrieres Public).
 *
 * These helpers run in Next.js Server Components / Route metadata
 * generators, so they talk directly to the Laravel API instead of going
 * through the browser-only `/api/v1` same-origin proxy. They intentionally
 * do not send any auth header: every endpoint under `/public/careers` is
 * unauthenticated (tenant is resolved from `{companySlug}` on the backend).
 */

const DEFAULT_BACKEND_API_URL = 'https://gestionemployerbackend.onrender.com/api/v1';

function resolveBackendBaseUrl(): string {
  return (
    process.env.API_PROXY_TARGET ||
    process.env.BACKEND_API_URL ||
    process.env.NEXT_PUBLIC_API_URL ||
    DEFAULT_BACKEND_API_URL
  ).replace(/\/$/, '');
}

export interface PublicJobPosting {
  id: number;
  title: string;
  description: string | null;
  department: { id: number; name: string } | null;
  location: string | null;
  remote_policy: 'onsite' | 'hybrid' | 'remote';
  contract_type: 'cdi' | 'cdd' | 'stage' | 'freelance';
  salary_range_min: number | string | null;
  salary_range_max: number | string | null;
  currency: string | null;
  skills_required: string[] | null;
  status: string;
  closes_at: string | null;
  created_at: string | null;
}

/**
 * Per-tenant branding exposed by `PublicCareerController::companyPayload()`
 * (issue #1325) so the careers portal (issue #1330) can render the client
 * company's identity instead of Leopardo's own — logo, display name and
 * brand colors — on an otherwise generic, unauthenticated page.
 */
export interface PublicCareersCompany {
  id: number;
  name: string;
  slug: string;
  display_name: string;
  logo_url: string | null;
  primary_color: string;
  accent_color: string;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: { current_page?: number; last_page?: number; total?: number; company: PublicCareersCompany };
}

interface ShowResponse<T> {
  data: T;
  meta?: { company: PublicCareersCompany };
}

async function careersFetch(path: string, init?: RequestInit): Promise<Response> {
  return fetch(`${resolveBackendBaseUrl()}/public/careers${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...(init?.headers ?? {}),
    },
    // Job listings change infrequently; a short revalidation window keeps
    // the SEO-critical pages fast without serving stale postings for long.
    next: { revalidate: 300 },
  });
}

/**
 * Returns null when the company slug does not exist (404) so callers can
 * render Next.js `notFound()`.
 */
export async function getPublicJobPostings(
  companySlug: string,
  filters?: { location?: string; contractType?: string }
): Promise<PublicJobPosting[] | null> {
  const params = new URLSearchParams();
  if (filters?.location) params.set('location', filters.location);
  if (filters?.contractType) params.set('contract_type', filters.contractType);
  params.set('per_page', '100');

  const query = params.toString();
  const response = await careersFetch(`/${encodeURIComponent(companySlug)}${query ? `?${query}` : ''}`);

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(`Failed to load job postings for ${companySlug}: HTTP ${response.status}`);
  }

  const payload = (await response.json()) as PaginatedResponse<PublicJobPosting>;
  return payload.data;
}

export async function getPublicJobPosting(
  companySlug: string,
  jobId: string
): Promise<PublicJobPosting | null> {
  const response = await careersFetch(`/${encodeURIComponent(companySlug)}/jobs/${encodeURIComponent(jobId)}`);

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(`Failed to load job posting ${jobId} for ${companySlug}: HTTP ${response.status}`);
  }

  const payload = (await response.json()) as ShowResponse<PublicJobPosting>;
  return payload.data;
}

/**
 * Fetches the tenant branding (`meta.company`) exposed by the careers index
 * endpoint (issue #1330, criterion "Branding dynamique selon le tenant").
 * Reuses the same `/public/careers/{companySlug}` request the listing page
 * already issues for `getPublicJobPostings`; Next.js's per-request fetch
 * cache (see `careersFetch`'s `next.revalidate`) dedupes identical requests
 * within the same render, so calling both from the same page is not an
 * extra round trip in practice. Returns null for an unknown company slug so
 * pages can fall back to Leopardo's own default branding.
 */
export async function getPublicCareersCompany(companySlug: string): Promise<PublicCareersCompany | null> {
  const response = await careersFetch(`/${encodeURIComponent(companySlug)}?per_page=1`);

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(`Failed to load company branding for ${companySlug}: HTTP ${response.status}`);
  }

  const payload = (await response.json()) as PaginatedResponse<PublicJobPosting>;
  return payload.meta?.company ?? null;
}

export interface SubmitApplicationInput {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  cover_letter?: string;
  source?: 'website' | 'referral' | 'linkedin' | 'agency' | 'other';
  resume?: File | null;
}

export interface SubmitApplicationResult {
  success: boolean;
  status?: number;
  errors?: Record<string, string[]>;
  message?: string;
}

/**
 * Client-side submission (used inside the 'use client' apply form). Uses
 * multipart/form-data so the resume upload reaches Laravel's `resume` file
 * validation rule directly.
 */
export async function submitPublicApplication(
  companySlug: string,
  jobId: number | string,
  input: SubmitApplicationInput
): Promise<SubmitApplicationResult> {
  const formData = new FormData();
  formData.set('first_name', input.first_name);
  formData.set('last_name', input.last_name);
  formData.set('email', input.email);
  if (input.phone) formData.set('phone', input.phone);
  if (input.cover_letter) formData.set('cover_letter', input.cover_letter);
  formData.set('source', input.source ?? 'website');
  if (input.resume) formData.set('resume', input.resume);

  const response = await fetch(
    `/api/v1/public/careers/${encodeURIComponent(companySlug)}/jobs/${encodeURIComponent(String(jobId))}/apply`,
    {
      method: 'POST',
      body: formData,
    }
  );

  if (response.status === 201) {
    return { success: true, status: 201 };
  }

  let payload: unknown = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (response.status === 422 && payload && typeof payload === 'object' && 'errors' in payload) {
    return {
      success: false,
      status: 422,
      errors: (payload as { errors: Record<string, string[]> }).errors,
    };
  }

  const message =
    payload && typeof payload === 'object' && 'message' in payload
      ? String((payload as Record<string, unknown>).message)
      : 'Une erreur est survenue. Merci de reessayer.';

  return { success: false, status: response.status, message };
}

export const CONTRACT_TYPE_LABELS: Record<PublicJobPosting['contract_type'], string> = {
  cdi: 'CDI',
  cdd: 'CDD',
  stage: 'Stage',
  freelance: 'Freelance',
};

export const REMOTE_POLICY_LABELS: Record<PublicJobPosting['remote_policy'], string> = {
  onsite: 'Sur site',
  hybrid: 'Hybride',
  remote: 'Teletravail',
};
