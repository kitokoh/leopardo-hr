import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { areFormsEnabled, captureMarketingLead, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { evaluateSpam, forbiddenOriginResponse, readSpamSignals, spamResponse } from '../_lib/antispam';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

/**
 * #8440 — formulaire « Devenir actionnaire » (page vitrine `/investir`,
 * entrée footer discrète). Capture la manifestation d'intérêt (non
 * contraignante) de prise de parts dans l'entreprise via le canal leads
 * existant (type `equity_interest`, pattern demo/contact) : le suivi se
 * fait ensuite dans l'admin plateforme (`crm.view`).
 *
 * Aucune donnée bancaire, aucun paiement : nom, email, téléphone optionnel,
 * pays, tranche indicative, message, consentement explicite au recontact.
 */
const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const AMOUNT_BRACKETS = ['lt_1k', '1k_10k', '10k_50k', 'gt_50k', 'discuss'] as const;

const equitySchema = z.object({
  name: z.string().min(2).max(120),
  email: z.string().email().max(255),
  phone: z.string().max(40).optional().or(z.literal('')),
  country: z.string().length(2).optional().or(z.literal('')),
  amountBracket: z.enum(AMOUNT_BRACKETS),
  message: z.string().max(2000).optional().or(z.literal('')),
  consent: z.literal(true),
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

    // Barrières anti-bot AVANT toute validation et toute persistance (même
    // raisonnement que #7594) : une seule lecture du corps, rejet silencieux.
    const rawBody: unknown = await request.json();
    const verdict = evaluateSpam(readSpamSignals(rawBody), request);
    if (verdict.spam) {
      return verdict.reason === 'bad-origin' ? forbiddenOriginResponse() : spamResponse();
    }

    const validatedData = equitySchema.parse(rawBody);
    const sanitizedData = {
      name: sanitizeInput(validatedData.name),
      email: sanitizeEmail(validatedData.email),
      phone: validatedData.phone ? sanitizeInput(validatedData.phone) : undefined,
      country: validatedData.country ? validatedData.country.toUpperCase() : undefined,
      amount_bracket: validatedData.amountBracket,
      message: validatedData.message ? sanitizeInput(validatedData.message) : undefined,
      consent: true,
    };

    const lead = await captureMarketingLead(request, {
      type: 'equity_interest',
      email: sanitizedData.email,
      locale: validatedData.locale,
      page: validatedData.page || '/investir',
      source: validatedData.source || 'equity_form',
      timestamp: validatedData.timestamp,
      data: sanitizedData,
    });

    return NextResponse.json(
      {
        success: true,
        message: 'Manifestation d\u2019intérêt enregistrée. Nous vous recontacterons.',
        data: {
          id: lead.id,
          email: sanitizedData.email,
          name: sanitizedData.name,
          persisted: lead.persisted,
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
        message: 'Erreur lors de l\u2019enregistrement de la manifestation d\u2019intérêt',
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
