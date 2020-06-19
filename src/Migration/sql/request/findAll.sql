SELECT json_object_agg(m.filename, m) as resource
FROM (
    SELECT h.*
    FROM migration.history h
    GROUP BY h.filename
    ORDER BY h.filename DESC
) m;