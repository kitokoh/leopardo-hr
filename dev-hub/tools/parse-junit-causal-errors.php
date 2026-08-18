<?php

/**
 * parse-junit-causal-errors.php — Extraction de l'erreur CAUSALE depuis un
 * rapport JUnit PHPUnit (issue #4978, critère 5).
 *
 * Objectif : quand la suite Feature échoue en cascade (178 échecs dont 1
 * cause racine — SQLSTATE 25P02/42P07/42P01 après la première violation), le
 * log du job ne doit plus noyer l'erreur causale sous 177 copies. Ce script
 * regroupe les échecs par classe, isole les motifs SQLSTATE, et imprime
 * l'échec racine (le premier, hors cascade) avec fichier:ligne.
 *
 * Usage : php parse-junit-causal-errors.php <rapport-junit.xml> [--max=N]
 * Sortie : résumé sur stdout. Exit 0 toujours (outil de diagnostic ; la
 * décision de fail appartient aux gates CI).
 */

$report = $argv[1] ?? null;
if (! $report || ! is_file($report)) {
    fwrite(STDERR, "usage: php parse-junit-causal-errors.php <junit.xml> [--max=N]\n");
    exit(2);
}

$max = 10;
foreach ($argv as $arg) {
    if (str_starts_with((string) $arg, '--max=')) {
        $max = (int) substr((string) $arg, 6);
    }
}

$xml = simplexml_load_file($report);
if ($xml === false) {
    fwrite(STDERR, "Impossible de lire le rapport JUnit : {$report}\n");
    exit(2);
}

/** Motifs SQLSTATE d'échecs en cascade (la cause précède les copies). */
const SQLSTATE_PATTERNS = [
    '25P02' => 'transaction aborted (current transaction is aborted)',
    '42P07' => 'relation already exists',
    '42P01' => 'relation does not exist',
    '23505' => 'unique violation',
    '23503' => 'foreign key violation',
    '23502' => 'not null violation',
    '42703' => 'undefined column',
    '2BP01'  => 'dependent objects still exist',
    '22001'  => 'value too long',
];

$failures = [];
$sqlstateCounts = [];

foreach ($xml->xpath('//testcase[failure|error]') as $testcase) {
    $class = (string) ($testcase['classname'] ?? $testcase['class'] ?? '?');
    $file = (string) ($testcase['file'] ?? '?');
    $line = (string) ($testcase['line'] ?? '?');
    $name = (string) $testcase['name'];

    foreach (['failure', 'error'] as $kind) {
        foreach ($testcase->{$kind} as $node) {
            $message = (string) ($node['message'] ?? '');
            $detail = trim((string) $node);
            if ($message === '') {
                $message = strtok($detail, "\n") ?: '(sans message)';
            }
            $firstLine = strtok($message, "\n") ?: $message;

            $isSql = false;
            foreach (SQLSTATE_PATTERNS as $code => $label) {
                if (str_contains($message, $code) || str_contains($detail, $code)) {
                    $sqlstateCounts[$code] = ($sqlstateCounts[$code] ?? 0) + 1;
                    $isSql = true;
                }
            }
            // formes non-SQLSTATE : "relation X does not exist", "already exists"
            if (! $isSql && (str_contains($detail, ' does not exist')
                || str_contains($detail, ' already exists'))) {
                $isSql = true;
            }

            $failures[] = [
                'class' => $class,
                'name' => $name,
                'file' => $file,
                'line' => $line,
                'message' => strlen($firstLine) > 260
                    ? substr($firstLine, 0, 257).'…'
                    : $firstLine,
                'isSql' => $isSql,
            ];
        }
    }
}

$total = count($failures);
echo "=== Erreurs causales — {$report} ===\n";
if ($total === 0) {
    echo "Aucun échec dans le rapport.\n";
    exit(0);
}

$sqlTotal = array_sum($sqlstateCounts);
echo "Total échecs/erreurs : {$total} (dont {$sqlTotal} motifs SQLSTATE)\n";

if ($sqlstateCounts) {
    arsort($sqlstateCounts);
    echo "\nMotifs SQLSTATE (cascade probable si un seul code domine) :\n";
    foreach ($sqlstateCounts as $code => $count) {
        $label = SQLSTATE_PATTERNS[$code] ?? '?';
        printf("  %-6s %4d  %s\n", $code, $count, $label);
    }
    // Un code dominant => la première occurrence est la cause racine.
    $firstCode = array_key_first($sqlstateCounts);
    $firstCount = $sqlstateCounts[$firstCode];
    if ($total >= 3 && $firstCount >= ($total / 2)) {
        $first = null;
        foreach ($failures as $f) {
            if (str_contains($f['message'], $firstCode)) {
                $first = $f;
                break;
            }
        }
        if ($first) {
            echo "\n⚠ Cause racine probable — première occurrence de {$firstCode} :\n";
            printf("  %s:%s — %s::%s\n  %s\n",
                $first['file'], $first['line'], $first['class'], $first['name'], $first['message']);
        }
    }
}

echo "\nPremiers échecs par classe (hors copies de cascade) :\n";
$seen = [];
$printed = 0;
foreach ($failures as $f) {
    $key = $f['class'];
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    printf("  %s:%s — %s::%s\n    %s\n", $f['file'], $f['line'], $f['class'], $f['name'], $f['message']);
    if (++$printed >= $max) {
        echo "  … (limité à {$max} classes ; {$total} échecs au total)\n";
        break;
    }
}
