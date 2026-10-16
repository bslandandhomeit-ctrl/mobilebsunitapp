-- Local rehearsal account; SELECT only on public catalog tables.
CREATE USER IF NOT EXISTS 'unified_catalog_reader'@'%' IDENTIFIED BY 'local-only-unified-reader';
GRANT SELECT ON bsunits_beloan_final.projects TO 'unified_catalog_reader'@'%';
GRANT SELECT ON bsunits_beloan_final.unit_types TO 'unified_catalog_reader'@'%';
GRANT SELECT ON bsunits_beloan_final.units TO 'unified_catalog_reader'@'%';
GRANT SELECT ON bsunits_beloan_final.unit_actions TO 'unified_catalog_reader'@'%';
