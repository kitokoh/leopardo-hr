import { api, unwrap } from './client';
import { secureStorage } from './secureStorage';
import type { Employee } from '../models/employee';

export type LoginResult =
  | { kind: 'authenticated'; employee: Employee }
  | { kind: 'mfa_required'; challengeToken: string };

/**
 * Connexion employe.
 *
 * Contrat API (POST /auth/login) :
 *   - succes  -> { ...employee, token, token_type, token_expires_at }
 *   - 2FA     -> { mfa_challenge: true, mfa_challenge_token, mfa_challenge_expires_in }
 */
export async function login(email: string, password: string): Promise<LoginResult> {
  const { data } = await api.post('/auth/login', {
    email: email.trim().toLowerCase(),
    password,
    device_name: 'Leopardo Mobile (Expo)',
  });

  if (data?.mfa_challenge === true) {
    return { kind: 'mfa_required', challengeToken: String(data.mfa_challenge_token) };
  }

  const token = data?.token as string | undefined;
  if (!token) {
    throw new Error("Reponse d'authentification invalide : jeton absent.");
  }
  await secureStorage.setToken(token);

  const employee = await fetchMe();
  return { kind: 'authenticated', employee };
}

/**
 * Verification du code TOTP apres un challenge 2FA.
 * POST /auth/2fa/verify
 */
export async function verifyTwoFactor(
  challengeToken: string,
  code: string,
): Promise<Employee> {
  const { data } = await api.post('/auth/2fa/verify', {
    challenge_token: challengeToken,
    code: code.trim(),
    device_name: 'Leopardo Mobile (Expo)',
  });

  const token = data?.token as string | undefined;
  if (!token) {
    throw new Error('Verification 2FA invalide : jeton absent.');
  }
  await secureStorage.setToken(token);
  return fetchMe();
}

/**
 * Profil de l'utilisateur connecte (source de verite des capacites).
 * GET /auth/me
 */
export async function fetchMe(): Promise<Employee> {
  const { data } = await api.get('/auth/me');
  const employee = unwrap<Employee>(data);
  await secureStorage.setEmployee(JSON.stringify(employee));
  return employee;
}

/**
 * Restaure la session au demarrage : jeton present + /auth/me valide.
 */
export async function restoreSession(): Promise<Employee | null> {
  const token = await secureStorage.getToken();
  if (!token) return null;
  try {
    return await fetchMe();
  } catch {
    await secureStorage.clearAll();
    return null;
  }
}

/**
 * Deconnexion : revoque le jeton cote serveur puis efface le stockage local.
 * POST /auth/logout
 */
export async function logout(): Promise<void> {
  try {
    await api.post('/auth/logout');
  } catch {
    // Une revocation serveur en echec ne doit pas empecher la deconnexion locale.
  } finally {
    await secureStorage.clearAll();
  }
}
