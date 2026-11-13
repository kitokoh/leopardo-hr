/**
 * Leopardo Toolbox — état d'outil partageable par lien.
 *
 * Un résultat n'est utile que s'il peut être transmis. Plutôt que d'encoder le
 * RÉSULTAT (qui dépend de la version du calcul et pourrait donc mentir si la
 * formule évolue), on encode les ENTRÉES : le lien rejoue le calcul avec la
 * formule courante. Un lien partagé ne peut donc jamais afficher un résultat
 * calculé par une version obsolète.
 *
 * Contrat :
 * - **paramètre unique** `?s=` (base64url d'un JSON `{ v, s }`) : un seul
 *   paramètre, donc aucune collision avec `?lang=` ni avec les paramètres de
 *   campagne (`utm_*`) que la vitrine utilise déjà ;
 * - **versionné** (`SHARE_VERSION`) : un lien d'une version antérieure est
 *   ignoré proprement au lieu d'être mal interprété ;
 * - **jamais de confiance** : tout ce qui vient de l'URL est validé (type,
 *   longueur, nombre d'entrées) et les clés inconnues sont écartées ;
 * - **aucune donnée personnelle** : seules les valeurs saisies transitent, et
 *   elles restent dans l'URL — rien n'est envoyé à un serveur.
 */

/** Nom du paramètre d'URL portant l'état partagé. */
export const SHARE_PARAM = 's';

/** Version du format d'encodage — à incrémenter si la forme du payload change. */
export const SHARE_VERSION = 1;

/** Nombre maximal d'entrées encodées (borne la longueur de l'URL). */
const MAX_ENTRIES = 24;

/** Longueur maximale d'une clé (identifiant de champ). */
const MAX_KEY_LENGTH = 32;

/** Longueur maximale d'une valeur (une saisie numérique reste courte). */
const MAX_VALUE_LENGTH = 64;

/** Longueur maximale du paramètre brut accepté (protection contre l'abus). */
const MAX_RAW_LENGTH = 2048;

/** État d'un outil : identifiant de champ → valeur saisie (chaîne brute). */
export type ToolStateValues = Record<string, string>;

interface SharePayload {
  v: number;
  s: ToolStateValues;
}

/**
 * Encode une chaîne en octets UTF-8.
 *
 * Implémentation manuelle volontaire : `TextEncoder` n'existe pas dans tous les
 * environnements d'exécution (jsdom des tests, anciens navigateurs), et le codec
 * doit produire exactement le même résultat partout — un lien partagé depuis un
 * navigateur doit se décoder dans un autre.
 */
function utf8Encode(input: string): number[] {
  const bytes: number[] = [];
  for (let index = 0; index < input.length; index += 1) {
    let code = input.charCodeAt(index);

    // Paire de substitution (hors BMP) → un seul point de code.
    if (code >= 0xd800 && code <= 0xdbff && index + 1 < input.length) {
      const next = input.charCodeAt(index + 1);
      if (next >= 0xdc00 && next <= 0xdfff) {
        code = 0x10000 + ((code - 0xd800) << 10) + (next - 0xdc00);
        index += 1;
      }
    }

    if (code < 0x80) {
      bytes.push(code);
    } else if (code < 0x800) {
      bytes.push(0xc0 | (code >> 6), 0x80 | (code & 0x3f));
    } else if (code < 0x10000) {
      bytes.push(0xe0 | (code >> 12), 0x80 | ((code >> 6) & 0x3f), 0x80 | (code & 0x3f));
    } else {
      bytes.push(
        0xf0 | (code >> 18),
        0x80 | ((code >> 12) & 0x3f),
        0x80 | ((code >> 6) & 0x3f),
        0x80 | (code & 0x3f),
      );
    }
  }
  return bytes;
}

/** Décode des octets UTF-8 en chaîne, ou `null` si la séquence est invalide. */
function utf8Decode(bytes: number[]): string | null {
  let output = '';
  let index = 0;

  while (index < bytes.length) {
    const first = bytes[index];

    if (first < 0x80) {
      output += String.fromCharCode(first);
      index += 1;
      continue;
    }

    let needed: number;
    let code: number;

    if ((first & 0xe0) === 0xc0) {
      needed = 1;
      code = first & 0x1f;
    } else if ((first & 0xf0) === 0xe0) {
      needed = 2;
      code = first & 0x0f;
    } else if ((first & 0xf8) === 0xf0) {
      needed = 3;
      code = first & 0x07;
    } else {
      return null;
    }

    if (index + needed >= bytes.length) return null;

    for (let offset = 1; offset <= needed; offset += 1) {
      const continuation = bytes[index + offset];
      if ((continuation & 0xc0) !== 0x80) return null;
      code = (code << 6) | (continuation & 0x3f);
    }

    index += needed + 1;

    if (code > 0x10ffff) return null;

    if (code > 0xffff) {
      const adjusted = code - 0x10000;
      output += String.fromCharCode(0xd800 + (adjusted >> 10), 0xdc00 + (adjusted & 0x3ff));
    } else {
      output += String.fromCharCode(code);
    }
  }

  return output;
}

/** Encode une chaîne UTF-8 en base64url (sans `=` de remplissage). */
function toBase64Url(input: string): string {
  const bytes = utf8Encode(input);
  let binary = '';
  for (let index = 0; index < bytes.length; index += 1) {
    binary += String.fromCharCode(bytes[index]);
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/** Décode une chaîne base64url en UTF-8, ou `null` si elle est invalide. */
function fromBase64Url(input: string): string | null {
  try {
    const base64 = input.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const binary = atob(base64 + padding);
    const bytes: number[] = [];
    for (let index = 0; index < binary.length; index += 1) {
      bytes.push(binary.charCodeAt(index));
    }
    return utf8Decode(bytes);
  } catch {
    return null;
  }
}

/** Vrai si la clé est un identifiant de champ acceptable. */
function isValidKey(key: unknown): key is string {
  return (
    typeof key === 'string' &&
    key.length > 0 &&
    key.length <= MAX_KEY_LENGTH &&
    /^[a-zA-Z0-9_-]+$/.test(key)
  );
}

/** Vrai si la valeur est une saisie acceptable (pas de caractère de contrôle). */
function isValidValue(value: unknown): value is string {
  return (
    typeof value === 'string' &&
    value.length <= MAX_VALUE_LENGTH &&
    !/[\u0000-\u001f\u007f]/.test(value)
  );
}

/**
 * Encode un état d'outil en paramètre d'URL.
 *
 * Retourne `null` quand il n'y a rien à encoder (état vide ou entièrement
 * invalide) : l'appelant supprime alors le paramètre au lieu d'écrire `?s=`.
 */
export function encodeToolState(values: ToolStateValues): string | null {
  const entries = Object.entries(values)
    .filter(([key, value]) => isValidKey(key) && isValidValue(value))
    .slice(0, MAX_ENTRIES);

  if (entries.length === 0) return null;

  const payload: SharePayload = { v: SHARE_VERSION, s: Object.fromEntries(entries) };
  return toBase64Url(JSON.stringify(payload));
}

/**
 * Décode un paramètre d'URL en état d'outil.
 *
 * Retourne `null` pour toute entrée non conforme (absente, trop longue, base64
 * invalide, JSON invalide, version inconnue, payload vide). L'appelant retombe
 * alors sur les valeurs par défaut de l'outil.
 */
export function decodeToolState(raw: string | null | undefined): ToolStateValues | null {
  if (typeof raw !== 'string' || raw === '' || raw.length > MAX_RAW_LENGTH) return null;

  const json = fromBase64Url(raw);
  if (json === null) return null;

  let parsed: unknown;
  try {
    parsed = JSON.parse(json);
  } catch {
    return null;
  }

  if (typeof parsed !== 'object' || parsed === null) return null;

  const payload = parsed as Partial<SharePayload>;
  if (payload.v !== SHARE_VERSION) return null;
  if (typeof payload.s !== 'object' || payload.s === null) return null;

  const decoded: ToolStateValues = {};
  let count = 0;
  for (const [key, value] of Object.entries(payload.s as Record<string, unknown>)) {
    if (count >= MAX_ENTRIES) break;
    if (!isValidKey(key) || !isValidValue(value)) continue;
    decoded[key] = value;
    count += 1;
  }

  return Object.keys(decoded).length > 0 ? decoded : null;
}

/**
 * Fusionne un état décodé avec les valeurs par défaut d'un outil.
 *
 * Seules les clés **connues de l'outil** sont reprises : un lien forgé ne peut
 * pas injecter un champ que le formulaire n'attend pas.
 */
export function mergeWithDefaults(
  decoded: ToolStateValues | null,
  defaults: ToolStateValues,
): ToolStateValues {
  const merged: ToolStateValues = { ...defaults };
  if (!decoded) return merged;

  for (const key of Object.keys(defaults)) {
    const value = decoded[key];
    if (typeof value === 'string' && isValidValue(value)) merged[key] = value;
  }

  return merged;
}

/**
 * Construit une URL absolue portant l'état donné.
 *
 * Les autres paramètres (`lang`, `utm_*`…) sont préservés : partager un lien
 * ne doit pas faire perdre la langue ni l'attribution d'une campagne.
 */
export function buildShareUrl(baseUrl: string, values: ToolStateValues): string {
  const url = new URL(baseUrl);
  const encoded = encodeToolState(values);

  if (encoded === null) {
    url.searchParams.delete(SHARE_PARAM);
  } else {
    url.searchParams.set(SHARE_PARAM, encoded);
  }

  return url.toString();
}
