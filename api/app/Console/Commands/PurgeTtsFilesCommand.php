<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Issue #5616 — Purge des fichiers audio TTS temporaires.
 *
 * Les fichiers générés par VoiceController (edge-tts ou ElevenLabs) sont
 * stockés dans `storage/app/tts/` et servis via des URLs signées à courte
 * durée (60 s). Ce scheduler les supprime après `--max-age` minutes (défaut :
 * 60 min) pour éviter l'accumulation sur le disque.
 *
 * Usage :
 *   php artisan tts:purge [--max-age=60] [--dry-run]
 */
class PurgeTtsFilesCommand extends Command
{
    protected $signature = 'tts:purge
        {--max-age=60 : Âge maximum des fichiers en minutes (défaut : 60)}
        {--dry-run    : Affiche les fichiers qui seraient supprimés sans les supprimer}';

    protected $description = 'Purge les fichiers audio TTS temporaires (issue #5616 — RGPD + espace disque)';

    public function handle(): int
    {
        $maxAgeMinutes = (int) $this->option('max-age');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subMinutes($maxAgeMinutes);

        $disk = Storage::disk('local');
        $files = $disk->files('tts');

        $deleted = 0;
        $skipped = 0;

        foreach ($files as $file) {
            // Ignorer les fichiers dont le nom ne correspond pas au pattern
            // généré par VoiceController (tts_<hex>.mp3).
            if (! preg_match('/^tts\/tts_[a-f0-9]+\.mp3$/', $file)) {
                $skipped++;
                continue;
            }

            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));

            if ($lastModified->isBefore($cutoff)) {
                if ($dryRun) {
                    $this->line("[dry-run] Supprimerait : {$file} (modifié le {$lastModified->toIso8601String()})");
                } else {
                    $disk->delete($file);
                    $this->line("Supprimé : {$file}");
                }
                $deleted++;
            }
        }

        $action = $dryRun ? 'à supprimer' : 'supprimés';
        $this->info("Purge TTS terminée — {$deleted} fichier(s) {$action}, {$skipped} ignoré(s).");

        return self::SUCCESS;
    }
}
