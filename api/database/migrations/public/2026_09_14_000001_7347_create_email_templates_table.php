<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #7347 — surcharges éditables des e-mails (cockpit plateforme).
 *
 * Schema public (qualifié explicitement), comme les autres tables du cockpit
 * super-admin. Une ligne = une surcharge (template_key, locale) ; l'absence de
 * ligne signifie « valeur par défaut du catalogue » — c'est le cas normal, et
 * c'est ce qui garantit qu'un template non modifié n'a pas changé de rendu.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.email_templates (
    id bigserial PRIMARY KEY,
    template_key varchar(80) NOT NULL,
    locale varchar(5) NOT NULL,
    subject text NULL,
    heading text NULL,
    body text NULL,
    cta_label varchar(160) NULL,
    updated_by varchar(180) NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT email_templates_key_locale_unique UNIQUE (template_key, locale)
);
CREATE INDEX IF NOT EXISTS email_templates_template_key_idx
    ON public.email_templates (template_key);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS public.email_templates');
    }
};
