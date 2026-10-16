"""Preserve source-qualified project links without duplicating project masters."""
import importlib.util
import json
from pathlib import Path

spec = importlib.util.spec_from_file_location('stage', Path(__file__).with_name('stage-properties.py'))
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
if int(m.target("SELECT COUNT(*) FROM (SELECT target_id FROM source_record_map WHERE source_system='beloan' AND source_table='tb_projects' GROUP BY target_id HAVING COUNT(*)>1) x")[0]):
    raise RuntimeError('Ambiguous historical project mapping')
m.target('CREATE TABLE IF NOT EXISTS beloan_project_reference_links (source_project_id BIGINT PRIMARY KEY, project_id BIGINT NOT NULL UNIQUE, bank_account BIGINT NULL, sale_representative_id BIGINT NULL)')
expected = "SELECT p.id,r.target_id,p.bank_account,p.sale_representative_id FROM beloan_migration_stage.tb_projects p JOIN source_record_map r ON r.source_system='beloan' AND r.source_table='tb_projects' AND BINARY r.source_id=BINARY CAST(p.id AS CHAR)"
if not int(m.target('SELECT COUNT(*) FROM beloan_project_reference_links')[0]):
    m.target('INSERT INTO beloan_project_reference_links '+expected)
mapping_differences = int(m.target("SELECT COUNT(*) FROM beloan_project_reference_links l LEFT JOIN source_record_map r ON r.source_system='beloan' AND r.source_table='tb_projects' AND BINARY r.source_id=BINARY CAST(l.source_project_id AS CHAR) WHERE r.target_id IS NULL OR r.target_id<>l.project_id")[0])
if mapping_differences:
    raise RuntimeError('Preserved project identity differs from canonical mapping')
missing = int(m.target('SELECT COUNT(*) FROM beloan_migration_stage.tb_projects p LEFT JOIN beloan_project_reference_links l ON l.source_project_id=p.id WHERE l.project_id IS NULL OR NOT(l.bank_account <=> p.bank_account) OR NOT(l.sale_representative_id <=> p.sale_representative_id)')[0])
if missing:
    raise RuntimeError('Historical project links missing or changed; refusing overwrite')
m.target("GRANT SELECT ON bsunits_beloan_final.beloan_project_reference_links TO 'unified_beloan_posting_reader'@'%'")
m.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW beloan_compat_project_banks AS SELECT d.*,l.project_id FROM beloan_reference_dealer_banks d JOIN beloan_project_reference_links l ON l.source_project_id=d.dealer_id')
m.target("GRANT SELECT ON bsunits_beloan_final.beloan_compat_project_banks TO 'unified_beloan_posting_reader'@'%'")
Path('outputs/unified-db/project-link-preservation.json').write_text(json.dumps({'status':'PRESERVED','rows':int(m.target('SELECT COUNT(*) FROM beloan_project_reference_links')[0]),'ambiguous_mappings':0,'historical_link_differences':missing,'scope':'Source-qualified bank and representative IDs; canonical project identity and latest BS fields unchanged. Read only, no application cutover.'},indent=2)+'\n')
print('Historical project reference links preserved and verified.')
