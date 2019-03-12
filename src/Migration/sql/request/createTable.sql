CREATE SCHEMA IF NOT EXISTS migration;
CREATE TABLE  IF NOT EXISTS migration.history (
  filename    text      PRIMARY KEY,
  executed_at timestamp DEFAULT now() NOT NULL,
  up          text                    NOT NULL,
  down        text                    NOT NULL,
  version     int                     NOT NULL
);

CREATE SEQUENCE IF NOT EXISTS migration.version_seq;
