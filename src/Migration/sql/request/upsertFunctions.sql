INSERT INTO migration.functions (filename, definition, up, down, version)
VALUES (:filename, :definition, :up, :down, :version)
ON CONFLICT (filename) DO UPDATE
  SET
    definition = excluded.definition,
    up = excluded.up,
    down = excluded.down,
    version = excluded.version;
