<?php

declare(strict_types=1);

/**
 * #9876 (R2) — régénère la baseline des contrôleurs sans test.
 *
 * Usage : php dev-tools/generate-uncovered-controllers-baseline.php
 *
 * À lancer UNIQUEMENT après avoir ajouté des tests (la baseline doit
 * rétrécir) ou après avoir créé un contrôleur dont l'absence de test est
 * assumée et documentée. Le test `ControllerCoverageRatchetTest` échoue si
 * la baseline grossit sans que ce script ait été relancé — c'est voulu.
 */

$root = dirname(__DIR__);

$controllers = [];
$finder = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/app/Modules', FilesystemIterator::SKIP_DOTS)
);

foreach ($finder as $file) {
    /** @var SplFileInfo $file */
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Controller.php')) {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());

    if (! preg_match('#/Interfaces/Api/.*/Controllers/#', $path)) {
        continue;
    }

    $controllers[] = str_replace($root.'/', '', $path);
}

sort($controllers);

// Corpus de test : une seule lecture.
$corpus = '';
$testFinder = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS)
);

foreach ($testFinder as $file) {
    /** @var SplFileInfo $file */
    if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
        $corpus .= file_get_contents($file->getPathname());
    }
}

$uncovered = array_values(array_filter(
    $controllers,
    static fn (string $c): bool => ! str_contains($corpus, basename($c, '.php'))
));

$dir = $root.'/tests/Arch/baselines';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

file_put_contents(
    $dir.'/uncovered-controllers.json',
    json_encode([
        'generated_by' => '#9876',
        'total_controllers' => count($controllers),
        'uncovered' => $uncovered,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
);

printf(
    "Baseline régénérée : %d contrôleurs, %d sans test (%.0f%%).\n",
    count($controllers),
    count($uncovered),
    count($controllers) > 0 ? 100 * count($uncovered) / count($controllers) : 0
);
