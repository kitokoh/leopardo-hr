import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { captureMarketingLead, getClientIp } from '../_lib/lead-capture';
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
  try {
    const ip = getClientIp(request);

    if (!rateLimiter.isAllowed(ip)) {
      return NextResponse.json(
        {
          success: false,
          message: 'Trop de tentatives. Veuillez reessayer plus tard.',
          error: 'RATE_LIMIT_EXCEEDED',
        },
        { status: 429 }
      );
    }

    const validatedData = newsletterSchema.parse(await request.json());
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
