-- User confirmed BS Units has the latest unit details.
-- Physical details only: no availability/deletion state or historical money changes.
SET @detail_run = (SELECT id FROM migration_runs
  WHERE status='READY_FOR_REVIEW' AND domain_scope='PROPERTY'
  ORDER BY completed_at DESC LIMIT 1);

START TRANSACTION;
INSERT IGNORE INTO migration_field_resolutions
  (run_id,entity_type,candidate_key,field_name,source_system,source_table,
   source_id,chosen_value,reason)
SELECT s.run_id,s.entity_type,s.candidate_key,f.field_name,s.source_system,
       s.source_table,s.source_id,
       JSON_EXTRACT(s.source_payload,CONCAT('$.',f.field_name)),
       'User confirmed BS Units has the latest unit details. Availability and historical financial terms are excluded.'
FROM migration_source_records s
JOIN migration_match_groups g ON g.run_id=s.run_id
  AND g.entity_type=s.entity_type AND g.candidate_key=s.candidate_key
CROSS JOIN (
  SELECT 'street' field_name UNION ALL SELECT 'street_corner'
  UNION ALL SELECT 'street_size' UNION ALL SELECT 'floor'
  UNION ALL SELECT 'land_size_width' UNION ALL SELECT 'land_size_length'
  UNION ALL SELECT 'land_area' UNION ALL SELECT 'building_size_width'
  UNION ALL SELECT 'building_size_length' UNION ALL SELECT 'building_area'
  UNION ALL SELECT 'gross_area' UNION ALL SELECT 'total_area_size'
  UNION ALL SELECT 'living_room' UNION ALL SELECT 'kitchen'
  UNION ALL SELECT 'bedroom' UNION ALL SELECT 'bathroom'
  UNION ALL SELECT 'swimming_pool' UNION ALL SELECT 'block'
  UNION ALL SELECT 'unit_zone' UNION ALL SELECT 'construction_overall_progress'
) f
WHERE s.run_id=@detail_run AND s.entity_type='unit' AND s.source_system='bsunits'
  AND (g.classification IN ('UNAMBIGUOUS','SOURCE_ONLY') OR (g.classification='AMBIGUOUS' AND g.decision='MERGE'))
  AND JSON_CONTAINS_PATH(s.source_payload,'one',CONCAT('$.',f.field_name));
COMMIT;

INSERT IGNORE INTO schema_versions(version) VALUES ('003_bsunits_current_unit_details');

SELECT 'units_with_physical_detail_selections',COUNT(DISTINCT candidate_key)
FROM migration_field_resolutions WHERE run_id=@detail_run AND field_name='floor';
SELECT 'selected_physical_fields',COUNT(*) FROM migration_field_resolutions
WHERE run_id=@detail_run AND field_name<>'list_price';
SELECT 'mismatched_selections',COUNT(*)
FROM migration_field_resolutions r JOIN migration_source_records s
  ON s.run_id=r.run_id AND s.source_system=r.source_system
  AND s.source_table=r.source_table AND s.source_id=r.source_id
WHERE r.run_id=@detail_run AND r.field_name<>'list_price'
  AND NOT(SHA2(r.chosen_value,256) <=> SHA2(JSON_EXTRACT(s.source_payload,CONCAT('$.',r.field_name)),256));
