-- Collapse duplicate property IDs only when the unique BS Units counterpart
-- exists and none of the duplicate Beloan rows is referenced by financial workflows.
SET @review_run = (SELECT id FROM migration_runs WHERE domain_scope='PROPERTY'
  AND status='READY_FOR_REVIEW' ORDER BY completed_at DESC LIMIT 1);
SET @promotion_run = (SELECT id FROM migration_runs WHERE domain_scope='PROPERTY_PROMOTION'
  AND status='READY_FOR_REVIEW' ORDER BY completed_at DESC LIMIT 1);

CREATE TEMPORARY TABLE safe_duplicate_unit_aliases AS
SELECT b.source_id AS beloan_id, bm.target_id AS canonical_id,g.candidate_key
FROM migration_match_groups g
JOIN migration_source_records a ON a.run_id=g.run_id AND a.entity_type='unit'
  AND a.candidate_key=g.candidate_key AND a.source_system='bsunits'
JOIN source_record_map bm ON bm.source_system='bsunits' AND bm.source_table='units' AND bm.source_id=a.source_id
JOIN migration_source_records b ON b.run_id=g.run_id AND b.entity_type='unit'
  AND b.candidate_key=g.candidate_key AND b.source_system='beloan'
WHERE g.run_id=@review_run AND g.entity_type='unit' AND g.classification='AMBIGUOUS'
  AND (SELECT COUNT(*) FROM migration_source_records x WHERE x.run_id=g.run_id
    AND x.entity_type='unit' AND x.candidate_key=g.candidate_key AND x.source_system='bsunits')=1
  AND NOT EXISTS (
    SELECT 1 FROM migration_source_records x WHERE x.run_id=g.run_id AND x.entity_type='unit'
      AND x.candidate_key=g.candidate_key AND x.source_system='beloan'
      AND (
        EXISTS(SELECT 1 FROM beloan_migration_stage.tb_loans l WHERE l.unit_id=CAST(x.source_id AS UNSIGNED))
        OR EXISTS(SELECT 1 FROM beloan_migration_stage.tb_sale_items s WHERE s.unit_id=CAST(x.source_id AS UNSIGNED))
        OR EXISTS(SELECT 1 FROM beloan_migration_stage.tb_client_loan_accounts l WHERE l.unit_id=CAST(x.source_id AS UNSIGNED))
        OR EXISTS(SELECT 1 FROM beloan_migration_stage.tb_drawdown_account d WHERE d.unit_id=CAST(x.source_id AS UNSIGNED))
      )
  );

START TRANSACTION;
INSERT IGNORE INTO source_record_map
  (source_system,source_table,source_id,entity_type,target_id,run_id,mapping_basis)
SELECT 'beloan','tb_units',beloan_id,'unit',canonical_id,@promotion_run,'RECONCILED_UNUSED_DUPLICATE'
FROM safe_duplicate_unit_aliases;

UPDATE migration_match_groups g
JOIN (SELECT DISTINCT candidate_key FROM safe_duplicate_unit_aliases) s ON s.candidate_key=g.candidate_key
SET decision='MERGE',reviewed_by='migration_rule:unused_duplicate',reviewed_at=CURRENT_TIMESTAMP,
  review_reason='Same project/type/unit code and one BS Units master. No loan, sale item, loan account or drawdown references on duplicate Beloan rows. Original values retained; availability remains unverified.'
WHERE g.run_id=@review_run AND g.entity_type='unit';
COMMIT;

SELECT 'safe_duplicate_rows_mapped',COUNT(*) FROM safe_duplicate_unit_aliases;
SELECT 'groups_resolved',COUNT(DISTINCT candidate_key) FROM safe_duplicate_unit_aliases;
SELECT 'beloan_units_without_mapping',COUNT(*) FROM beloan_migration_stage.tb_units u
LEFT JOIN source_record_map m ON m.source_system='beloan' AND m.source_table='tb_units' AND m.source_id=(CAST(u.id AS CHAR) COLLATE utf8mb4_unicode_ci)
WHERE m.source_id IS NULL;
