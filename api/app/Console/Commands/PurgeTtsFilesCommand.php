<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * #5616 — Purge des fichiers TTS audio générés plus d'une heure plus tôt.
 *
 * Les fichiers TTS sont stockés dans storage/app/tts/ avec des URL signées
 * de 60 secondes : au-delà ils sont inaccessibles mais occupent du disque.
 * Ce cron supprime les fichiers > 1 h pour respecter les obligations RGPD
 * (minimisation des données) et éviter la saturation disque.
 *
 * Planification : toutes les heures (voir routes/console.php).
 */
class PurgeTtsFilesCommand extends Command
{
    protected $signature = 'tts:purge {--older-than=3600 : Supprimer les fichiers plus anciens que N secondes (défaut : 3600 = 1 h)}';

    protected $description = 'Supprime les fichiers TTS générés il y a plus d\'une heure (#5616 — RGPD)';

    public function handle(): int
    {
        $olderThan = (int) $this->option('older-than');
        $cutoff = now()->subSeconds($olderThan);

        $disk = Storage::disk('local');
        $files = $disk->files('tts');

        $deleted = 0;
        $errors = 0;

        foreach ($files as $file) {
            // N'opérer que sur les fichiers générés par le TTS (tts_<hex>.mp3).
            $basename = basename($file);
            if (! preg_match('/^tts_[a-f0-9]+\.mp3$/', $basename)) {
                continue;
            }

            try {
                $lastModified = $disk->lastModified($file);
                if ($lastModified < $cutoff->timestamp) {
                    $disk->delete($file);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                $this->warn("Impossible de supprimer {$file} : ".$e->getMessage());
                $errors++;
            }
        }

        $this->info("TTS purge : {$deleted} fichier(s) supprimé(s), {$errors} erreur(s).");

        return self::SUCCESS;
    }
}
