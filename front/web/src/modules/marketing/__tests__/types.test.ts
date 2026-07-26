import { calendarDateFor, dayKey, type SocialPost } from '@/modules/marketing/types';

function makePost(overrides: Partial<SocialPost> = {}): SocialPost {
  return {
    id: 1,
    content: 'hello',
    target_platforms: ['linkedin'],
    status: 'draft',
    ...overrides,
  };
}

describe('calendarDateFor', () => {
  it('prefers scheduled_at when present', () => {
    const post = makePost({
      scheduled_at: '2026-08-01T10:00:00Z',
      published_at: '2026-08-02T10:00:00Z',
      created_at: '2026-07-01T10:00:00Z',
    });
    expect(calendarDateFor(post)?.toISOString()).toBe('2026-08-01T10:00:00.000Z');
  });

  it('falls back to published_at when scheduled_at is missing', () => {
    const post = makePost({
      published_at: '2026-08-02T10:00:00Z',
      created_at: '2026-07-01T10:00:00Z',
    });
    expect(calendarDateFor(post)?.toISOString()).toBe('2026-08-02T10:00:00.000Z');
  });

  it('falls back to created_at when nothing else is set', () => {
    const post = makePost({ created_at: '2026-07-01T10:00:00Z' });
    expect(calendarDateFor(post)?.toISOString()).toBe('2026-07-01T10:00:00.000Z');
  });

  it('returns null when no date is available', () => {
    const post = makePost();
    expect(calendarDateFor(post)).toBeNull();
  });

  it('returns null for an unparsable date', () => {
    const post = makePost({ scheduled_at: 'not-a-date' });
    expect(calendarDateFor(post)).toBeNull();
  });
});

describe('dayKey', () => {
  it('formats a date as YYYY-MM-DD with zero-padding', () => {
    expect(dayKey(new Date(2026, 0, 5))).toBe('2026-01-05');
    expect(dayKey(new Date(2026, 11, 25))).toBe('2026-12-25');
  });
});
