# QA Leopardo RH — Session expert #16 (v3) du 2026-08-16 — CI Backend débloquée

## Nouveautés (v3)

### Outillage backend local complet
PHP 8.4.24 (PPA ondrej) + Composer + PostgreSQL 16 + Redis installés dans le
sandbox → validation backend locale possible pour la première fois
(PHPStan, Pint, PHPUnit ciblés).

### CI Backend débloquée (2 blocages racine)
1. **#4110** : `composer.lock` désynchronisé (content-hash obsolète depuis la
   contrainte PHP ^8.4.1) → TOUS les jobs Backend échouaient avant
   Pint/PHPStan/PHPUnit. Fix : `composer update --lock` (diff = 1 ligne),
   mergé (#4150). PR doublon #4147 fermée avec renvoi.
2. **#4120** : gate PHPStan strict — 86 erreurs sur main. #4108 (10 erreurs
   réelles + baseline régénéré) mergé, puis **15 erreurs restantes corrigées**
   (2 fichiers de test : typages factory/propriétés, tearDown inexistant,
   types itérables, false-safe openssl) → `phpstan-strict.neon` **[OK] No errors**.

### Bugs masqués par le lock cassé, détectés via exécution locale
3. **#4159** : `OidcIdTokenValidatorDerTest` — `openssl_sign()` retourne bool
   (true) depuis PHP 8.4 → `assertSame(1, ...)` rouge ; `assertSame(1, ord() &
   0x80)` toujours fausse (MSB set = 128). `openssl_verify()` reste int → code
   prod OK. PR #4160 mergé, test 3/3.
4. **#3147 volet** : `TestRtspSsidGuardTest` — TEST-NET-3 (203.0.113.0/24,
   RFC 5737) classé « public » alors que le guard anti-SSRF le bloque à
   raison ; `camera.example.com` NXDOMAIN partout (y compris CI) → remplacé
   par `www.example.com`. PR #4193, 16/16.

### Issues implémentées et mergées (v2+v3 : 11)
#3965 #3966 #3972 #3958 #3862 #4102 #3876 (validate-and-sync vert) #4110
#4120 #4159 #3147 — + #3843 clôturée avec preuve, + merge #4108.

## Leçons
1. Le composer.lock content-hash obsolète masquait TOUTE la CI backend — et
   donc tous les tests cassés par les merges récents (OIDC, RTSP).
2. PHP 8.4 : `openssl_sign()` → bool, `openssl_verify()` → int|false —
   toujours vérifier le retour réel avant d'écrire des assertions.
3. Un « hôte public » de test doit résoudre partout : utiliser les domaines
   RFC 2606 avec A record garanti (www.example.com), jamais un sous-domaine
   inventé (NXDOMAIN en CI aussi).
4. Les plages de documentation RFC 5737 sont des cibles privées pour un
   guard anti-SSRF (bloquer par défaut).
