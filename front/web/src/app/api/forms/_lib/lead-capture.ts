import { NextRequest } from 'next/server';

type MarketingLeadType = 'signup' | 'demo_request' | 'newsletter' | 'contact';

export type MarketingLeadPayload = {
  type: MarketingLeadType;
  email: string;
  locale?: string;
  page?: string;
  source?: string;
  timestamp?: string;
  data?: Record<string, unknown>;
};

export type MarketingLeadCaptureResult = {
  id: string;
  crmForwarded: boolean;
  emailForwarded: boolean;
};

type ForwarderTarget = 'crm' | 'email';

const DEFAULT_TIMEOUT_MS = 2500;
const supportedLocales = new Set(['fr', 'en', 'ar', 'tr']);

export function getClientIp(request: NextRequest): string {
  const forwardedFor = request.headers.get('x-forwarded-for');

  if (forwardedFor) {
    return forwardedFor.split(',')[0]?.trim() || 'unknown';
  }

  return request.headers.get('x-real-ip') || 'unknown';
}

export function normalizeLeadLocale(locale?: string): string {
  const normalized = locale?.trim().toLowerCase();

  return normalized && supportedLocales.has(normalized) ? normalized : 'fr';
}

export async function captureMarketingLead(
  request: NextRequest,
  payload: MarketingLeadPayload
): Promise<MarketingLeadCaptureResult> {
  const id = createLeadId(payload.type);
  const locale = normalizeLeadLocale(payload.locale);
  const lead = {
    id,
    type: payload.type,
    email: payload.email,
    locale,
    page: payload.page || '/',
    source: payload.source || `${payload.type}_form`,
    timestamp: payload.timestamp || new Date().toISOString(),
    ip: getClientIp(request),
    userAgent: request.headers.get('user-agent') || 'unknown',
    referrer: request.headers.get('referer') || request.headers.get('referrer') || null,
    data: payload.data || {},
  };

  logLeadEvent('marketing.lead.received', lead);

  const [crmForwarded, emailForwarded] = await Promise.all([
    forwardLead('crm', lead),
    forwardLead('email', lead),
  ]);

  logLeadEvent('marketing.lead.processed', {
    id,
    type: payload.type,
    locale,
    page: lead.page,
    crmForwarded,
    emailForwarded,
  });

  return {
    id,
    crmForwarded,
    emailForwarded,
  };
}

function createLeadId(type: MarketingLeadType): string {
  return `${type}_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

async function forwardLead(
  target: ForwarderTarget,
  lead: Record<string, unknown>
): Promise<boolean> {
  const url =
    target === 'crm'
      ? process.env.MARKETING_CRM_WEBHOOK_URL
      : process.env.MARKETING_EMAIL_WEBHOOK_URL;

  if (!url) {
    return false;
  }

  const timeoutMs = Number(process.env.MARKETING_LEAD_FORWARD_TIMEOUT_MS || DEFAULT_TIMEOUT_MS);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: buildForwardHeaders(target),
      body: JSON.stringify({
        target,
        lead,
      }),
      signal: controller.signal,
    });

    if (!response.ok) {
      logLeadEvent('marketing.lead.forward_failed', {
        id: lead.id,
        type: lead.type,
        target,
        status: response.status,
      });

      return false;
    }

    return true;
  } catch (error) {
    logLeadEvent('marketing.lead.forward_error', {
      id: lead.id,
      type: lead.type,
      target,
      error: error instanceof Error ? error.name : 'unknown',
    });

    return false;
  } finally {
    clearTimeout(timeout);
  }
}

function buildForwardHeaders(target: ForwarderTarget): HeadersInit {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'X-Leopardo-Lead-Target': target,
  };
  const token = process.env.MARKETING_LEAD_WEBHOOK_TOKEN;

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  return headers;
}

function logLeadEvent(event: string, payload: Record<string, unknown>): void {
  if (process.env.NODE_ENV === 'test') {
    return;
  }

  console.info(
    JSON.stringify({
      event,
      service: 'leopardo-web',
      ...payload,
    })
  );
}
