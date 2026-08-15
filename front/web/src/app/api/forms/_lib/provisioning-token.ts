/** Laravel Str::random(64) uses a case-sensitive base62 alphabet. */
export const PROVISIONING_TOKEN_LENGTH = 64;

export function isValidProvisioningToken(token: string): boolean {
  return new RegExp(`^[A-Za-z0-9]{${PROVISIONING_TOKEN_LENGTH}}$`).test(token);
}
