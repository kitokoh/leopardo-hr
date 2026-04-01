# CC-00 — Validation Infrastructure
# Agent : Claude Code
# Durée : 2-3 heures
# Rôle : PORTE D'ENTRÉE — rien ne commence sans ce fichier vert

---

## POURQUOI CE PROMPT EXISTE

Avant d'écrire une seule ligne de code métier, tu dois prouver que l'environnement
d'hébergement réel supporte la stack complète.
Si tu sautes cette étape et que l'hébergement est incompatible, tu découvriras
le problème en semaine 6 avec 10 000 lignes de code déjà écrites.

---

## MISSION

Déployer un prototype **vide** de la stack sur o2switch et vérifier que tout fonctionne.
Ce prototype ne contient aucune logique métier — uniquement l'infrastructure.

---

## PRÉREQUIS HUMAINS (vérifier avant de commencer)

```
[ ] Accès SSH à o2switch disponible (host + user + clé SSH)
[ ] PHP 8.3 disponible sur le serveur (vérifier : php8.3 -v)
[ ] PostgreSQL 16 accessible (local ou réseau interne)
[ ] Redis accessible (local ou réseau interne)
[ ] Domaine/sous-domaine pointant vers le serveur (ou IP directe acceptable en dev)
```

Si l'un de ces points est rouge → STOP. Résoudre avant de continuer.

---

## ÉTAPE 1 — Créer le projet minimal

```bash
composer create-project laravel/laravel leopardo-proto
cd leopardo-proto
composer require laravel/horizon
```

---

## ÉTAPE 2 — Configurer .env (minimal)

```env
APP_NAME=LeopardoProto
APP_ENV=production
APP_KEY=  # généré par artisan key:generate

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=leopardo_proto
DB_USERNAME=leopardo_user
DB_PASSWORD=XXXX

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## ÉTAPE 3 — Test PostgreSQL multi-schéma

```bash
php artisan tinker
```

```php
// Dans tinker — tester le switch de schéma PostgreSQL
DB::statement("CREATE SCHEMA IF NOT EXISTS test_tenant_abc");
DB::statement("SET search_path TO test_tenant_abc, public");
DB::statement("CREATE TABLE IF NOT EXISTS test_table (id SERIAL PRIMARY KEY, name TEXT)");
DB::statement("INSERT INTO test_table (name) VALUES ('hello')");
$result = DB::select("SELECT * FROM test_table");
var_dump($result); // doit retourner [{"id":1,"name":"hello"}]
DB::statement("SET search_path TO public");
DB::statement("DROP SCHEMA test_tenant_abc CASCADE");
echo "PostgreSQL multi-schéma : OK";
```

---

## ÉTAPE 4 — Test Redis

```bash
php artisan tinker
```

```php
Cache::put('test_key', 'leopardo_ok', 60);
$val = Cache::get('test_key');
if ($val === 'leopardo_ok') echo "Redis cache : OK";

Queue::push(new \Illuminate\Queue\SerializableClosure(function() { return true; }));
echo "Redis queue : OK";
```

---

## ÉTAPE 5 — Créer l'endpoint de santé

```php
// routes/api.php
Route::get('/v1/health', function () {
    $checks = [
        'db'    => false,
        'redis' => false,
        'queue' => false,
    ];

    try {
        DB::connection()->getPdo();
        $checks['db'] = true;
    } catch (\Exception $e) {}

    try {
        Cache::put('health_check', true, 10);
        $checks['redis'] = Cache::get('health_check') === true;
    } catch (\Exception $e) {}

    $checks['queue'] = config('queue.default') === 'redis';

    $allOk = !in_array(false, $checks);

    return response()->json([
        'status' => $allOk ? 'ok' : 'degraded',
        'checks' => $checks,
        'php'    => PHP_VERSION,
        'laravel' => app()->version(),
    ], $allOk ? 200 : 503);
});
```

---

## ÉTAPE 6 — Déployer sur o2switch et tester

```bash
# Sur le serveur o2switch
git clone <repo> leopardo-proto
cd leopardo-proto
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan config:cache

# Tester depuis l'extérieur
curl https://api.leopardo-rh.com/api/v1/health
```

**Réponse attendue :**
```json
{
  "status": "ok",
  "checks": { "db": true, "redis": true, "queue": true },
  "php": "8.3.x",
  "laravel": "11.x"
}
```

---

## ÉTAPE 7 — Test Supervisor (queue worker)

```bash
# Démarrer un worker manuellement
php artisan queue:work redis --tries=3 &

# Pousser un job test
php artisan tinker
```

```php
dispatch(function() {
    \Log::info('Queue worker fonctionne : ' . now());
})->onQueue('default');
```

```bash
# Vérifier les logs
tail -f storage/logs/laravel.log
# Doit afficher : "Queue worker fonctionne : ..."
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE

```
[ ] GET /api/v1/health retourne {"status":"ok"} depuis une machine externe
[ ] PostgreSQL : switch de schéma fonctionne (étape 3 verte)
[ ] Redis : cache + queue opérationnels (étape 4 verte)
[ ] Queue : job dispatché et exécuté (log visible dans étape 7)
[ ] PHP 8.3 confirmé sur le serveur de prod
[ ] Temps de réponse /health < 500ms
```

**Si un seul critère est rouge → NE PAS commencer CC-01.**
Résoudre le problème d'infrastructure d'abord. C'est la règle.

---

## CE QUI SUIT

Quand tous les critères sont verts :
1. Supprimer le prototype (`rm -rf leopardo-proto`)
2. Mettre à jour CONTINUE.md : Infrastructure ✅
3. Passer à **CC-01_INIT_LARAVEL.md**

---

## COMMIT

```
chore: validate o2switch infrastructure — PostgreSQL schema switch + Redis + Queue OK
```
