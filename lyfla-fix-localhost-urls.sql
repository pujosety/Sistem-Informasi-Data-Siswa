-- LYFLA production data repair: remove development hosts from landing CMS JSON.
--
-- Scope: landing_sections.content only.
-- Safety: no DROP, TRUNCATE, DELETE, or schema changes.
-- Idempotent: re-running after a successful repair updates zero rows.
-- Backup the target database before applying this in production.

START TRANSACTION;

SELECT COUNT(*) AS rows_with_local_urls_before
FROM landing_sections
WHERE content LIKE '%http://localhost%'
   OR content LIKE '%https://localhost%'
   OR content LIKE '%http://127.0.0.1%'
   OR content LIKE '%https://127.0.0.1%'
   OR content LIKE '%http://0.0.0.0%'
   OR content LIKE '%https://0.0.0.0%';

UPDATE landing_sections
SET content = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(content,
                                        'http://localhost:8000', ''),
                                    'https://localhost:8000', ''),
                                'http://localhost', ''),
                            'https://localhost', ''),
                        'http://127.0.0.1:8000', ''),
                    'https://127.0.0.1:8000', ''),
                'http://127.0.0.1', ''),
            'https://127.0.0.1', ''),
        'http://0.0.0.0:8000', ''),
    'https://0.0.0.0:8000', '')
WHERE content LIKE '%http://localhost%'
   OR content LIKE '%https://localhost%'
   OR content LIKE '%http://127.0.0.1%'
   OR content LIKE '%https://127.0.0.1%'
   OR content LIKE '%http://0.0.0.0%'
   OR content LIKE '%https://0.0.0.0%';

SELECT ROW_COUNT() AS updated_landing_sections;

SELECT COUNT(*) AS rows_with_local_urls_after
FROM landing_sections
WHERE content LIKE '%http://localhost%'
   OR content LIKE '%https://localhost%'
   OR content LIKE '%http://127.0.0.1%'
   OR content LIKE '%https://127.0.0.1%'
   OR content LIKE '%http://0.0.0.0%'
   OR content LIKE '%https://0.0.0.0%';

COMMIT;
