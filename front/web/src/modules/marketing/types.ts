/**
 * Module Marketing — Phase 4 (calendar + PostEditor).
 *
 * Shared types/constants between the legacy `/social-marketing` list page
 * and the new `/social` calendar page + `PostEditor` component. Keeping
 * these in one place avoids drifting definitions of `SUPPORTED_PLATFORMS`
 * across the two surfaces.
 */

export type SocialAccount = {
  id: number;
  provider: string;
  display_name?: string | null;
  status: string;
  connected_platforms?: string[] | null;
  connected_at?: string | null;
  last_error?: string | null;
};

export type SocialAccountPayload = {
  data?: SocialAccount;
};

export type SocialPost = {
  id: number;
  content: string;
  target_platforms: string[];
  status: string;
  scheduled_at?: string | null;
  published_at?: string | null;
  created_at?: string | null;
  error_message?: string | null;
};

export type SocialPostsPayload = {
  data?: SocialPost[];
  meta?: {
    current_page?: number;
    last_page?: number;
  };
};

// Doit rester synchronisee manuellement avec
// StoreSocialPostRequest::supportedPlatforms() (api/app/Modules/Marketing).
export const SUPPORTED_PLATFORMS = [
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'facebook_page', label: 'Facebook (page)' },
  { value: 'facebook_group', label: 'Facebook (groupe)' },
  { value: 'twitter', label: 'X / Twitter' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'youtube', label: 'YouTube' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'pinterest', label: 'Pinterest' },
  { value: 'reddit', label: 'Reddit' },
  { value: 'telegram', label: 'Telegram' },
  { value: 'gmb', label: 'Google Business' },
  { value: 'bluesky', label: 'Bluesky' },
  { value: 'threads', label: 'Threads' },
];

export const STATUS_STYLES: Record<string, { class: string; label: string }> = {
  draft: { class: 'bg-slate-100 text-slate-600', label: 'Brouillon' },
  scheduled: { class: 'bg-info/15 text-info', label: 'Planifie' },
  publishing: { class: 'bg-amber-50 text-amber-700', label: 'Publication...' },
  published: { class: 'bg-emerald-50 text-emerald-700', label: 'Publie' },
  failed: { class: 'bg-red-50 text-red-700', label: 'Echec' },
};

/**
 * The calendar day a post "lives on": the date it is/was scheduled for,
 * falling back to when it was actually published, then to creation date
 * for drafts that were never scheduled (so nothing silently disappears
 * from the calendar).
 */
export function calendarDateFor(post: SocialPost): Date | null {
  const raw = post.scheduled_at ?? post.published_at ?? post.created_at ?? null;
  if (!raw) {
    return null;
  }
  const date = new Date(raw);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function dayKey(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
