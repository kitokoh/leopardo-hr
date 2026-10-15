/**
 * #8499 (I-12) — enrolement securise des appareils : NOYAU.
 *
 * Exigences de la mission (Partie 6) que ce module rend VERIFIABLES :
 *  - code d'appairage a USAGE UNIQUE et EXPIRANT ;
 *  - affectation a un etablissement et a un poste/fonction ;
 *  - revocation d'appareil ;
 *  - l'interface ne doit JAMAIS exposer de secret/jeton REUTILISABLE.
 *
 * Le code d'appairage est la seule creance echangee : court, a usage unique,
 * expirant. Il n'est PAS un jeton d'API reutilisable — une fois consomme, il
 * ne vaut plus rien. Ce module est PUR (RNG injectable) donc testable en Node.
 *
 * Portee honnete : ceci est le NOYAU (generation/validation/consommation/
 * revocation). Le branchement backend (routes tenant `edge/nodes/*`) et
 * l'ecran d'enrolement restent a faire -> #8499 maintenue ouverte.
 */

export interface PairingCode {
  code: string;
  createdAt: number;
  expiresAt: number;
  usedAt?: number;
}

export interface DeviceRecord {
  id: string;
  establishmentId: string;
  /** Poste / fonction auquel l'appareil est affecte (ex. 'pos', 'kds'). */
  role: string;
  status: 'active' | 'revoked';
  pairedAt: number;
}

/** Alphabet sans caracteres ambigus (pas de 0/O, 1/I/L). */
const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const CODE_LENGTH = 8;

/**
 * Genere un code d'appairage a usage unique, valable `ttlMs`.
 * `rng` est injectable pour des tests deterministes.
 */
export function generatePairingCode(
  now: number,
  ttlMs: number,
  rng: () => number = Math.random,
): PairingCode {
  let code = '';
  for (let i = 0; i < CODE_LENGTH; i += 1) {
    code += ALPHABET[Math.floor(rng() * ALPHABET.length)];
  }
  return { code, createdAt: now, expiresAt: now + ttlMs };
}

/** Un code est utilisable s'il n'a jamais ete consomme ET n'est pas expire. */
export function isPairingCodeUsable(record: PairingCode, now: number): boolean {
  return record.usedAt === undefined && now < record.expiresAt;
}

/**
 * Consomme un code (usage unique). Refuse un code expire ou deja consomme —
 * fail-closed : jamais de reutilisation silencieuse.
 */
export function consumePairingCode(record: PairingCode, now: number): PairingCode {
  if (!isPairingCodeUsable(record, now)) {
    throw new Error('pairing_code_not_usable');
  }
  return { ...record, usedAt: now };
}

/** Cree l'appareil appaire (affecte a un etablissement et a un poste). */
export function pairDevice(
  id: string,
  establishmentId: string,
  role: string,
  now: number,
): DeviceRecord {
  return { id, establishmentId, role, status: 'active', pairedAt: now };
}

/** Revoque un appareil : il ne peut plus synchroniser. */
export function revokeDevice(device: DeviceRecord): DeviceRecord {
  return { ...device, status: 'revoked' };
}

/** Un appareil revoque ne doit jamais etre considere comme actif. */
export function isDeviceActive(device: DeviceRecord): boolean {
  return device.status === 'active';
}
