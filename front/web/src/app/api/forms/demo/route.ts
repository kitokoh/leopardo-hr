import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { captureMarketingLead, getClientIp } from '../_lib/lead-capture';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const demoSchema = z.object({
  name: z.string().min(2).max(100),
  email: z.string().email().max(255),
  company: z.string().min(2).max(100),
  phone: z.string().max(30).optional().or(z.literal('')),
  employees: z.enum(['1-10', '11-50', '51-200', '201-500', '500+']).optional().or(z.literal('')),
  preferredDate: z.string().max(40).optional().or(z.literal('')),
  message: z.string().max(5000).optional().or(z.literal('')),
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

    const validatedData = demoSchema.parse(await request.json());
    const sanitizedData = {
      name: sanitizeInput(validatedData.name),
      email: sanitizeEmail(validatedData.email),
      company: sanitizeInput(validatedData.company),
      phone: validatedData.phone ? sanitizeInput(validatedData.phone) : undefined,
      employees: validatedData.employees || undefined,
      preferredDate: validatedData.preferredDate || undefined,
      message: validatedData.message ? sanitizeInput(validatedData.message) : undefined,
    };

    const lead = await captureMarketingLead(request, {
      type: 'demo_request',
      email: sanitizedData.email,
      locale: validatedData.locale,
      page: validatedData.page || '/demo',
      source: validatedData.source || 'demo_form',
      timestamp: validatedData.timestamp,
      data: sanitizedData,
    });

    return NextResponse.json(
      {
        success: true,
        message: 'Demande de demo envoyee. Nous vous contacterons bientot.',
        data: {
          id: lead.id,
          email: sanitizedData.email,
          name: sanitizedData.name,
          company: sanitizedData.company,
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
        message: 'Erreur lors de la demande de demo',
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
