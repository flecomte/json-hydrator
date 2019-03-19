SELECT json_object_agg(filename, h)
FROM migration.history h
GROUP BY h.filename
ORDER BY h.filename DESC;