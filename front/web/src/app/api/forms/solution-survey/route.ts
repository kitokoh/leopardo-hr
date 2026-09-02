import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { areFormsEnabled, captureMarketingLead, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

/**
 * Wizard « Je suis restaurateur » — capture du lead (issue #6692).
 *
 * Le prospect donne son email + consentement marketing explicite à la fin
 * du wizard ; la route persiste le lead via le canal PA2-MKT-007
 * (POST /marketing/leads, secret partagé) avec le pack sélectionné dans
 * `payload` — source de vérité pour le CRM et l'activation ultérieure.
 *
 * RGPD : le champ `consent` est OBLIGATOIRE (literal true) — aucun lead
 * sans consentement. Consentement horodaté (`consented_at`) stocké dans
 * le payload. Registre RGPD : docs/RGPD_REGISTRE_TRAITEMENTS.md.
 */

const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const solutionSurveySchema = z.object({
  email: z.string().email().max(255),
  consent: z.literal(true, { message: 'Consentement marketing requis.' }),
  locale: z.enum(['fr', 'en', 'ar', 'tr']).optional(),
  page: z.string().max(300).optional(),
  source: z.string().max(120).optional(),
  timestamp: z.string().optional(),
  data: z.object({
    solution: z.string().min(1).max(40),
    answers: z.record(z.string(), z.unknown()).optional(),
    packages: z.array(z.string().min(1).max(60)).max(30).optional(),
  }),
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

    const validatedData = solutionSurveySchema.parse(await request.json());
    const email = sanitizeEmail(validatedData.email);

    if (!email) {
      return NextResponse.json(
        {
          success: false,
          message: 'Adresse email invalide.',
          error: 'INVALID_EMAIL',
        },
        { status: 422 }
      );
    }

    const consentedAt = validatedData.timestamp || new Date().toISOString();

    const lead = await captureMarketingLead(request, {
      type: 'solution_survey',
      email,
      locale: validatedData.locale,
      page: validatedData.page || '/restaurant',
      source: validatedData.source || 'solution_survey_restaurant',
      timestamp: consentedAt,
      data: {
        consent: true,
        consented_at: consentedAt,
        solution: sanitizeInput(validatedData.data.solution),
        answers: validatedData.data.answers ?? {},
        packages: validatedData.data.packages ?? [],
      },
    });

    return NextResponse.json(
      {
        success: true,
        message: 'Reçu ! Votre pack Leopardo arrive dans votre boîte mail.',
        data: {
          id: lead.id,
          email,
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
          message: 'Données invalides',
          error: 'VALIDATION_ERROR',
          details: error.issues,
        },
        { status: 400 }
      );
    }

    return NextResponse.json(
      {
        success: false,
        message: "Erreur lors de l'envoi",
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
