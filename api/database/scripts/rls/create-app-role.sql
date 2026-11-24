-- =============================================================================
-- #9896 — ADR-9879, étape 2 : rôle applicatif NOSUPERUSER NOBYPASSRLS
-- =============================================================================
--
-- ## Pourquoi ce script existe
--
-- PostgreSQL fait TOUJOURS bypasser RLS aux rôles SUPERUSER et BYPASSRLS,
-- MÊME avec `FORCE ROW LEVEL SECURITY`. Mesure dans ce dépôt : le rôle
-- applicatif (`leopardo`) est SUPERUSER. Les politiques posées par #9879 et
-- #9889 existent donc, mais elles ne protègent RIEN tant que l'application
-- se connecte avec ce rôle.
--
-- Vérifier l'état courant avant toute chose :
--
--     php artisan tenant:rls-status
--
-- Sortie 1 = « RLS annoncé mais bypassé ». C'est l'état à corriger ici.
--
-- ## Ce que ce script NE fait pas
--
-- Il ne bascule pas `DB_USERNAME`. C'est une décision d'exploitation, prise
-- après vérification, et réversible en changeant une variable d'environnement
-- (voir « Rollback »). Un script de migration ne doit pas pouvoir couper
-- l'accès à la base d'un déploiement en cours.
--
-- ## Exécution
--
--     psql "$DATABASE_URL" -v app_password="'<secret>'" -f create-app-role.sql
--
-- Le mot de passe est passé en variable : il ne doit jamais être écrit dans
-- ce fichier, qui est versionné.
--
-- Idempotent : réexécutable sans erreur.
-- =============================================================================

\set ON_ERROR_STOP on

-- -----------------------------------------------------------------------------
-- 1) Le rôle applicatif
-- -----------------------------------------------------------------------------
-- NOSUPERUSER  : sans quoi RLS est bypassé (le point de tout ce script).
-- NOBYPASSRLS  : idem — un rôle non-superuser peut quand même porter BYPASSRLS.
-- NOCREATEDB / NOCREATEROLE : moindre privilège, l'application n'en a pas besoin.
-- NOINHERIT absent volontairement : le rôle n'est membre d'aucun autre rôle.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'leopardo_app') THEN
        EXECUTE format(
            'CREATE ROLE leopardo_app LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE PASSWORD %L',
            :'app_password'
        );
        RAISE NOTICE 'Rôle leopardo_app créé.';
    ELSE
        -- Réexécution : on réaffirme les attributs de sécurité. Un rôle créé
        -- à la main puis promu SUPERUSER par erreur serait sinon laissé tel
        -- quel, et RLS resterait bypassé sans que personne ne le voie.
        ALTER ROLE leopardo_app NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
        RAISE NOTICE 'Rôle leopardo_app existant : attributs de sécurité réaffirmés.';
    END IF;
END
$$;

-- -----------------------------------------------------------------------------
-- 2) Droits sur les schémas et les tables
-- -----------------------------------------------------------------------------
-- `shared_tenants` porte les tables tenant ; `public` porte `companies` et
-- les tables plateforme dont l'application a besoin en lecture.

GRANT USAGE ON SCHEMA shared_tenants, public TO leopardo_app;

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA shared_tenants TO leopardo_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO leopardo_app;

GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA shared_tenants TO leopardo_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO leopardo_app;

-- Les tables créées PLUS TARD par le rôle propriétaire (celui des migrations)
-- doivent aussi être accessibles. Sans `ALTER DEFAULT PRIVILEGES`, chaque
-- nouvelle migration produirait une table que l'application ne peut pas lire —
-- une panne au déploiement suivant, pas au moment de la bascule.
ALTER DEFAULT PRIVILEGES IN SCHEMA shared_tenants
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO leopardo_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO leopardo_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA shared_tenants
    GRANT USAGE, SELECT ON SEQUENCES TO leopardo_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO leopardo_app;

-- -----------------------------------------------------------------------------
-- 3) Vérification AVANT bascule — doit renvoyer 0 ligne
-- -----------------------------------------------------------------------------
-- Si cette requête renvoie des lignes, la bascule rendrait ces tables
-- ILLISIBLES (fail-closed) : la politique existe mais aucune ligne ne
-- satisfait la comparaison. Corriger AVANT de changer DB_USERNAME.

\echo ''
\echo '== Vérification 1 : le rôle ne doit PAS bypasser RLS =='
SELECT rolname, rolsuper, rolbypassrls
  FROM pg_roles
 WHERE rolname = 'leopardo_app'
   AND (rolsuper OR rolbypassrls);

\echo ''
\echo '== Vérification 2 : tables attendues couvertes (ENABLE + FORCE + policy) =='
SELECT c.relname,
       c.relrowsecurity      AS rls_enabled,
       c.relforcerowsecurity AS rls_forced,
       (SELECT count(*) FROM pg_policy p WHERE p.polrelid = c.oid) AS policies
  FROM pg_class c
  JOIN pg_namespace n ON n.oid = c.relnamespace
 WHERE n.nspname = 'shared_tenants'
   AND c.relname IN ('bulk_payment_claims', 'pay_slips', 'payroll_runs', 'employees')
 ORDER BY c.relname;

-- -----------------------------------------------------------------------------
-- 4) Bascule (hors de ce script)
-- -----------------------------------------------------------------------------
--   DB_USERNAME=leopardo_app
--   DB_PASSWORD=<le secret>
--   puis redéploiement / redémarrage des workers.
--
-- Vérification APRÈS bascule :
--
--   php artisan tenant:rls-status          # doit sortir 0
--   SELECT count(*) FROM shared_tenants.pay_slips;   -- sans tenant : 0
--
-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
--   DB_USERNAME=leopardo   (le rôle superuser d'origine)
--   puis redéploiement.
--
-- Aucune migration à défaire : les politiques restent en place et
-- redeviennent simplement inertes. Aucun risque de perte de données.
--
-- Le rôle peut rester créé (il ne gêne pas) ou être supprimé :
--
--   REASSIGN OWNED BY leopardo_app TO leopardo;   -- si des objets lui appartiennent
--   DROP OWNED BY leopardo_app;
--   DROP ROLE leopardo_app;
--
-- -----------------------------------------------------------------------------
-- Point d'attention : propriété des tables
-- -----------------------------------------------------------------------------
-- `leopardo_app` ne doit PAS être propriétaire des tables. S'il l'était,
-- `FORCE ROW LEVEL SECURITY` resterait indispensable (il est posé), et toute
-- table future créée par un autre rôle devrait recevoir les GRANT — d'où
-- `ALTER DEFAULT PRIVILEGES` ci-dessus, qui ne couvre que les objets créés
-- par le rôle qui exécute ce script. Si les migrations tournent sous un autre
-- rôle, rejouer la section 2 sous CE rôle.
