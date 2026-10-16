-- User decision: BS Units has the latest current unit list prices.
-- This resolves a field choice, not entity identity or historical financial amounts.
CREATE TABLE IF NOT EXISTS migration_field_resolutions (
  run_id CHAR(36) NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  candidate_key CHAR(64) NOT NULL,
  field_name VARCHAR(100) NOT NULL,
  source_system VARCHAR(20) NOT NULL,
  source_table VARCHAR(100) NOT NULL,
  source_id VARCHAR(100) NOT NULL,
  chosen_value JSON NOT NULL,
  reason VARCHAR(500) NOT NULL,
  resolved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(run_id,entity_type,candidate_key,field_name),
  FOREIGN KEY(run_id,entity_type,candidate_key)
    REFERENCES migration_match_groups(run_id,entity_type,candidate_key)
) ENGINE=InnoDB;

SET @price_run = (SELECT id FROM migration_runs
  WHERE status='READY_FOR_REVIEW' AND domain_scope='PROPERTY' ORDER BY completed_at DESC LIMIT 1);

START TRANSACTION;
INSERT IGNORE INTO migration_field_resolutions
  (run_id,entity_type,candidate_key,field_name,source_system,source_table,
   source_id,chosen_value,reason)
SELECT s.run_id,s.entity_type,s.candidate_key,'list_price',s.source_system,
       s.source_table,s.source_id,JSON_EXTRACT(s.source_payload,'$.price'),
       'User confirmed BS Units prices are the latest. Historical sale/contract/loan amounts are excluded.'
FROM migration_source_records s
JOIN migration_match_groups g ON g.run_id=s.run_id
  AND g.entity_type=s.entity_type AND g.candidate_key=s.candidate_key
WHERE s.run_id=@price_run AND s.entity_type='unit' AND s.source_system='bsunits'
  AND (g.classification IN ('UNAMBIGUOUS','SOURCE_ONLY') OR (g.classification='AMBIGUOUS' AND g.decision='MERGE'))
  AND JSON_UNQUOTE(JSON_EXTRACT(s.source_payload,'$.price')) REGEXP '^[0-9]+([.][0-9]+)?$';
COMMIT;

INSERT IGNORE INTO schema_versions(version) VALUES ('002_bsunits_current_price_policy');

SELECT 'price_resolutions',COUNT(*) FROM migration_field_resolutions WHERE run_id=@price_run AND field_name='list_price';
SELECT 'resolved_price_conflict_groups',COUNT(*)
FROM migration_field_resolutions r JOIN migration_match_groups g
  ON g.run_id=r.run_id AND g.entity_type=r.entity_type AND g.candidate_key=r.candidate_key
WHERE r.run_id=@price_run AND r.field_name='list_price' AND JSON_CONTAINS_PATH(g.field_conflicts,'one','$.price');
SELECT 'ambiguous_groups_kept_pending',COUNT(*) FROM migration_match_groups
WHERE run_id=@price_run AND entity_type='unit' AND classification='AMBIGUOUS' AND decision='PENDING';
