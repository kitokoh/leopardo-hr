<?php

declare(strict_types=1);

namespace App\Core\Mail;

use Illuminate\Support\Facades\DB;

/**
 * #7347 — accès à `public.email_templates` (surcharges éditables depuis l'admin).
 *
 * Le tableau est lu/écrit sans modèle Eloquent, comme les autres tables du
 * cockpit plateforme (`platform_oauth_configs`). Toute erreur (migration non
 * jouée sur un environnement partiel) est absorbée en LECTURE : l'application
 * retombe alors sur les valeurs par défaut du catalogue — un e-mail ne doit
 * jamais cesser de partir parce qu'une table de surcharge est absente.
 */
final class EmailTemplateRepository
{
    private const TABLE = 'email_templates';

    public function find(string $key, string $locale): ?object
    {
        try {
            return DB::table(self::TABLE)
                ->where('template_key', $key)
                ->where('locale', $locale)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, object> clé « template_key:locale » => ligne
     */
    public function all(): array
    {
        try {
            $rows = DB::table(self::TABLE)->get();
        } catch (\Throwable) {
            return [];
        }

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row->template_key.':'.$row->locale] = $row;
        }

        return $indexed;
    }

    /**
     * Crée ou met à jour la surcharge. Les champs absents ne sont PAS écrasés
     * (une mise à jour partielle est donc possible).
     *
     * @param  array<string, string|null>  $fields
     */
    public function upsert(string $key, string $locale, array $fields, ?string $updatedBy): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['template_key' => $key, 'locale' => $locale],
            array_merge($fields, [
                'updated_by' => $updatedBy,
                'updated_at' => now(),
            ])
        );
    }

    /** Retour au défaut : supprime la surcharge de cette locale. */
    public function reset(string $key, string $locale): int
    {
        return DB::table(self::TABLE)
            ->where('template_key', $key)
            ->where('locale', $locale)
            ->delete();
    }
}
