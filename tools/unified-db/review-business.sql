-- Read-only review of the latest customer/sale/contract/loan snapshot run.
SET @business_run = (SELECT id FROM migration_runs
  WHERE domain_scope='BUSINESS' AND status='READY_FOR_REVIEW'
  ORDER BY completed_at DESC LIMIT 1);

SELECT source_system,entity_type,source_table,COUNT(*) records
FROM migration_source_records WHERE run_id=@business_run
GROUP BY source_system,entity_type,source_table;

SELECT source_system,source_table,reference_column,reference_status,COUNT(*) records
FROM migration_source_relationships WHERE run_id=@business_run
GROUP BY source_system,source_table,reference_column,reference_status;

-- Missing references require investigation; EMPTY may be valid legacy state.
SELECT source_system,source_table,source_id,reference_column,target_source_table,reference_status
FROM migration_source_relationships
WHERE run_id=@business_run AND reference_status IN ('MISSING','AMBIGUOUS');

SELECT decision,COUNT(*) candidate_links FROM migration_customer_candidates
WHERE run_id=@business_run GROUP BY decision;

-- Source IDs and evidence only. Personal details remain in local source snapshots.
SELECT source_contract_id,buyer_number,beloan_client_id,evidence,decision
FROM migration_customer_candidates WHERE run_id=@business_run
ORDER BY source_contract_id,buyer_number LIMIT 100;
