<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * ATS (#9461) — diagnostic des binaires d'extraction du texte des CV.
 *
 * ## Pourquoi cette commande existe
 *
 * `ShellResumeTextExtractor` échoue en `null` quand un binaire manque : c'est
 * le bon comportement à l'exécution (un CV scanné ne doit pas faire échouer
 * une candidature), mais c'est une panne SILENCIEUSE. Sans cette commande, une
 * image de production sans `poppler-utils` dégrade le score ATS de tous les
 * candidats sans qu'aucune alerte ne se déclenche : les tests passent (le
 * runner CI a les binaires), la prod non.
 *
 * La commande sort en FAILURE si un binaire requis manque, ce qui la rend
 * utilisable telle quelle dans un smoke test de déploiement :
 *
 *     php artisan recruitment:check-extractors
 *
 * `--json` produit une sortie machine pour un script de déploiement.
 */
class RecruitmentCheckExtractorsCommand extends Command
{
    protected $signature = 'recruitment:check-extractors
        {--json : Sortie JSON (pour un script de déploiement)}';

    protected $description = 'Vérifie la présence des binaires d\'extraction du texte des CV (pdftotext, antiword, catdoc)';

    /**
     * Binaires requis, avec le format qu'ils couvrent.
     *
     * `catdoc` est marqué optionnel : l'extracteur ne s'en sert qu'en repli
     * quand `antiword` ne sait pas lire un `.doc`. Son absence dégrade la
     * couverture des vieux fichiers Word, elle ne casse pas le PDF — qui est
     * de très loin le format dominant. Le rendre bloquant ferait échouer un
     * déploiement pour un cas marginal.
     *
     * @var array<string, array{format: string, required: bool}>
     */
    private const BINARIES = [
        'pdftotext' => ['format' => 'PDF', 'required' => true],
        'antiword' => ['format' => 'DOC', 'required' => true],
        'catdoc' => ['format' => 'DOC (repli)', 'required' => false],
    ];

    public function handle(): int
    {
        $report = [];
        $missingRequired = [];

        foreach (self::BINARIES as $binary => $meta) {
            $path = $this->locate($binary);
            $present = $path !== null;

            $report[$binary] = [
                'format' => $meta['format'],
                'required' => $meta['required'],
                'present' => $present,
                'path' => $path,
            ];

            if (! $present && $meta['required']) {
                $missingRequired[] = $binary;
            }
        }

        // DOCX ne dépend d'aucun binaire : il est lu par ZipArchive +
        // DOMDocument, tous deux fournis par l'image PHP standard. On le
        // vérifie quand même — une extension `zip` absente est un cas réel
        // sur une image PHP minimale, et elle casserait silencieusement le
        // format de CV le plus courant.
        $zipAvailable = class_exists(\ZipArchive::class);
        $report['ziparchive'] = [
            'format' => 'DOCX',
            'required' => true,
            'present' => $zipAvailable,
            'path' => null,
        ];

        if (! $zipAvailable) {
            $missingRequired[] = 'ziparchive (extension PHP zip)';
        }

        $ok = $missingRequired === [];

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $ok ? 'ok' : 'error',
                'missing' => $missingRequired,
                'binaries' => $report,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('  <options=bold>Extraction du texte des CV — binaires requis</>');
        $this->newLine();

        $rows = [];
        foreach ($report as $name => $info) {
            $rows[] = [
                $name,
                $info['format'],
                $info['required'] ? 'oui' : 'non',
                $info['present'] ? '<fg=green>présent</>' : '<fg=red>ABSENT</>',
                $info['path'] ?? '—',
            ];
        }

        $this->table(['Binaire', 'Format', 'Requis', 'État', 'Chemin'], $rows);

        if ($ok) {
            $this->line('  <fg=green>OK</> — tous les binaires requis sont présents.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->line('  <fg=red>ÉCHEC</> — binaires manquants : '.implode(', ', $missingRequired));
        $this->newLine();
        $this->line('  Sans eux, le score ATS retombe sur la seule lettre de motivation');
        $this->line('  (un candidat sans lettre obtient 0 % malgré un CV aligné).');
        $this->newLine();
        $this->line('  Debian/Ubuntu : <options=bold>apt-get install -y poppler-utils antiword catdoc</>');
        $this->line('  Alpine        : <options=bold>apk add --no-cache poppler-utils antiword catdoc</>');
        $this->newLine();

        return self::FAILURE;
    }

    /**
     * Localise un binaire dans le PATH, sans passer par le shell.
     *
     * On n'utilise pas `which` : la commande dépendrait alors d'un binaire
     * supplémentaire, et `which` n'existe pas sur toutes les images minimales.
     * `exec` est refusé par certains durcissements PHP ; on lit donc le PATH
     * directement.
     */
    private function locate(string $binary): ?string
    {
        $path = getenv('PATH');

        if (! is_string($path) || $path === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory === '') {
                continue;
            }

            $candidate = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary;

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
