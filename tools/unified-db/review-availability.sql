-- Snapshot of conflicting current availability. No action flags are enabled here.
CREATE TABLE IF NOT EXISTS migration_unit_availability_review (
 unit_id BIGINT UNSIGNED PRIMARY KEY,
 bs_saleable TINYINT NULL,
 beloan_available_rows INT NOT NULL,
 beloan_blocking_rows INT NOT NULL,
 open_contracts INT NOT NULL,
 active_deposits INT NOT NULL,
 active_holds INT NOT NULL,
 suggested_state ENUM('AVAILABLE','UNAVAILABLE','REVIEW_REQUIRED') NOT NULL,
 decision ENUM('PENDING','APPLIED') NOT NULL DEFAULT 'PENDING',
 evidence_as_of TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(unit_id) REFERENCES units(id)
) ENGINE=InnoDB;
INSERT INTO migration_unit_availability_review
(unit_id,bs_saleable,beloan_available_rows,beloan_blocking_rows,open_contracts,active_deposits,active_holds,suggested_state)
SELECT u.id,bs.saleable,COALESCE(be.available_count,0),COALESCE(be.blocking_count,0),
 COALESCE(c.open_count,0),COALESCE(d.active_count,0),COALESCE(h.active_count,0),
 CASE
 WHEN u.catalog_state='LEGACY_ONLY' THEN 'REVIEW_REQUIRED'
 WHEN bs.saleable=1 AND (COALESCE(be.blocking_count,0)>0 OR COALESCE(c.open_count,0)>0 OR COALESCE(d.active_count,0)>0 OR COALESCE(h.active_count,0)>0) THEN 'REVIEW_REQUIRED'
 WHEN bs.saleable=0 THEN 'UNAVAILABLE'
 WHEN bs.saleable=1 THEN 'AVAILABLE'
 ELSE 'REVIEW_REQUIRED' END
FROM units u
LEFT JOIN bsunits_migration_stage.units bs ON bs.id=u.id AND u.catalog_state='CURRENT'
LEFT JOIN (
 SELECT m.target_id,
 SUM(b.status='available') available_count,
 SUM(b.status IS NULL OR b.status<>'available') blocking_count
 FROM source_record_map m JOIN beloan_migration_stage.tb_units b
 ON m.source_id=(CAST(b.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)
 WHERE m.source_system='beloan' AND m.source_table='tb_units'
 GROUP BY m.target_id
) be ON be.target_id=u.id
LEFT JOIN (SELECT unit_id,COUNT(*) open_count FROM contracts WHERE status='OPEN' GROUP BY unit_id) c ON c.unit_id=u.id
LEFT JOIN (SELECT unit_id,COUNT(*) active_count FROM unit_deposit_requests WHERE status IN ('OPEN','PENDING','APPROVED') GROUP BY unit_id) d ON d.unit_id=u.id
LEFT JOIN (SELECT unit_id,COUNT(*) active_count FROM unit_hold_requests WHERE status IN ('OPEN','PENDING','APPROVED') AND release_date>NOW() GROUP BY unit_id) h ON h.unit_id=u.id;
