import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { RateLimiter, sanitizeEmail } from '@/modules/vitrine/lib/validation';
import { getClientIp } from '../_lib/lead-capture';

const rateLimiter = new RateLimiter(10, 15 * 60 * 1000);

const verifySchema = z.object({
  email: z.string().email().max(255),
  code: z.string().length(6),
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

    const body = await request.json();
    const validatedData = verifySchema.parse(body);
    const email = sanitizeEmail(validatedData.email);

    const trialResponse = await fetch(`${LEOPARDO_API_URL}/api/v1/trial/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        email,
        code: validatedData.code,
      }),
      signal: AbortSignal.timeout(30000), // 30s timeout for provisioning after verify
    });

    const trialData = await trialResponse.json();

    if (!trialResponse.ok || !trialData.success) {
      return NextResponse.json(
        {
          success: false,
          message: trialData.message || 'Code invalide ou expire.',
          error: trialData.error || 'VERIFICATION_FAILED',
        },
        { status: trialResponse.status }
      );
    }

    return NextResponse.json(
      {
        success: true,
        message: trialData.message || 'Votre espace Leopardo est pret !',
        data: trialData.data,
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
        message: 'Erreur lors de la verification',
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
