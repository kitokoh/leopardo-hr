import { NextRequest, NextResponse } from 'next/server';
import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { z } from 'zod';
import { areFormsEnabled, captureMarketingLead, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
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

    const validatedData = signupSchema.parse(await request.json());
    const email = sanitizeEmail(validatedData.email);
    const company = sanitizeInput(validatedData.company);
    const phone = validatedData.phone ? sanitizeInput(validatedData.phone) : undefined;

    // Issue #6680 : le champ `country` est OBLIGATOIRE côté backend (#1867 —
    // plus de fallback silencieux DZ). Le formulaire rapide du hero ne le
    // collecte pas → détection géo côté serveur (Vercel `request.geo`), sinon
    // la demande sera rejetée en 422 et le prospect verra une erreur honnête
    // (jamais un faux succès).
    //
    // `geo` n'est pas typé sur NextRequest (augmentation Vercel runtime) —
    // accès typé pour satisfaire ESLint/TS sans `any`.
    const geo = (request as unknown as { geo?: { country?: string } }).geo;
    const geoCountry = geo?.country?.toUpperCase() ?? '';
    const effectiveCountry = validatedData.country || geoCountry || undefined;

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
        requestedWorkflow: 'guided_trial',
        passwordCaptured: false,
      },
    });

    // Step 2: Call the backend to initiate OTP verification
    let signupResult = null;
    let signupError = null;
    let signupValidationDetails: unknown = null;

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
          country: effectiveCountry,
          phone,
          plan: validatedData.plan,
          source: validatedData.source || 'signup_form',
          requestedWorkflow: 'guided_trial',
        }),
        signal: AbortSignal.timeout(15000),
      });

      const trialData = await trialResponse.json();

      if (trialResponse.ok && trialData.success) {
        signupResult = trialData.data;
      } else {
        // Anti-énumération (#3945) : /trial/signup renvoie désormais une
        // réponse uniforme — la détection « email déjà enregistré » se fait à
        // l'étape verify (OTP), qui remonte EMAIL_ALREADY_REGISTERED (409).
        signupError = trialData.error || 'SIGNUP_FAILED';
        // Issue #6680 : conserver les détails de validation (ex. country
        // requis) pour une réponse d'erreur exploitable côté client.
        if (trialData.error === 'VALIDATION_ERROR' && trialData.errors) {
          signupValidationDetails = trialData.errors;
        }
      }
    } catch (error) {
      signupError = error instanceof Error ? error.name : 'NETWORK_ERROR';
      console.error(
        JSON.stringify({
          event: 'marketing.signup.otp_send_failed',
          service: 'leopardo-web',
          email,
          error: signupError,
        })
      );
    }

    // Step 3: Return response
    if (signupResult) {
      // #6959 : contrat honnête selon le workflow réellement exécuté par le
      // backend. Le flux guidé (`requestedWorkflow=guided_trial`) ne passe
      // PAS par un OTP : le backend renvoie `status=provisioning_sandbox` +
      // `provisioning_token` et provisionne en asynchrone. On ne doit donc
      // jamais répondre « Code de vérification envoyé » pour ce flux —
      // `provisioned:false` + `nextStep:'tracking'` orientent l'UI vers le
      // suivi du statut. Seul le flux legacy self-service (statut
      // `pending_verification`) reçoit réellement un code par email.
      const otpFlow = signupResult.status === 'pending_verification';

      return NextResponse.json(
        {
          success: true,
          provisioned: otpFlow,
          // #6959 : aucun nouveau littéral affiché côté client — l'UI choisit
          // ses textes localisés (catalogue i18n vitrine) selon `nextStep`
          // (`tracking` = suivi du provisioning, `verify` = OTP). Le message
          // ci-dessous n'est conservé que pour la compatibilité du flux OTP.
          message: otpFlow ? 'Code de vérification envoyé.' : undefined,
          data: {
            id: lead.id,
            email: signupResult.email,
            status: signupResult.status,
            nextStep: otpFlow ? 'verify' : 'tracking',
            // #2469 : le provisioning_token permet au prospect de suivre
            // l'état du sandbox (GET /api/forms/trial-status) sans email OTP.
            provisioning_token:
              typeof signupResult.provisioning_token === 'string'
                ? signupResult.provisioning_token
                : undefined,
            confirmationSent: lead.emailForwarded,
            crmForwarded: lead.crmForwarded,

          },
        },
        { status: 200 }
      );
    } else {
      // Issue #6680 : ne JAMAIS renvoyer success:true quand le backend a
      // rejeté la demande de trial (ex. 422 country manquant, hors détection
      // géo) — le prospect croirait son essai lancé alors que rien n'est
      // provisionné. Une erreur de validation remonte telle quelle (avec
      // redirection vers le formulaire complet) ; le fallback marketing
      // « contact sous 24h » ne s'applique qu'aux pannes réseau/backlog
      // (OTP/back indisponible), pas aux rejets de contrat.
      const backendError = signupError || 'SIGNUP_FAILED';

      if (backendError === 'VALIDATION_ERROR' || backendError === 'SIGNUP_FAILED') {
        return NextResponse.json(
          {
            success: false,
            message:
              "Impossible de lancer l'essai automatique (pays requis). Utilisez le formulaire complet pour choisir votre pays.",
            error: backendError,
            data: {
              id: lead.id,
              email,
              company,
              nextStep: 'complete_signup',
              confirmationSent: lead.emailForwarded,
              crmForwarded: lead.crmForwarded,
            },
            ...(signupValidationDetails ? { details: signupValidationDetails } : {}),
          },
          { status: 422 }
        );
      }

      // FALLBACK (panne réseau/back uniquement) : lead recue, contact sous 24h.
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
            signupError,
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
