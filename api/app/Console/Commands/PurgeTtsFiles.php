<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Issue #5631 (RGPD) : purge des fichiers TTS expirés.
 *
 * Chaque synthèse vocale écrit un fichier storage/app/tts/tts_*.mp3
 * (50-200 Ko) — sans purge, le disque se sature et les données audio
 * (potentiellement personnelles : noms, salaires...) restent au repos
 * indéfiniment. Les URLs servies sont déjà signées/expirantes (route
 * `tts.audio`, 5 min) ; cette commande supprime les fichiers eux-mêmes.
 *
 * Usage : php artisan tts:purge --older-than=60
 */
class PurgeTtsFiles extends Command
{
    protected $signature = 'tts:purge {--older-than=60 : âge minimal (minutes) des fichiers à supprimer}';

    protected $description = 'Supprime les fichiers TTS plus vieux que --older-than minutes';

    public function handle(): int
    {
        $olderThanMinutes = max(1, (int) $this->option('older-than'));
        $disk = Storage::disk('local');
        $cutoff = now()->subMinutes($olderThanMinutes)->getTimestamp();

        $deleted = 0;
        foreach ($disk->files('tts') as $file) {
            if (($disk->lastModified($file) ?? 0) < $cutoff) {
                if ($disk->delete($file)) {
                    $deleted++;
                }
            }
        }

        $this->info("TTS purge : {$deleted} fichier(s) supprimé(s) (âge > {$olderThanMinutes} min).");

        return self::SUCCESS;
    }
}
