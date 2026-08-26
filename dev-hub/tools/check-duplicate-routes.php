<?php

declare(strict_types=1);

/**
 * check-duplicate-routes.php — garde CI contre les collisions de routes (#5577)
 *
 * Utilise `php artisan route:list --json` pour obtenir les routes Laravel avec
 * leurs URIs complètes (préfixes inclus), puis détecte les paires (METHOD, URI)
 * enregistrées plusieurs fois — ce qui rend l'un des contrôleurs silencieusement
 * inatteignable (Laravel ne sert que la première déclaration).
 *
 * Usage : php dev-hub/tools/check-duplicate-routes.php [api_dir]
 * Exemple depuis la racine du monorepo : php dev-hub/tools/check-duplicate-routes.php api
 *
 * Retour : 0 si aucun doublon, 1 si au moins un doublon détecté.
 *
 * Ignorés intentionnellement :
 *   - Routes de HEAD (Laravel génère automatiquement HEAD pour chaque GET).
 *   - Routes dont l'URI ou la méthode est vide.
 *   - Routes tagged comme "deprecated" dans leur nom (ex. aliases rétro-compat).
 */

$apiDir = $argv[1] ?? 'api';

if (! is_dir($apiDir)) {
    fwrite(STDERR, "❌  Répertoire introuvable : {$apiDir}\n");
    exit(2);
}

// Exécuter artisan route:list en JSON
$cmd = sprintf(
    'cd %s && php artisan route:list --json --columns=method,uri,name,action 2>&1',
    escapeshellarg($apiDir)
);

$output = [];
$exitCode = 0;
exec($cmd, $output, $exitCode);

$json = implode("\n", $output);

if ($exitCode !== 0) {
    fwrite(STDERR, "❌  artisan route:list a échoué (code {$exitCode}) :\n{$json}\n");
    exit(2);
}

$routes = json_decode($json, true);

if (! is_array($routes)) {
    fwrite(STDERR, "❌  Impossible de décoder le JSON de route:list.\n");
    fwrite(STDERR, "Output brut : " . substr($json, 0, 500) . "\n");
    exit(2);
}

// Construire une map (METHOD|URI → [routes…])
$seen = [];

foreach ($routes as $route) {
    $method = strtoupper(trim((string) ($route['method'] ?? '')));
    $uri = trim((string) ($route['uri'] ?? ''));
    $name = (string) ($route['name'] ?? '');
    $action = (string) ($route['action'] ?? '');

    // Ignorer les routes HEAD (générées automatiquement par Laravel pour chaque GET)
    if ($method === 'HEAD') {
        continue;
    }

    // Ignorer les routes vides
    if ($method === '' || $uri === '') {
        continue;
    }

    // Ignorer les aliases dépréciés (nom de route contient "deprecated" ou "legacy")
    if (preg_match('/déprécié|deprecated|legacy[._-]alias/i', $name)) {
        continue;
    }

    // Laravel liste GET|HEAD ensemble — on prend GET uniquement
    if (str_contains($method, '|')) {
        $methods = explode('|', $method);
        foreach ($methods as $m) {
            $m = trim($m);
            if ($m === 'HEAD') {
                continue;
            }
            $key = strtoupper($m) . '|' . $uri;
            $seen[$key][] = ['method' => strtoupper($m), 'uri' => $uri, 'action' => $action, 'name' => $name];
        }
        continue;
    }

    $key = "{$method}|{$uri}";
    $seen[$key][] = ['method' => $method, 'uri' => $uri, 'action' => $action, 'name' => $name];
}

// Filtrer les doublons
$duplicates = array_filter($seen, fn (array $routes): bool => count($routes) > 1);

if (count($duplicates) === 0) {
    $total = count($routes);
    echo "✅  Aucune route dupliquée (méthode + URI) détectée ({$total} routes analysées).\n";
    exit(0);
}

echo "\n";
echo "❌  Routes dupliquées détectées (" . count($duplicates) . " collision(s)) :\n";
echo "\n";

foreach ($duplicates as $key => $collisions) {
    [$method, $uri] = explode('|', $key, 2);
    echo "   {$method} /{$uri}\n";
    foreach ($collisions as $r) {
        $action = $r['action'] !== '' ? $r['action'] : '(closure)';
        echo "     → {$action}\n";
    }
    echo "\n";
}

echo "  " . count($duplicates) . " collision(s) détectée(s).\n";
echo "  Laravel ne sert que la PREMIÈRE déclaration : les contrôleurs suivants\n";
echo "  sont silencieusement inatteignables.\n";
echo "  Ref : issue #5577\n\n";

exit(1);
