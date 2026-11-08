<?php

/**
 * Génère les testsuites `<file>` de découpage de la suite Feature (B7).
 *
 * POURQUOI CE SCRIPT EXISTE
 * -------------------------
 * Constat B7 (2026-11-07) : la suite Feature dépassait la limite de 5 h du
 * runner GitHub Actions, donc aucun run ne produisait de verdict complet.
 *
 * MESURE 2026-11-08 : le job `Backend` de tests.yml TERMINE désormais en ~82 min
 * (run 42408094007 : 4947,98 s). Le découpage n'est donc plus nécessaire pour
 * tenir dans le runner ; il reste un outil de DIAGNOSTIC manuel.
 *
 * Le découpage ne peut PAS se faire :
 *   - par `--filter` regex de nom de classe : 0 test sélectionné, SILENCIEUSEMENT
 *     (mesuré : 6 min 40 s pour zéro test) ;
 *   - par glob de fichiers : paratest refuse (`Too many arguments, expected
 *     arguments "path"`) — il n'accepte qu'UN SEUL <path> ;
 *   - par `<directory prefix="…">` : le préfixe s'applique RÉCURSIVEMENT, donc
 *     les shards se chevauchent.
 *
 * Le seul mécanisme qui fonctionne est une testsuite `<file>` explicite : une
 * entrée `<file>` par fichier. Vérifié en exécution réelle sous paratest
 * (`--testsuite=FeatureRootA --parallel` → 3 tests annoncés, 3 testcases au
 * JUnit, exit 0).
 *
 * AVERTISSEMENT (mesuré 2026-11-08) : lancer les 77 shards SANS plafond de
 * concurrence produit des FAUX échecs — 229 tests en erreur dans les shards
 * passent dans la suite complète (209 `statement timeout`, 19 table absente).
 * Le workflow plafonne donc `max-parallel` et ne se déclenche plus que
 * manuellement.
 *
 * CE SCRIPT
 * ---------
 * Il énumère les fichiers `tests/Feature/*Test.php` (le « shard root » : les
 * fichiers à la racine de tests/Feature, par opposition aux sous-répertoires qui
 * forment leurs propres shards), les pondère par leur nombre de méthodes de test,
 * puis les répartit en N paniers équilibrés (plus grand d'abord, glouton).
 *
 * Il écrit les testsuites ENTRE LES MARQUEURS `BEGIN/END GENERATED FEATURE
 * SHARDS` de `phpunit.xml` — c'est le fichier que PHPUnit lit réellement, donc
 * les shards sont utilisables tels quels par `--testsuite=FeatureRootN`. Le
 * reste de `phpunit.xml` n'est jamais touché : seule la zone entre marqueurs
 * est remplacée, et le script est idempotent.
 *
 * Usage :
 *   php dev-tools/generate-feature-shards.php [--shards=6] [--check]
 *
 *   --check : ne réécrit rien, échoue (exit 1) si le contenu généré diffère de
 *             celui présent dans phpunit.xml. À utiliser en garde de CI pour
 *             détecter un phpunit.xml édité à la main.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$shards = 6;
$check = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--shards=')) {
        $shards = (int) substr($arg, 9);
    } elseif ($arg === '--check') {
        $check = true;
    }
}

if ($shards < 1) {
    fwrite(STDERR, "Le nombre de shards doit être >= 1.\n");
    exit(1);
}

const BEGIN_MARKER = '<!-- BEGIN GENERATED FEATURE SHARDS -->';
const END_MARKER = '<!-- END GENERATED FEATURE SHARDS -->';

/**
 * Compte les méthodes de test d'un fichier sans l'exécuter.
 *
 * On compte les déclarations `function test…` ET les méthodes annotées
 * `#[Test]` (PHPUnit 11 accepte les deux styles ; le dépôt utilise
 * majoritairement le préfixe `test`). C'est une PONDÉRATION pour équilibrer les
 * paniers, pas une vérité : le compte exact par shard est établi ensuite par
 * `phpunit --list-tests`, qui est la seule source faisant autorité.
 */
function countTestMethods(string $path): int
{
    $source = (string) file_get_contents($path);

    $prefixed = preg_match_all('/function\s+test[A-Za-z0-9_]*\s*\(/', $source);
    $attributed = preg_match_all('/#\[Test\]\s*(?:public\s+)?function\s+[A-Za-z0-9_]+\s*\(/', $source);

    // Un fichier sans aucune méthode détectée reste compté pour 1 : il peut
    // contenir des tests fournis par un data provider ou un trait. Le compter
    // pour 0 le rendrait « gratuit » et fausserait l'équilibrage.
    return max(1, (int) $prefixed + (int) $attributed);
}

$files = glob($root.'/tests/Feature/*Test.php') ?: [];
sort($files);

if ($files === []) {
    fwrite(STDERR, "Aucun fichier tests/Feature/*Test.php trouvé.\n");
    exit(1);
}

$weighted = [];
$totalTests = 0;

foreach ($files as $file) {
    $weight = countTestMethods($file);
    $totalTests += $weight;
    $weighted[] = [
        'relative' => 'tests/Feature/'.basename($file),
        'weight' => $weight,
    ];
}

// Plus grand d'abord : le glouton place ainsi les gros fichiers avant que les
// paniers ne se remplissent de petits, ce qui réduit l'écart final.
usort($weighted, fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);

$buckets = array_fill(0, $shards, ['files' => [], 'weight' => 0]);

foreach ($weighted as $item) {
    // Paniers triés par poids croissant : le fichier va au moins chargé.
    usort($buckets, fn (array $a, array $b): int => $a['weight'] <=> $b['weight']);
    $buckets[0]['files'][] = $item['relative'];
    $buckets[0]['weight'] += $item['weight'];
}

$weights = array_column($buckets, 'weight');
$min = min($weights);
$max = max($weights);

// ── Shards de sous-répertoires ────────────────────────────────────────────
// La matrice du workflow (backend-feature-shards.yml) attend UN shard par
// sous-répertoire de tests/Feature. Sans ces testsuites, `--testsuite=<Sub>`
// ne sélectionne AUCUN test et paratest meurt en écrivant le JUnit
// (`Writer::write(): Argument #1 must be of type TestSuite, null given`) :
// 71 shards rouges pour un défaut de GÉNÉRATION, pas un échec de test.
// Les fichiers sont listés explicitement (récursivement) : c'est le seul
// mécanisme que paratest honore (cf. en-tête).
$subdirs = glob($root.'/tests/Feature/*', GLOB_ONLYDIR) ?: [];
sort($subdirs);

$subdirShards = [];
foreach ($subdirs as $dir) {
    $name = basename($dir);
    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if ($entry->isFile() && str_ends_with($entry->getFilename(), 'Test.php')) {
            $found[] = 'tests/Feature/'.$name.'/'.substr($entry->getPathname(), strlen($dir) + 1);
        }
    }
    if ($found === []) {
        continue; // ex. Fixtures/ : aucun test, donc aucun shard.
    }
    sort($found);
    $subdirShards[$name] = $found;
}

$generated = BEGIN_MARKER."\n";
$generated .= "        <!--\n";
$generated .= "          Zone GÉNÉRÉE — ne pas éditer à la main.\n";
$generated .= "          Régénérer : php dev-tools/generate-feature-shards.php (option shards={$shards})\n";
$generated .= "\n";
$generated .= "          Découpage du « shard root » de la suite Feature (les fichiers\n";
$generated .= "          tests/Feature/*Test.php, hors sous-répertoires qui forment leurs\n";
$generated .= "          propres shards). Chaque testsuite liste explicitement ses <file> :\n";
$generated .= "          c'est le seul mécanisme que paratest honore (cf. en-tête du\n";
$generated .= "          générateur).\n";
$generated .= "\n";
$generated .= "          Fichiers : ".count($files)." — poids cumulé : {$totalTests} méthodes.\n";
$generated .= "          Équilibrage : panier le plus léger {$min}, le plus lourd {$max}.\n";
$generated .= "\n";
$generated .= "          Suivent un shard par sous-répertoire de tests/Feature ("
    .count($subdirShards)." shards), chacun listant ses <file> récursivement.\n";
$generated .= "        -->\n";

foreach ($buckets as $index => $bucket) {
    $name = 'FeatureRoot'.($index + 1);
    $generated .= "        <testsuite name=\"{$name}\">\n";

    $filesInBucket = $bucket['files'];
    sort($filesInBucket);

    foreach ($filesInBucket as $relative) {
        $generated .= "            <file>{$relative}</file>\n";
    }

    $generated .= "        </testsuite>\n";
}

foreach ($subdirShards as $name => $subdirFiles) {
    $generated .= "        <testsuite name=\"{$name}\">\n";

    foreach ($subdirFiles as $relative) {
        $generated .= "            <file>{$relative}</file>\n";
    }

    $generated .= "        </testsuite>\n";
}

$generated .= '        '.END_MARKER;

$phpunitPath = $root.'/phpunit.xml';
$phpunit = (string) file_get_contents($phpunitPath);

$beginPos = strpos($phpunit, BEGIN_MARKER);
$endPos = strpos($phpunit, END_MARKER);

if ($beginPos === false || $endPos === false || $endPos < $beginPos) {
    fwrite(STDERR, "Marqueurs BEGIN/END GENERATED FEATURE SHARDS absents ou désordonnés dans phpunit.xml.\n");
    exit(1);
}

$endPos += strlen(END_MARKER);
$current = substr($phpunit, $beginPos, $endPos - $beginPos);

$assigned = array_sum(array_map(fn (array $b): int => count($b['files']), $buckets));

if ($check) {
    if ($current === $generated) {
        fwrite(STDOUT, "OK : les shards de phpunit.xml sont à jour.\n");
        exit(0);
    }

    fwrite(STDERR, "ÉCART : les shards de phpunit.xml diffèrent du contenu généré.\n");
    fwrite(STDERR, "Régénérer : php dev-tools/generate-feature-shards.php\n");
    exit(1);
}

$updated = substr($phpunit, 0, $beginPos).$generated.substr($phpunit, $endPos);
file_put_contents($phpunitPath, $updated);

fwrite(STDOUT, "Fichiers énumérés      : ".count($files)."\n");
fwrite(STDOUT, "Fichiers affectés      : {$assigned}\n");
fwrite(STDOUT, "Doublons               : ".(count($files) - $assigned === 0 ? 'aucun' : 'PRÉSENTS')."\n");
fwrite(STDOUT, "Poids min / max        : {$min} / {$max}\n");
fwrite(STDOUT, "Shards sous-répertoire : ".count($subdirShards)."\n");
fwrite(STDOUT, "Écrit dans             : phpunit.xml (zone générée)\n");

foreach ($buckets as $index => $bucket) {
    fwrite(STDOUT, sprintf(
        "  FeatureRoot%-2d : %3d fichiers, %5d méthodes\n",
        $index + 1,
        count($bucket['files']),
        $bucket['weight'],
    ));
}

exit($assigned === count($files) ? 0 : 1);
