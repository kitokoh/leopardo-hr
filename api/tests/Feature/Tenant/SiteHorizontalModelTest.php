<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Tenant\Domain\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8722 — extension du modèle horizontal `Site`.
 *
 * Trois familles de garanties :
 *  - **compatibilité** : un établissement historique (adresse texte, pas de
 *    coordonnée) reste lisible à l'identique — l'extension n'est pas une
 *    réécriture ;
 *  - **validation** : provenance GPS, bornes, horaires ;
 *  - **frontière** : les horaires d'ouverture ne touchent pas les horaires RH.
 */
final class SiteHorizontalModelTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeSite(array $attributes = []): Site
    {
        $site = new Site;
        $site->fill(array_merge(['name' => 'Leopardo Lyon'], $attributes));
        $site->save();

        return $site;
    }

    /**
     * Exécute une écriture qui DOIT être refusée par la base, sans empoisonner
     * la transaction du test.
     *
     * Pourquoi un savepoint : `RefreshDatabase` enveloppe chaque test dans une
     * transaction. Sous PostgreSQL, une violation de contrainte avorte la
     * transaction COURANTE ; sans savepoint, le `SET search_path` du setUp
     * suivant échoue en 25P02 (« current transaction is aborted ») et fait
     * tomber les tests SUIVANTS du fichier, pas seulement celui-ci.
     * `DB::transaction()` imbriqué crée un SAVEPOINT : on revient juste avant
     * l'écriture fautive et la transaction du test reste utilisable.
     *
     * On vérifie en plus le NOM de la contrainte : sans cela, n'importe quelle
     * erreur SQL (colonne absente, typo) ferait « passer » le test.
     */
    private function assertRejectedByDatabase(callable $statement, string $constraint): void
    {
        $rejected = null;

        try {
            DB::transaction(static fn () => $statement());
        } catch (\Illuminate\Database\QueryException $e) {
            $rejected = $e;
        }

        self::assertNotNull($rejected, "l'écriture aurait dû être refusée par {$constraint}");
        self::assertStringContainsString(
            $constraint,
            $rejected->getMessage(),
            "refusée, mais par une autre contrainte que {$constraint}",
        );
    }

    public function test_migration_adds_columns_to_a_historical_table(): void
    {
        foreach ([
            'country_code',
            'address_line1',
            'locality',
            'postal_code',
            'gps_source',
            'public_phone',
            'media_ids',
            'opening_hours',
            'opening_hours_exceptions',
        ] as $column) {
            self::assertTrue(
                Schema::hasColumn('sites', $column),
                "colonne sites.{$column} absente",
            );
        }
    }

    public function test_historical_site_with_free_text_address_still_reads(): void
    {
        $site = $this->makeSite(['address' => '12 rue de la Paix, 69002 Lyon']);

        // Pas de backfill : le texte libre reste la seule source, et il est
        // rendu tel quel. Aucun écran existant ne casse.
        self::assertSame('12 rue de la Paix, 69002 Lyon', $site->displayAddress());
        self::assertFalse($site->hasStructuredAddress());
        self::assertFalse($site->hasCoordinates());
    }

    public function test_structured_address_wins_and_is_joined_readably(): void
    {
        $site = $this->makeSite([
            'address' => 'ancienne adresse libre',
            'country_code' => 'FR',
            'address_line1' => '12 rue de la Paix',
            'postal_code' => '69002',
            'locality' => 'Lyon',
        ]);

        self::assertTrue($site->hasStructuredAddress());
        self::assertSame('12 rue de la Paix, 69002, Lyon, FR', $site->displayAddress());
    }

    public function test_postal_code_is_not_required_for_every_market(): void
    {
        // Marchés sans code postal (CEMAC, une partie du Maghreb) : l'adresse
        // structurée doit rester valide sans lui.
        $site = $this->makeSite([
            'country_code' => 'CM',
            'address_line1' => 'Quartier Bastos',
            'locality' => 'Yaoundé',
        ]);

        self::assertTrue($site->hasStructuredAddress());
        self::assertSame('Quartier Bastos, Yaoundé, CM', $site->displayAddress());
    }

    public function test_manual_coordinates_are_a_valid_state(): void
    {
        $site = $this->makeSite();
        $site->markCoordinatesManual(45.7640, 4.8357);
        $site->save();

        $fresh = $site->fresh();

        self::assertTrue($fresh->hasCoordinates());
        // Le géocodage n'est pas une précondition : « manual » est un état
        // normal, pas un brouillon.
        self::assertSame(Site::GPS_SOURCE_MANUAL, $fresh->gps_source);
        self::assertNotNull($fresh->gps_updated_at);
        self::assertNull($fresh->gps_verified_at);
    }

    public function test_gps_source_is_not_mass_assignable(): void
    {
        $site = new Site;
        $site->fill([
            'name' => 'X',
            'gps_source' => Site::GPS_SOURCE_VERIFIED,
            'gps_verified_at' => Carbon::now(),
        ]);

        // Un PATCH de masse ne peut pas se déclarer « verified » : la
        // provenance se pose par `markCoordinatesVerified()`.
        self::assertNull($site->gps_source);
    }

    public function test_invalid_gps_source_is_rejected_by_the_database(): void
    {
        $site = $this->makeSite();

        // L'allowlist du domaine est doublée d'un CHECK : une valeur inventée
        // n'entre pas en base même par un UPDATE qui contourne le modèle.
        $this->assertRejectedByDatabase(
            fn () => DB::table('sites')->where('id', $site->id)->update(['gps_source' => 'invented']),
            'sites_gps_source_allowed',
        );
    }

    public function test_verified_coordinates_require_a_date(): void
    {
        $site = $this->makeSite();

        // Contrainte sites_gps_verified_requires_date : « verified » sans
        // preuve datée est refusé même en SQL direct.
        $this->assertRejectedByDatabase(
            fn () => DB::table('sites')->where('id', $site->id)->update(['gps_source' => 'verified']),
            'sites_gps_verified_requires_date',
        );
    }

    public function test_coordinates_outside_bounds_are_rejected(): void
    {
        $site = $this->makeSite();

        // `decimal(10,8)` accepterait physiquement 91 : c'est le CHECK qui
        // protège les cartes en aval d'une coordonnée absurde.
        $this->assertRejectedByDatabase(
            fn () => DB::table('sites')->where('id', $site->id)->update(['gps_lat' => 91]),
            'sites_gps_bounds',
        );
    }

    public function test_opening_state_is_unknown_when_hours_are_absent(): void
    {
        $site = $this->makeSite();

        // « Horaires non renseignés » n'est PAS « fermé » : on ne prétend pas
        // savoir ce qu'on ignore.
        self::assertSame('unknown', $site->openingStateAt(Carbon::parse('2026-10-26 10:00:00')));
    }

    public function test_opening_state_open_closed_and_empty_day(): void
    {
        $site = $this->makeSite([
            'timezone' => 'Europe/Paris',
            'opening_hours' => [
                // Lundi : 09:00-12:00 et 14:00-18:00 (pause de midi).
                '1' => [
                    ['opens' => '09:00', 'closes' => '12:00'],
                    ['opens' => '14:00', 'closes' => '18:00'],
                ],
                // Dimanche : liste vide = fermé explicitement.
                '7' => [],
            ],
        ]);

        // Les instants sont construits DANS le fuseau de l'établissement :
        // sinon l'heure murale testée dépend du fuseau par défaut de
        // l'application (UTC en test) et une heure censée être « dans la pause »
        // tombe en réalité dans l'après-midi d'ouverture.
        $paris = static fn (string $time): \Illuminate\Support\Carbon => Carbon::parse($time, 'Europe/Paris');

        $monday = '2026-10-26'; // lundi
        self::assertSame('open', $site->openingStateAt($paris($monday.' 10:30:00')));
        self::assertSame('open', $site->openingStateAt($paris($monday.' 15:00:00')));
        // Dans la pause de midi : fermé, et c'est une information différente
        // d'« horaires inconnus ».
        self::assertSame('closed', $site->openingStateAt($paris($monday.' 13:00:00')));

        self::assertSame('closed', $site->openingStateAt($paris('2026-11-01 10:30:00')));
    }

    public function test_opening_hours_validation_reports_every_problem(): void
    {
        $errors = Site::validateOpeningHours([
            '9' => [['opens' => '09:00', 'closes' => '12:00']],
            '1' => [['opens' => '22:00', 'closes' => '02:00']],
            '2' => [['opens' => '9:00', 'closes' => '18:00']],
        ]);

        // Un import doit rapporter TOUTES les lignes fautives, pas la première.
        self::assertCount(3, $errors);
        self::assertStringContainsString('jour ISO invalide', $errors[0]);
        self::assertStringContainsString('doit précéder closes', $errors[1]);
        self::assertStringContainsString("'opens' doit être HH:MM", $errors[2]);
    }

    public function test_valid_opening_hours_produce_no_error(): void
    {
        self::assertSame([], Site::validateOpeningHours([
            '1' => [['opens' => '09:00', 'closes' => '12:00'], ['opens' => '14:00', 'closes' => '18:00']],
            '7' => [],
        ]));
    }

    public function test_opening_hours_do_not_touch_hr_schedules(): void
    {
        $site = $this->makeSite([
            'opening_hours' => ['1' => [['opens' => '09:00', 'closes' => '18:00']]],
        ]);

        // Frontière explicite : les horaires d'ouverture vivent sur `sites`.
        // `schedules` porte les horaires de travail RH et ne doit recevoir
        // aucune ligne du fait de cette extension.
        // `schedules` est une table SOCIÉTÉ : elle n'a pas de `site_id`. La
        // frontière à prouver est donc qu'aucune ligne n'apparaît du fait de
        // l'extension — une assertion filtrée par `site_id` échouerait sur une
        // colonne inexistante et ne prouverait rien.
        if (! Schema::hasTable('schedules')) {
            self::markTestSkipped('table schedules absente de ce schéma de test');
        }

        self::assertSame(
            0,
            DB::table('schedules')->count(),
            "opening_hours ne doit créer aucune ligne d'horaire RH",
        );

        self::assertIsArray($site->fresh()->opening_hours);
    }

    public function test_media_ids_are_references_not_storage_keys(): void
    {
        $site = $this->makeSite(['media_ids' => ['media_42', 'media_43']]);

        // On stocke des identifiants, jamais une clé de stockage ni une URL
        // non contrôlée : un DTO public ne doit pas pouvoir exposer un bucket.
        self::assertSame(['media_42', 'media_43'], $site->fresh()->media_ids);
    }
}
