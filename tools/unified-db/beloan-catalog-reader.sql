CREATE OR REPLACE SQL SECURITY DEFINER VIEW bsunits_beloan_final.beloan_unit_aliases AS
 SELECT source_id,target_id FROM bsunits_beloan_final.source_record_map
 WHERE source_system='beloan' AND source_table='tb_units' AND entity_type='unit';
CREATE USER IF NOT EXISTS 'unified_beloan_catalog_reader'@'%' IDENTIFIED BY 'local-only-beloan-catalog-reader';
GRANT SELECT ON bsunits_beloan_final.beloan_unit_aliases TO 'unified_beloan_catalog_reader'@'%';
GRANT SELECT ON bsunits_beloan_final.units TO 'unified_beloan_catalog_reader'@'%';
GRANT SELECT ON bsunits_beloan_final.unit_types TO 'unified_beloan_catalog_reader'@'%';
GRANT SELECT ON bsunits_beloan_final.projects TO 'unified_beloan_catalog_reader'@'%';
