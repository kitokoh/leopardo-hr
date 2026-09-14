<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * #7384 — lecture/écriture des réglages de l'assistant IA (table
 * `public.platform_ai_settings`).
 *
 * Sécurité : les valeurs marquées secrètes par le catalogue sont chiffrées au
 * repos (`Crypt`) et ne sortent jamais en clair vers l'API — `all()` ne renvoie
 * qu'un booléen « configurée » ; seul `resolvableValues()` (usage interne, pour
 * configurer le client fournisseur) déchiffre.
 *
 * Fail-safe : table absente (environnement partiel, migration pas encore jouée)
 * ⇒ aucun réglage, donc repli sur les valeurs d'environnement. Aucune exception
 * ne remonte, en particulier pas au boot.
 */
final class PlatformAiSettingsRepository
{
    private const TABLE = 'platform_ai_settings';

    /**
     * Cache par requête : une seule lecture pour tout un boot.
     *
     * @var array<string, object>|null
     */
    private ?array $cache = null;

    /**
     * Vue « API » : valeurs publiques + indicateurs, **jamais de secret**.
     *
     * @return array<string, array{value: string|null, is_secret: bool, has_value: bool, updated_at: string|null, updated_by: string|null}>
     */
    public function all(): array
    {
        $rows = $this->rows();
        $result = [];

        foreach (PlatformAiSettingsCatalog::all() as $key => $definition) {
            $row = $rows[$key] ?? null;
            $isSecret = $definition['secret'];
            $publicValue = $row->setting_value ?? null;
            $encrypted = $row->encrypted_value ?? null;

            $result[$key] = [
                'value' => $isSecret ? null : (is_string($publicValue) ? $publicValue : null),
                'is_secret' => $isSecret,
                'has_value' => $isSecret
                    ? (is_string($encrypted) && $encrypted !== '')
                    : (is_string($publicValue) && $publicValue !== ''),
                'updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
                'updated_by' => isset($row->updated_by) ? (string) $row->updated_by : null,
            ];
        }

        return $result;
    }

    /**
     * Valeurs exploitables pour surcharger la config (secrets déchiffrés).
     * Usage interne uniquement — ne jamais exposer le retour par l'API.
     *
     * @return array<string, string>
     */
    public function resolvableValues(): array
    {
        $values = [];

        foreach ($this->rows() as $key => $row) {
            $definition = PlatformAiSettingsCatalog::find($key);
            if ($definition === null) {
                continue;
            }

            if ($definition['secret']) {
                $encrypted = $row->encrypted_value ?? null;
                if (! is_string($encrypted) || $encrypted === '') {
                    continue;
                }

                try {
                    $values[$key] = Crypt::decryptString($encrypted);
                } catch (\Throwable $exception) {
                    // Clé applicative changée / donnée corrompue : on ignore la
                    // surcharge (repli env) plutôt que de casser le démarrage.
                    Log::warning('platform_ai_settings.secret_undecryptable', [
                        'setting_key' => $key,
                        'error' => $exception->getMessage(),
                    ]);
                }

                continue;
            }

            $publicValue = $row->setting_value ?? null;
            if (is_string($publicValue)) {
                $values[$key] = $publicValue;
            }
        }

        return $values;
    }

    /**
     * Écrit un réglage.
     *
     * `null`/`''` sur un réglage SECRET ⇒ on CONSERVE le secret existant : un
     * formulaire ne doit jamais réécrire (ni effacer) une clé par inadvertance.
     * `null` sur un réglage public ⇒ on efface la surcharge (retour à l'env).
     */
    public function put(string $key, ?string $value, ?string $updatedBy): bool
    {
        $definition = PlatformAiSettingsCatalog::find($key);
        if ($definition === null) {
            return false;
        }

        if ($definition['secret'] && ($value === null || $value === '')) {
            // Rien de neuf : on ne touche pas au secret déjà enregistré.
            return true;
        }

        $attributes = ['updated_by' => $updatedBy, 'updated_at' => now()];

        if ($definition['secret']) {
            $attributes['encrypted_value'] = Crypt::encryptString((string) $value);
            $attributes['is_secret'] = true;
        } else {
            $attributes['setting_value'] = $value;
            $attributes['is_secret'] = false;
        }

        try {
            DB::table(self::TABLE)->updateOrInsert(['setting_key' => $key], $attributes);
        } catch (\Throwable $exception) {
            Log::error('platform_ai_settings.write_failed', [
                'setting_key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->cache = null;

        return true;
    }

    /**
     * Supprime une surcharge → retour à la valeur d'environnement.
     */
    public function forget(string $key): int
    {
        $this->cache = null;

        try {
            return (int) DB::table(self::TABLE)->where('setting_key', $key)->delete();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, object>
     */
    private function rows(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $rows = DB::table(self::TABLE)->get();
        } catch (\Throwable) {
            // Table absente → aucun réglage (repli env), jamais d'exception.
            $this->cache = [];

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $asArray = (array) $row;
            $key = $asArray['setting_key'] ?? null;
            if (is_string($key)) {
                $map[$key] = (object) $asArray;
            }
        }

        $this->cache = $map;

        return $map;
    }
}
