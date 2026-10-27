<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8862 — rattrapage des colonnes perdues par trois migrations no-op.
 *
 * TROIS fonctionnalites Restaurant sont livrees, utilisees par du code de
 * production, mais leurs colonnes **n'existent pas** en base :
 *
 *  - `restaurant_reservations.reminder_sent_at`  (#6213 / RESTO-608)
 *  - `restaurant_branches.cancel_free_hours`     (#6208 / RESTO-603)
 *  - `restaurant_branches.cancel_fee_bps`        (#6208 / RESTO-603)
 *  - `restaurant_loyalty_customers.opted_in_at`  (#6211 / RESTO-606)
 *
 * Cause : Laravel trie les migrations par NOM DE FICHIER. Les migrations qui
 * ajoutent ces colonnes portent une sequence *inferieure* a celle de la
 * migration qui CREE la table :
 *
 *   ..._000714_6208_...  (alter)  <  ..._001519_6166_...  (create)
 *   ..._000715_6211_...  (alter)  <  ..._001531_6177_...  (create)
 *   ..._000716_6213_...  (alter)  <  ..._001529_6175_...  (create)
 *
 * Chacune commence par `if (! schemaTableExists(...)) { return; }` — garde
 * correcte en intention (regle #5431 : jamais d'ALTER sur une table absente),
 * mais qui, dans cet ordre, rend la migration **no-op pour toujours** : elle
 * est enregistree comme appliquee, et la colonne n'est jamais creee.
 *
 * Effet mesure en production, et non seulement en test :
 *   - `RestaurantSendRemindersCommand` filtre `whereNull('reminder_sent_at')`
 *     -> SQLSTATE[42703] : le rappel J-1 ne peut pas s'executer.
 *   - `RestaurantCancellationPolicyController` ecrit la politique d'annulation
 *     -> toute mise a jour echoue.
 *   - `RestaurantLoyaltyController` ecrit l'opt-in RGPD -> l'activation du
 *     programme de fidelite echoue.
 *
 * Cette migration est **reentrante** et sans perte : `schemaTableExists` +
 * `Schema::hasColumn` avant chaque ajout, donc la rejouer sur une base ou les
 * colonnes existent deja (base recreee par un `migrate:fresh` correct) ne fait
 * rien, et une base ou les tables sont absentes (tenant non provisionne) est
 * laissee intacte.
 *
 * Les trois migrations d'origine sont conservees telles quelles : elles sont
 * deja appliquees partout, les modifier ne rejouerait rien et ferait diverger
 * l'historique. Le defaut de CLASSE (ordre des noms de fichier) est traite par
 * la garde `dev-hub/tools/check-migration-alter-before-create.py` ajoutee par
 * ce meme lot.
 */
return new class extends Migration
{
    /**
     * Colonne => [type de colonne, table].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const COLUMNS = [
        'reminder_sent_at' => ['timestamp', 'restaurant_reservations'],
        'opted_in_at' => ['timestamp', 'restaurant_loyalty_customers'],
        'cancel_free_hours' => ['unsignedInteger', 'restaurant_branches'],
        'cancel_fee_bps' => ['unsignedInteger', 'restaurant_branches'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $column => [$type, $table]) {
            $this->addColumnIfMissing($table, $column, $type);
        }
    }

    public function down(): void
    {
        // Volontairement vide : ces colonnes appartiennent aux fonctionnalites
        // livrees par #6208/#6211/#6213, pas a ce rattrapage. Les detruire
        // ici reviendrait a casser ces fonctionnalites en rollback — la regle
        // #8207 exige qu'un `down()` ne detruise que ce que son `up()` a cree,
        // or ici l'etat cible EST l'etat attendu par le code applicatif.
    }

    private function addColumnIfMissing(string $table, string $column, string $type): void
    {
        // Garde resolue via le search_path (#1613) : `Schema::hasTable()` seul
        // n'interroge que `current_schema()` et repondrait `false` a tort
        // lorsque la table vit dans `shared_tenants`.
        if (! schemaTableExists($table)) {
            return;
        }

        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($type, $column): void {
            $blueprint->{$type}($column)->nullable();
        });
    }
};
