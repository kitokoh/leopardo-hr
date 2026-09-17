import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { areFormsEnabled, captureMarketingLead, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { evaluateSpam, forbiddenOriginResponse, readSpamSignals, spamResponse } from '../_lib/antispam';
import { RateLimiter, sanitizeEmail } from '@/modules/vitrine/lib/validation';

const rateLimiter = new RateLimiter(10, 15 * 60 * 1000);

const newsletterSchema = z.object({
  email: z.string().email().max(255),
  locale: z.enum(['fr', 'en', 'ar', 'tr']).optional(),
  page: z.string().max(300).optional(),
  source: z.string().max(120).optional(),
  timestamp: z.string().optional(),
});

export async function POST(request: NextRequest) {
  if (!areFormsEnabled()) {
    return formsDisabledResponse();
  }

  try {
    const ip = getClientIp(request);

    if (!rateLimiter.isAllowed(ip)) {
      return NextResponse.json(
        {
          success: false,
          message: 'Trop de tentatives. Veuillez réessayer plus tard.',
          error: 'RATE_LIMIT_EXCEEDED',
        },
        { status: 429 }
      );
    }

    // #7594 — barrières anti-bot AVANT toute validation et toute persistance.
    // Une seule lecture du corps : les signaux sont lus sur le BRUT, car les
    // schémas zod ne déclarent ni le honeypot ni l'horodatage de rendu (zod
    // supprime les clés inconnues). Rejet silencieux : même réponse qu'un
    // succès, mais rien n'est persisté.
    const rawBody: unknown = await request.json();
    const verdict = evaluateSpam(readSpamSignals(rawBody), request);
    if (verdict.spam) {
      return verdict.reason === 'bad-origin' ? forbiddenOriginResponse() : spamResponse();
    }

    const validatedData = newsletterSchema.parse(rawBody);
    const email = sanitizeEmail(validatedData.email);
    const lead = await captureMarketingLead(request, {
      type: 'newsletter',
      email,
      locale: validatedData.locale,
      page: validatedData.page || '/newsletter',
      source: validatedData.source || 'newsletter_form',
      timestamp: validatedData.timestamp,
      data: { email },
    });

    return NextResponse.json(
      {
        success: true,
        message: 'Inscription a la newsletter reussie.',
        data: {
          id: lead.id,
          email,
          confirmationSent: lead.emailForwarded,
          crmForwarded: lead.crmForwarded,
        },
      },
      { status: 201 }
    );
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json(
        {
          success: false,
          message: 'Donnees invalides',
          error: 'VALIDATION_ERROR',
          details: error.issues,
        },
        { status: 400 }
      );
    }

    return NextResponse.json(
      {
        success: false,
        message: "Erreur lors de l'inscription a la newsletter",
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
