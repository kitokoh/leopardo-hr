<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7595 — Pages vues de la vitrine (analytique first-party, agrégée par jour).
 *
 * ## Pourquoi une table d'agrégats et non un journal d'événements
 *
 * Le funnel (#7496) stocke un événement par étape, parce qu'on doit pouvoir
 * reconstituer un parcours par `correlation_id`. Ici, on veut répondre à
 * « combien de visites, quelles pages, d'où » sur 90 jours : un journal brut
 * ferait grossir la base proportionnellement au trafic pour un besoin qui est
 * purement agrégé. On incrémente donc un compteur par
 * `(jour, page, source, medium, campaign)`, et la granularité journalière est
 * celle qu'affiche l'écran.
 *
 * ## Pourquoi aucune PII n'est possible par construction
 *
 * Pas de colonne IP, pas d'empreinte d'appareil, pas de `session_id` : une
 * visite est un incrément sur une clé d'attribution, jamais une personne.
 * C'est ce qui rend l'écran conforme sans avoir à écrire une purge — il n'y a
 * rien à purger.
 *
 * ## Pourquoi le jour est une colonne `date` séparée
 *
 * `occurred_at::date` dans un `GROUP BY` empêche l'usage de l'index et impose
 * un tri complet de la fenêtre. Avec `day` matérialisé, l'upsert et les
 * lectures retombent sur l'index unique `(day, page_path, source, medium,
 * campaign)`.
 *
 * ## Pourquoi l'unicité passe par un index d'EXPRESSION et non `$table->unique()`
 *
 * Piège PostgreSQL : dans un index unique, deux `NULL` ne sont PAS égaux (un
 * index B-tree considère les NULL comme distincts). Avec
 * `$table->unique(['day','page_path','source',...])`, deux vues directes de la
 * même page (source, medium et campaign tous NULL) créent donc DEUX lignes au
 * lieu d'incrémenter un compteur — vérifié en test : 3 vues, 3 lignes, et des
 * totaux faux d'un facteur arbitraire.
 *
 * L'index porte donc sur `COALESCE(colonne, '')`, ce qui rend deux attributions
 * absentes égales entre elles. Les colonnes restent NULL en lecture (l'API les
 * expose comme « direct »), seule la clé d'unicité est normalisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vitrine_page_views')) {
            return;
        }

        Schema::create('vitrine_page_views', function (Blueprint $table): void {
            $table->id();
            // Jour civil UTC : la vitrine est publique et mondiale, une
            // agrégation « locale » n'aurait pas de fuseau de référence.
            $table->date('day');
            // Chemin normalisé (sans query string, sans slash final) — la
            // cardinalité reste bornée par le nombre de routes de la vitrine.
            $table->string('page_path', 255);
            // Attribution : referrer externe ou utm_source, jamais l'URL
            // complète du referrer (qui peut porter une recherche).
            $table->string('source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });

        // Unicité réelle : NULL ≡ '' sur chaque colonne d'attribution.
        DB::statement(
            "CREATE UNIQUE INDEX vitrine_page_views_unique ON vitrine_page_views "
            ."(day, page_path, COALESCE(source, ''), COALESCE(utm_medium, ''), COALESCE(utm_campaign, ''))"
        );

        DB::statement('CREATE INDEX vitrine_page_views_day_index ON vitrine_page_views (day)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vitrine_page_views');
    }
};
