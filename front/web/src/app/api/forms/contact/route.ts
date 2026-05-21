import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { captureMarketingLead, getClientIp } from '../_lib/lead-capture';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const contactSchema = z.object({
  name: z.string().min(2).max(100),
  email: z.string().email().max(255),
  subject: z.string().min(5).max(200),
  message: z.string().min(10).max(5000),
  phone: z.string().max(30).optional().or(z.literal('')),
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

    const validatedData = contactSchema.parse(await request.json());
    const sanitizedData = {
      name: sanitizeInput(validatedData.name),
      email: sanitizeEmail(validatedData.email),
      subject: sanitizeInput(validatedData.subject),
      message: sanitizeInput(validatedData.message),
      phone: validatedData.phone ? sanitizeInput(validatedData.phone) : undefined,
    };

    const lead = await captureMarketingLead(request, {
      type: 'contact',
      email: sanitizedData.email,
      locale: validatedData.locale,
      page: validatedData.page || '/contact',
      source: validatedData.source || 'contact_form',
      timestamp: validatedData.timestamp,
      data: sanitizedData,
    });

    return NextResponse.json(
      {
        success: true,
        message: 'Message envoye. Nous vous repondrons bientot.',
        data: {
          id: lead.id,
          email: sanitizedData.email,
          name: sanitizedData.name,
          subject: sanitizedData.subject,
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
        message: "Erreur lors de l'envoi du message",
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
