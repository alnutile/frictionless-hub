-- A separate database for running the test suite against Postgres,
-- so tests never touch the development data.
SELECT 'CREATE DATABASE testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec
