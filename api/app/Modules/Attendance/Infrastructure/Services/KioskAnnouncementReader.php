<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * #7962 (suite de #8944) — lecture des annonces kiosque.
 *
 * Extrait de `KioskController::doAnnouncements()`. La lecture vit dans
 * Infrastructure parce que `check-layer-purity` (#6568) interdit les facades
 * Laravel (`DB`, `Schema`, `Log`) dans Application — et cette lecture en a
 * besoin : elle sonde les colonnes réellement présentes.
 *
 * ## Pourquoi on sonde les colonnes au lieu de supposer le schéma
 *
 * `kiosk_announcements` a été ajoutée par un lot vertical qui n'est pas
 * déployé sur tous les environnements (`is_active`, `starts_at`, `expires_at`,
 * `priority` sont apparues dans des migrations successives). Un `SELECT` qui
 * cite une colonne absente lève une `QueryException` : l'écran kiosque
 * afficherait alors une erreur au lieu du roster. On lit donc la liste réelle
 * des colonnes et on ne filtre que sur celles qui existent.
 *
 * ## Pourquoi une table absente n'est pas une erreur
 *
 * Un tenant sans le module d'affichage n'a pas la table. La politique est
 * explicite : « pas d'annonces » est un état normal, pas une panne — d'où la
 * liste vide. Une table PRÉSENTE mais illisible (`Throwable`) est en revanche
 * tracée : c'est un incident, et le rendre silencieux le rendrait invisible.
 */
final class KioskAnnouncementReader
{
    /** Plafond de sécurité : un écran kiosque n'affiche pas 40 annonces. */
    private const MAX_ANNOUNCEMENTS = 10;

    /**
     * @return list<array<string, mixed>>
     */
    public function forCompany(Company $company): array
    {
        if (! Schema::hasTable('kiosk_announcements')) {
            return [];
        }

        try {
            $columns = Schema::getColumnListing('kiosk_announcements');

            if (! in_array('company_id', $columns, true)) {
                // Table sans axe tenant : elle ne peut pas être filtrée, donc
                // elle ne peut pas être servie sur une surface multi-tenant.
                return [];
            }

            $hasColumn = static fn (string $column): bool => in_array($column, $columns, true);

            $rows = DB::table('kiosk_announcements')
                ->where('company_id', $company->id)
                ->when($hasColumn('is_active'), static fn ($query) => $query->where('is_active', true))
                ->when($hasColumn('starts_at'), static function ($query): void {
                    $query->where(static function ($windowQuery): void {
                        $windowQuery->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                    });
                })
                ->when($hasColumn('expires_at'), static function ($query): void {
                    $query->where(static function ($windowQuery): void {
                        $windowQuery->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    });
                })
                ->when($hasColumn('priority'), static fn ($query) => $query->orderByDesc('priority'))
                ->when(
                    $hasColumn('created_at'),
                    static fn ($query) => $query->orderByDesc('created_at'),
                    static fn ($query) => $hasColumn('id') ? $query->orderByDesc('id') : $query
                )
                ->limit(self::MAX_ANNOUNCEMENTS)
                ->get();

            $announcements = [];
            foreach ($rows as $row) {
                $announcements[] = [
                    'id' => $row->id ?? null,
                    'title' => $row->title ?? '',
                    'body' => $row->body ?? '',
                    'priority' => $row->priority ?? 'normal',
                    'starts_at' => self::nullableIsoString($row->starts_at ?? null),
                    'expires_at' => self::nullableIsoString($row->expires_at ?? null),
                ];
            }

            return $announcements;
        } catch (Throwable $exception) {
            Log::warning('Kiosk announcements skipped because the tenant table is not queryable.', [
                'company_id' => $company->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Normalise une date SQL en ISO-8601, sans lever sur une valeur inattendue.
     *
     * `DB::table()` ne passe pas par les casts Eloquent : selon la version du
     * driver, la colonne revient en `string` brute plutôt qu'en `Carbon`. On ne
     * veut ni `TypeError` ni `null` trompeur pour une date présente.
     */
    private static function nullableIsoString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
