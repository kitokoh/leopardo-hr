import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { captureMarketingLead, getClientIp } from '../_lib/lead-capture';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const signupSchema = z.object({
  email: z.string().email().max(255),
  password: z.string().min(8).max(200),
  company: z.string().max(120).optional().or(z.literal('')),
  plan: z.string().max(80).optional(),
  module: z.string().max(80).optional(),
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

    const validatedData = signupSchema.parse(await request.json());
    const email = sanitizeEmail(validatedData.email);
    const company = validatedData.company ? sanitizeInput(validatedData.company) : undefined;
    const lead = await captureMarketingLead(request, {
      type: 'signup',
      email,
      locale: validatedData.locale,
      page: validatedData.page || '/signup',
      source: validatedData.source || 'signup_form',
      timestamp: validatedData.timestamp,
      data: {
        email,
        company,
        plan: validatedData.plan,
        module: validatedData.module,
        passwordCaptured: false,
      },
    });

    return NextResponse.json(
      {
        success: true,
        message: "Demande d'essai enregistree. Verifiez votre email.",
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
        message: "Erreur lors de la demande d'essai",
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
