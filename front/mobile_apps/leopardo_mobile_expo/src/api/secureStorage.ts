import * as SecureStore from 'expo-secure-store';

const TOKEN_KEY = 'leopardo.auth.token';
const EMPLOYEE_KEY = 'leopardo.auth.employee';

/**
 * Stockage securise du jeton Sanctum et du profil employe.
 *
 * Le jeton est ecrit dans le Keychain (iOS) / Keystore (Android) via
 * expo-secure-store : jamais dans AsyncStorage, jamais en clair sur disque.
 */
export const secureStorage = {
  async getToken(): Promise<string | null> {
    try {
      return await SecureStore.getItemAsync(TOKEN_KEY);
    } catch {
      return null;
    }
  },

  async setToken(token: string): Promise<void> {
    await SecureStore.setItemAsync(TOKEN_KEY, token, {
      keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    });
  },

  async clearToken(): Promise<void> {
    try {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
    } catch {
      // Un echec de suppression ne doit jamais bloquer la deconnexion.
    }
  },

  async getEmployee(): Promise<string | null> {
    try {
      return await SecureStore.getItemAsync(EMPLOYEE_KEY);
    } catch {
      return null;
    }
  },

  async setEmployee(json: string): Promise<void> {
    await SecureStore.setItemAsync(EMPLOYEE_KEY, json);
  },

  async clearEmployee(): Promise<void> {
    try {
      await SecureStore.deleteItemAsync(EMPLOYEE_KEY);
    } catch {
      // idem
    }
  },

  async clearAll(): Promise<void> {
    await Promise.all([this.clearToken(), this.clearEmployee()]);
  },
};
