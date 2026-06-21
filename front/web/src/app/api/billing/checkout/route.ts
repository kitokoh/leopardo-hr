import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';

const checkoutSchema = z.object({
  plan: z.enum(['starter', 'business', 'enterprise', 'pilot', 'operations', 'scale']),
  billing: z.enum(['monthly', 'annual']).default('monthly'),
  email: z.string().email().max(255),
  company: z.string().min(2).max(120),
  first_name: z.string().max(80).optional().or(z.literal('')),
  last_name: z.string().max(80).optional().or(z.literal('')),
  employees: z.string().max(20).optional(),
  locale: z.enum(['fr', 'en', 'ar', 'tr']).default('fr'),
  success_url: z.string().url().max(500),
  cancel_url: z.string().url().max(500),
});

const LEOPARDO_API_URL =
  process.env.LEOPARDO_API_URL || 'https://gestionemployerbackend.onrender.com';
const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY || '';

/** Sandbox plan prices (EUR cents) — used when Stripe is not configured or in test mode */
const SANDBOX_PRICES: Record<string, Record<string, number>> = {
  pilot: { monthly: 2900, annual: 2400 },
  starter: { monthly: 2900, annual: 2400 },
  operations: { monthly: 9900, annual: 7900 },
  business: { monthly: 9900, annual: 7900 },
  scale: { monthly: 29900, annual: 23900 },
  enterprise: { monthly: 0, annual: 0 },
};

const PLAN_LABELS: Record<string, string> = {
  pilot: 'Pilot',
  starter: 'Pilot',
  operations: 'Operations',
  business: 'Operations',
  scale: 'Scale',
  enterprise: 'Enterprise',
};

/**
 * POST /api/billing/checkout
 *
 * Flow:
 *   1. If STRIPE_SECRET_KEY is set → create real Stripe checkout session via backend
 *   2. If not → return a sandbox mock response simulating Stripe
 *      (redirects to /checkout/success?sandbox=1&session_id=sandbox_xxx)
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  try {
    const body = await request.json();
    const data = checkoutSchema.parse(body);

    const isSandbox = !STRIPE_SECRET_KEY || STRIPE_SECRET_KEY.startsWith('sk_test_') || STRIPE_SECRET_KEY === 'sandbox';

    /* ── SANDBOX MODE ──────────────────────────────── */
    if (isSandbox) {
      const sandboxSessionId = `sandbox_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
      const priceAmount = SANDBOX_PRICES[data.plan]?.[data.billing] ?? 0;
      const planLabel = PLAN_LABELS[data.plan] ?? data.plan;

      // Simulate a brief provisioning call to the backend (trial signup)
      let provisionResult = null;
      try {
        const r = await fetch(`${LEOPARDO_API_URL}/api/v1/trial/signup`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            email: data.email,
            company: data.company,
            first_name: data.first_name || undefined,
            last_name: data.last_name || undefined,
            employees: data.employees || undefined,
            plan: data.plan,
            source: 'checkout_sandbox',
          }),
          signal: AbortSignal.timeout(12000),
        });
        if (r.ok) {
          const json = await r.json();
          if (json.success) provisionResult = json.data;
        }
      } catch {
        // Provisioning unavailable — sandbox still works
      }

      const successUrl = new URL(data.success_url);
      successUrl.searchParams.set('sandbox', '1');
      successUrl.searchParams.set('session_id', sandboxSessionId);
      successUrl.searchParams.set('plan', planLabel);
      successUrl.searchParams.set('billing', data.billing);
      successUrl.searchParams.set('amount', String(priceAmount));
      successUrl.searchParams.set('email', data.email);
      successUrl.searchParams.set('company', data.company);

      return NextResponse.json({
        success: true,
        sandbox: true,
        mode: 'sandbox',
        checkout_url: successUrl.toString(),
        session_id: sandboxSessionId,
        plan: planLabel,
        amount: priceAmount,
        provisioned: !!provisionResult,
        provisioning: provisionResult,
        message: 'Paiement simulé (mode sandbox). Aucune carte débitée.',
      });
    }

    /* ── REAL STRIPE MODE (via backend) ──────────── */
    const response = await fetch(`${LEOPARDO_API_URL}/api/v1/billing/checkout`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        // Manager auth would normally come from a session cookie here
        // For now we bootstrap via the backend API key if provided
        ...(process.env.LEOPARDO_INTERNAL_TOKEN
          ? { Authorization: `Bearer ${process.env.LEOPARDO_INTERNAL_TOKEN}` }
          : {}),
      },
      body: JSON.stringify({
        plan: data.plan,
        email: data.email,
        company: data.company,
        success_url: data.success_url,
        cancel_url: data.cancel_url,
        billing: data.billing,
      }),
      signal: AbortSignal.timeout(15000),
    });

    const json = await response.json();

    if (!response.ok || !json.data?.checkout_url) {
      return NextResponse.json(
        { success: false, error: json.error || 'CHECKOUT_FAILED', message: json.message || 'Impossible de créer la session de paiement.' },
        { status: response.status || 500 }
      );
    }

    return NextResponse.json({
      success: true,
      sandbox: false,
      checkout_url: json.data.checkout_url,
      session_id: json.data.session_id,
    });
  } catch (err) {
    if (err instanceof z.ZodError) {
      return NextResponse.json(
        { success: false, error: 'VALIDATION_ERROR', details: err.issues },
        { status: 400 }
      );
    }
    return NextResponse.json(
      { success: false, error: 'INTERNAL_ERROR', message: 'Erreur serveur' },
      { status: 500 }
    );
  }
}
