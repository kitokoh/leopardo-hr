import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { captureMarketingLead, getClientIp } from '../_lib/lead-capture';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const signupSchema = z.object({
  email: z.string().email().max(255),
  company: z.string().min(2).max(120),
  role: z.enum(['founder', 'manager', 'hr', 'operations', 'other']).optional(),
  employees: z.enum(['1-10', '11-50', '51-200', '201-500', '500+']).optional(),
  phone: z.string().max(40).optional().or(z.literal('')),
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
    const company = sanitizeInput(validatedData.company);
    const phone = validatedData.phone ? sanitizeInput(validatedData.phone) : undefined;
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
        role: validatedData.role,
        employees: validatedData.employees,
        phone,
        plan: validatedData.plan,
        module: validatedData.module,
        requestedWorkflow: 'guided_trial',
        nextStep: 'sales_or_platform_admin_provisioning',
        passwordCaptured: false,
      },
    });

    return NextResponse.json(
      {
        success: true,
        message: "Demande d'essai recue. Notre equipe vous contacte sous 24h ouvrables avec l'acces le plus adapte.",
        data: {
          id: lead.id,
          email,
          company,
          nextStep: 'contact_under_24h',
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
