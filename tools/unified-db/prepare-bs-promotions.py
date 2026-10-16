"""Preserve authoritative BS promotion definitions and bindings in the local unified DB."""
import importlib.util,json
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
tables=['discount_promotions','discount_promotion_items']
existing=m.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='bsunits_beloan_final' AND TABLE_NAME IN ('discount_promotions','discount_promotion_items')")
if existing and set(existing)!=set(tables):raise RuntimeError('Partial promotion schema; refusing automatic overwrite')
if not existing:
 for t in tables:m.target('CREATE TABLE '+t+' LIKE bsunits_migration_stage.'+t)
 m.target('START TRANSACTION;'+''.join('INSERT INTO '+t+' SELECT * FROM bsunits_migration_stage.'+t+';' for t in tables)+'COMMIT;')
checks={}
for t in tables:
 cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bsunits_migration_stage' AND TABLE_NAME="+m.literal(t)+' ORDER BY ORDINAL_POSITION')
 row='JSON_ARRAY('+','.join('`'+c+'`' for c in cols)+')'
 query=lambda schema:'SET SESSION group_concat_max_len=16777216;SELECT COUNT(*),SHA2(GROUP_CONCAT(SHA2('+row+',256) ORDER BY id SEPARATOR \'\'),256) FROM '+schema+'.'+t
 before=m.target(query('bsunits_migration_stage'));after=m.target(query('bsunits_beloan_final'))
 if before!=after:raise RuntimeError('Promotion preservation mismatch; no application activation')
 checks[t]={'rows':int(after[0].split('\t')[0]),'all_column_checksum_matches':True}
unresolved=int(m.target('SELECT COUNT(*) FROM discount_promotion_items i LEFT JOIN discount_promotions p ON p.id=i.discount_promotion_id LEFT JOIN unit_types t ON t.id=i.unit_type_id WHERE p.id IS NULL OR t.id IS NULL')[0])
if unresolved:raise RuntimeError('Unresolved promotion binding references')
report={'status':'PASSED','local_only':True,'source':'BS Units migration snapshot','tables':checks,'unresolved_bindings':unresolved,'runtime_grants_changed':False,'application_flags_enabled':False}
(HERE.parents[1]/'outputs/unified-db/promotion-preservation-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
