-- Record only identity decisions already represented by verified canonical maps.
-- These decisions do not resolve availability, financial ownership or customer data.
SET @identity_run = (SELECT id FROM migration_runs WHERE domain_scope='PROPERTY'
  AND status='READY_FOR_REVIEW' ORDER BY completed_at DESC LIMIT 1);

UPDATE migration_match_groups g
JOIN migration_source_records a ON a.run_id=g.run_id AND a.entity_type=g.entity_type
  AND a.candidate_key=g.candidate_key AND a.source_system='bsunits'
JOIN migration_source_records b ON b.run_id=g.run_id AND b.entity_type=g.entity_type
  AND b.candidate_key=g.candidate_key AND b.source_system='beloan'
JOIN source_record_map am ON am.source_system=a.source_system AND am.source_table=a.source_table AND am.source_id=a.source_id
JOIN source_record_map bm ON bm.source_system=b.source_system AND bm.source_table=b.source_table AND bm.source_id=b.source_id
SET g.decision='MERGE',g.reviewed_by='migration_rule:unique_property_identity',g.reviewed_at=CURRENT_TIMESTAMP,
  g.review_reason='Unique hierarchy/code match mapped to one canonical property identity. Latest BS Units price/details selected; historical values retained; availability and accounting ownership remain separate.'
WHERE g.run_id=@identity_run AND g.classification='UNAMBIGUOUS' AND g.decision='PENDING'
  AND am.entity_type=bm.entity_type AND am.target_id=bm.target_id;

UPDATE migration_match_groups g
JOIN migration_source_records s ON s.run_id=g.run_id AND s.entity_type=g.entity_type AND s.candidate_key=g.candidate_key
JOIN source_record_map m ON m.source_system=s.source_system AND m.source_table=s.source_table AND m.source_id=s.source_id
SET g.decision='KEEP_SEPARATE',g.reviewed_by='migration_rule:source_only_preserved',g.reviewed_at=CURRENT_TIMESTAMP,
  g.review_reason='Source-only identity retained as its own canonical record. Beloan-only units remain legacy-only with current list price unset and availability unverified.'
WHERE g.run_id=@identity_run AND g.classification='SOURCE_ONLY' AND g.decision='PENDING';

SELECT decision,COUNT(*) FROM migration_match_groups WHERE run_id=@identity_run GROUP BY decision;
