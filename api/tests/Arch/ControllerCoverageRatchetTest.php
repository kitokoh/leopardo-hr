<?php

declare(strict_types=1);

/**
 * #9876 (R2) — ratchet de couverture des contrôleurs.
 *
 * Constat mesuré : 463 contrôleurs sur 582 (80 %) ne sont référencés par
 * AUCUN fichier de test. Le dépôt sait déjà geler une dette et interdire
 * qu'elle grossisse (baselines PHPStan, ratchet de dette inter-modules) ;
 * il manquait le même mécanisme pour la couverture.
 *
 * Ce test ne demande pas de couvrir 463 contrôleurs d'un coup — ce serait
 * irréaliste et personne ne le ferait. Il garantit trois choses :
 *
 *   1. un NOUVEAU contrôleur sans test fait échouer la CI ;
 *   2. la baseline ne peut pas GROSSIR (ajouter une entrée = échec) ;
 *   3. la baseline peut RÉTRÉCIR librement (c'est l'objectif).
 *
 * Régénérer la baseline après avoir ajouté des tests :
 *   php dev-tools/generate-uncovered-controllers-baseline.php
 *
 * Aucune base de données n'est requise : le test lit le système de fichiers.
 */

use Symfony\Component\Finder\Finder;

/**
 * Contrôleurs de module (app/Modules/<M>/Interfaces/Api/**\/Controllers).
 *
 * @return array<int, string> chemins relatifs à api/
 */
function uncoveredControllersList(): array
{
    $root = dirname(__DIR__, 2);

    if (! is_dir($root.'/app/Modules')) {
        return [];
    }

    $controllers = [];
    $finder = (new Finder)
        ->files()
        ->in($root.'/app/Modules')
        ->path('/\/Interfaces\/Api\/.*\/Controllers\//')
        ->name('*Controller.php');

    foreach ($finder as $file) {
        $controllers[] = str_replace($root.'/', '', $file->getPathname());
    }

    sort($controllers);

    return $controllers;
}

/**
 * Concatène tous les fichiers de test (une seule lecture, pas 463).
 */
function uncoveredControllersTestCorpus(): string
{
    $root = dirname(__DIR__, 2);
    $corpus = '';

    if (! is_dir($root.'/tests')) {
        return $corpus;
    }

    $finder = (new Finder)->files()->in($root.'/tests')->name('*.php');

    foreach ($finder as $file) {
        $corpus .= $file->getContents();
    }

    return $corpus;
}

/**
 * @return array<int, string>
 */
function uncoveredControllersBaseline(): array
{
    $path = __DIR__.'/baselines/uncovered-controllers.json';

    if (! is_file($path)) {
        return [];
    }

    /** @var array{uncovered?: array<int, string>} $data */
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    return $data['uncovered'] ?? [];
}

it('ne laisse aucun NOUVEAU contrôleur sans référence de test', function (): void {
    $baseline = uncoveredControllersBaseline();
    $corpus = uncoveredControllersTestCorpus();

    $newlyUncovered = [];

    foreach (uncoveredControllersList() as $controller) {
        $class = basename($controller, '.php');

        // Déjà dans la baseline : dette connue, tolérée.
        if (in_array($controller, $baseline, true)) {
            continue;
        }

        // Hors baseline et absent des tests : c'est une NOUVELLE dette.
        if (! str_contains($corpus, $class)) {
            $newlyUncovered[] = $controller;
        }
    }

    expect($newlyUncovered)->toBe(
        [],
        "Nouveaux contrôleurs sans aucun test :\n  - ".implode("\n  - ", $newlyUncovered)
        ."\n\nAjoutez un test qui référence la classe, ou (si c'est assumé) "
        .'régénérez la baseline : php dev-tools/generate-uncovered-controllers-baseline.php'
    );
});

it('ne laisse pas la baseline de couverture grossir', function (): void {
    $baseline = uncoveredControllersBaseline();
    $corpus = uncoveredControllersTestCorpus();

    // Une entrée de baseline devenue couverte doit être RETIRÉE : sinon la
    // baseline ment sur l'état réel et le ratchet ne rétrécit jamais.
    $stale = [];

    foreach ($baseline as $controller) {
        $class = basename($controller, '.php');

        if (str_contains($corpus, $class)) {
            $stale[] = $controller;
        }
    }

    expect($stale)->toBe(
        [],
        'Ces contrôleurs sont désormais testés mais figurent encore dans la '
        ."baseline — retirez-les pour que le ratchet rétrécisse :\n  - "
        .implode("\n  - ", $stale)
    );
});

it('garde une baseline cohérente avec le code réel', function (): void {
    $baseline = uncoveredControllersBaseline();
    $existing = uncoveredControllersList();

    // Une entrée pointant vers un fichier supprimé est de la fausse dette.
    $ghosts = array_values(array_diff($baseline, $existing));

    expect($ghosts)->toBe(
        [],
        "Entrées de baseline pointant vers des contrôleurs inexistants :\n  - "
        .implode("\n  - ", $ghosts)
    );
});
