<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Antivirus;

use App\Modules\Recruitment\Domain\Contracts\ResumeScanner;
use App\Modules\Recruitment\Domain\ValueObjects\ResumeScanVerdict;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BC-10 (#9217) — scanner ClamAV via `clamd` (protocole INSTREAM).
 *
 * Pourquoi INSTREAM et pas `clamscan` en ligne de commande :
 *
 *  - `clamscan` recharge la base virale à CHAQUE appel (plusieurs centaines de
 *    Mo, plusieurs secondes) : inutilisable sur un endpoint public de
 *    candidature. `clamd` garde la base en mémoire.
 *  - INSTREAM envoie le contenu par le socket : le fichier n'est jamais écrit
 *    dans un répertoire temporaire partagé, donc pas de fenêtre pendant
 *    laquelle un fichier non analysé serait lisible par un autre processus.
 *
 * Le protocole est volontairement implémenté à la main plutôt que via une
 * dépendance : il tient en une trentaine de lignes, et une bibliothèque
 * supplémentaire sur le chemin d'un upload public est une surface d'attaque
 * de plus pour un gain nul.
 *
 * @see https://linux.die.net/man/8/clamd — commande INSTREAM
 */
final class ClamAvResumeScanner implements ResumeScanner
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeoutSeconds,
        private readonly int $chunkSize = 8192,
    ) {}

    public function name(): string
    {
        return 'clamav';
    }

    public function scan(string $absolutePath, string $originalName): ResumeScanVerdict
    {
        if (! is_readable($absolutePath)) {
            return ResumeScanVerdict::unavailable($this->name(), 'fichier illisible');
        }

        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeoutSeconds);

        if ($socket === false) {
            // Panne d'infrastructure, PAS un fichier sain. On le dit.
            Log::warning('recruitment.resume_scan.daemon_unreachable', [
                'host' => $this->host,
                'port' => $this->port,
                'errno' => $errno,
                'error' => $errstr,
            ]);

            return ResumeScanVerdict::unavailable($this->name(), 'démon ClamAV injoignable');
        }

        try {
            stream_set_timeout($socket, $this->timeoutSeconds);

            if (fwrite($socket, "zINSTREAM\0") === false) {
                return ResumeScanVerdict::unavailable($this->name(), 'écriture du handshake impossible');
            }

            $handle = fopen($absolutePath, 'rb');

            if ($handle === false) {
                return ResumeScanVerdict::unavailable($this->name(), 'ouverture du fichier impossible');
            }

            try {
                while (! feof($handle)) {
                    // `max(1, ...)` : `fread` exige une longueur strictement
                    // positive, et PHPStan ne peut pas prouver que la config
                    // n'est pas 0 ou négative.
                    $chunk = fread($handle, max(1, $this->chunkSize));

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    // INSTREAM : chaque bloc est préfixé par sa taille en
                    // big-endian sur 4 octets, et le flux se termine par un
                    // bloc de taille 0.
                    fwrite($socket, pack('N', strlen($chunk)).$chunk);
                }
            } finally {
                fclose($handle);
            }

            fwrite($socket, pack('N', 0));

            $response = '';
            while (! feof($socket)) {
                $line = fgets($socket, 4096);

                if ($line === false) {
                    break;
                }

                $response .= $line;

                if (str_contains($response, "\0") || str_contains($response, "\n")) {
                    break;
                }
            }

            return $this->interpret($response, $originalName);
        } catch (Throwable $e) {
            Log::warning('recruitment.resume_scan.failed', [
                'message' => $e->getMessage(),
            ]);

            return ResumeScanVerdict::unavailable($this->name(), 'erreur pendant l\'analyse');
        } finally {
            fclose($socket);
        }
    }

    /**
     * Traduit la réponse de clamd en verdict.
     *
     * Réponses possibles : `stream: OK`, `stream: <Signature> FOUND`,
     * `... ERROR`. Toute réponse non reconnue est traitée comme
     * INDISPONIBLE — jamais comme saine : on ne déduit pas la propreté d'un
     * fichier d'une réponse qu'on ne comprend pas.
     */
    private function interpret(string $response, string $originalName): ResumeScanVerdict
    {
        $response = trim(str_replace("\0", '', $response));

        if ($response === '') {
            return ResumeScanVerdict::unavailable($this->name(), 'réponse vide du démon');
        }

        if (str_ends_with($response, 'OK')) {
            return ResumeScanVerdict::clean($this->name());
        }

        if (str_contains($response, 'FOUND')) {
            // `stream: Eicar-Test-Signature FOUND` → on extrait la signature.
            $signature = 'inconnue';

            if (preg_match('/:\s*(.+?)\s+FOUND/', $response, $matches) === 1) {
                $signature = $matches[1];
            }

            Log::warning('recruitment.resume_scan.infected', [
                'original_name' => $originalName,
                'signature' => $signature,
            ]);

            return ResumeScanVerdict::infected($this->name(), $signature);
        }

        return ResumeScanVerdict::unavailable($this->name(), 'réponse non reconnue du démon');
    }
}
