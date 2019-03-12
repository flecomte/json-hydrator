CREATE SCHEMA IF NOT EXISTS migration;

CREATE TABLE  IF NOT EXISTS migration.functions (
  filename    text      PRIMARY KEY,
  definition  text                    NOT NULL,
  executed_at timestamp DEFAULT now() NOT NULL,
  up          text                    NOT NULL,
  down        text                    NOT NULL,
  version     int                     NOT NULL
);

CREATE SEQUENCE IF NOT EXISTS migration.version_seq;