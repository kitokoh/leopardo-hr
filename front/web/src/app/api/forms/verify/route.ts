import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { z } from 'zod';
import { RateLimiter, sanitizeEmail } from '@/modules/vitrine/lib/validation';
import { areFormsEnabled, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';

/**
 * Cookie de session — mêmes nom/attributs que `app/api/v1/auth/login/route.ts`
 * pour que le proxy du dashboard (`leopardo_token`, cf. `proxy.ts`)
 * reconnaisse immédiatement la session après la vérification du code.
 */
const COOKIE_NAME = 'leopardo_token';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours (aligné sur Sanctum)

const rateLimiter = new RateLimiter(10, 15 * 60 * 1000);

const verifySchema = z.object({
  email: z.string().email().max(255),
  code: z.string().length(6),
});

const LEOPARDO_API_URL = process.env.LEOPARDO_API_URL ||
  resolveBackendBaseUrl().replace(/\/api\/v1$/, '');

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

    // Auto-connexion : le backend émet un jeton de session après vérification
    // du code (l'utilisateur n'a jamais choisi de mot de passe). On le pose en
    // cookie httpOnly — jamais exposé au JavaScript de la page — exactement
    // comme le fait /api/v1/auth/login, puis on le retire de la réponse JSON.
    const data = (trialData.data ?? {}) as Record<string, unknown>;
    const token = typeof data.token === 'string' && data.token.length > 0 ? data.token : null;

    if (token) {
      const isSecure =
        request.nextUrl.protocol === 'https:' ||
        process.env.NODE_ENV === 'production';
      const cookieStore = await cookies();
      cookieStore.set(COOKIE_NAME, token, {
        httpOnly: true,
        secure: isSecure,
        sameSite: 'strict',
        maxAge: COOKIE_MAX_AGE,
        path: '/',
      });
    }

    const { token: _stripped, ...safeData } = data;

    return NextResponse.json(
      {
        success: true,
        message: trialData.message || 'Votre espace Leopardo est pret !',
        // `sessionEstablished` indique au formulaire s'il peut rediriger
        // directement vers le dashboard ou s'il doit proposer la connexion.
        data: { ...safeData, sessionEstablished: token !== null },
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
        message: 'Erreur lors de la vérification',
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
