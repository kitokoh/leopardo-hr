import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { captureMarketingLead, getClientIp } from '../_lib/lead-capture';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const signupSchema = z.object({
  email: z.string().email().max(255),
  company: z.string().min(2).max(120),
  first_name: z.string().max(80).optional().or(z.literal('')),
  last_name: z.string().max(80).optional().or(z.literal('')),
  role: z.enum(['founder', 'manager', 'hr', 'operations', 'other']).optional(),
  employees: z.enum(['1-10', '11-50', '51-200', '201-500', '500+']).optional(),
  phone: z.string().max(40).optional().or(z.literal('')),
  country: z.string().max(2).optional().or(z.literal('')),
  plan: z.string().max(80).optional(),
  module: z.string().max(80).optional(),
  locale: z.enum(['fr', 'en', 'ar', 'tr']).optional(),
  page: z.string().max(300).optional(),
  source: z.string().max(120).optional(),
  timestamp: z.string().optional(),
});

const LEOPARDO_API_URL = process.env.LEOPARDO_API_URL || 'https://gestionemployerbackend.onrender.com';

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

    // Step 1: Capture the marketing lead (CRM tracking)
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
        requestedWorkflow: 'self_service_trial',
        passwordCaptured: false,
      },
    });

    // Step 2: Call the backend to provision the trial tenant immediately
    let provisioningResult = null;
    let provisioningError = null;

    try {
      const trialResponse = await fetch(`${LEOPARDO_API_URL}/api/v1/trial/signup`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          email,
          company,
          first_name: validatedData.first_name || undefined,
          last_name: validatedData.last_name || undefined,
          role: validatedData.role,
          employees: validatedData.employees,
          country: validatedData.country || undefined,
          phone,
          plan: validatedData.plan,
          source: validatedData.source || 'signup_form',
        }),
        signal: AbortSignal.timeout(15000), // 15s timeout for provisioning
      });

      const trialData = await trialResponse.json();

      if (trialResponse.ok && trialData.success) {
        provisioningResult = trialData.data;
      } else {
        provisioningError = trialData.error || 'PROVISIONING_FAILED';
        // If email already registered, pass through the specific error
        if (trialData.error === 'EMAIL_ALREADY_REGISTERED') {
          return NextResponse.json(
            {
              success: false,
              message: trialData.message || 'Un compte avec cet email existe deja.',
              error: 'EMAIL_ALREADY_REGISTERED',
              data: {
                login_url: '/auth/login',
              },
            },
            { status: 409 }
          );
        }
      }
    } catch (error) {
      // Provisioning failed but we still captured the lead
      provisioningError = error instanceof Error ? error.name : 'NETWORK_ERROR';
      console.error(
        JSON.stringify({
          event: 'marketing.signup.provisioning_failed',
          service: 'leopardo-web',
          email,
          error: provisioningError,
        })
      );
    }

    // Step 3: Return response based on provisioning outcome
    if (provisioningResult) {
      // SUCCESS: Tenant created, return credentials
      return NextResponse.json(
        {
          success: true,
          provisioned: true,
          message: 'Votre espace Leopardo est pret !',
          data: {
            id: lead.id,
            email,
            company,
            ...provisioningResult,
            confirmationSent: lead.emailForwarded,
            crmForwarded: lead.crmForwarded,
          },
        },
        { status: 201 }
      );
    } else {
      // FALLBACK: Provisioning failed, fall back to guided trial
      return NextResponse.json(
        {
          success: true,
          provisioned: false,
          message:
            "Demande d'essai recue. Notre equipe vous contacte sous 24h ouvrables avec l'acces le plus adapte.",
          data: {
            id: lead.id,
            email,
            company,
            nextStep: 'contact_under_24h',
            confirmationSent: lead.emailForwarded,
            crmForwarded: lead.crmForwarded,
            provisioningError,
          },
        },
        { status: 201 }
      );
    }
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
