DO
$$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_roles
        WHERE rolname = 'leopardo_user'
    ) THEN
        CREATE ROLE leopardo_user LOGIN PASSWORD 'leopardo_pass_test';
    END IF;
END
$$;

SELECT 'CREATE DATABASE leopardo_test OWNER leopardo_user'
WHERE NOT EXISTS (
    SELECT 1
    FROM pg_database
    WHERE datname = 'leopardo_test'
)\gexec

GRANT ALL PRIVILEGES ON DATABASE leopardo_test TO leopardo_user;
