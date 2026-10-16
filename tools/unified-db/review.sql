-- Run in bsunits_beloan_final (DBeaver). This file is read-only.
SET @review_run = (SELECT id FROM migration_runs WHERE status='READY_FOR_REVIEW' AND domain_scope='PROPERTY' ORDER BY completed_at DESC LIMIT 1);

SELECT source_system, entity_type, COUNT(*) records
FROM migration_source_records WHERE run_id=@review_run
GROUP BY source_system,entity_type;

SELECT entity_type,classification,COUNT(*) groups,
       SUM(JSON_LENGTH(field_conflicts)>0) groups_with_compared_field_conflicts
FROM migration_match_groups WHERE run_id=@review_run
GROUP BY entity_type,classification;

-- Repeated keys requiring explicit identification before merging.
SELECT entity_type,members,field_conflicts,decision
FROM migration_match_groups
WHERE run_id=@review_run AND classification='AMBIGUOUS';

-- First 50 unit price disagreements. Members are source-qualified IDs.
SELECT members,JSON_EXTRACT(field_conflicts,'$.price') price_difference,decision
FROM migration_match_groups
WHERE run_id=@review_run AND entity_type='unit'
  AND JSON_CONTAINS_PATH(field_conflicts,'one','$.price')
ORDER BY candidate_key LIMIT 50;

-- Full source snapshots for any selected match group are stored separately.
-- Set @candidate to a candidate_key from the group you want to inspect.
-- SELECT source_system,source_table,source_id,source_payload
-- FROM migration_source_records
-- WHERE run_id=@review_run AND candidate_key=@candidate;
