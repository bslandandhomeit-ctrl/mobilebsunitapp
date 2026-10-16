import importlib.util,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
raw=['clients','client_cbc_general','client_cbc_address','client_cbc_contact','client_cbc_identification','client_cbc_employer','client_cbc_spouse','cbc_identification_types','economic_cbc_sector','save_draft','audit']
for t in raw:
 name='beloan_ui_'+('customer_profiles' if t=='clients' else t)
 if not m.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"+name+"'"):
  m.target('CREATE TABLE '+name+' LIKE beloan_migration_stage.tb_'+t+'; INSERT INTO '+name+' SELECT * FROM beloan_migration_stage.tb_'+t+';')
cols=[r.split('\t')[0] for r in m.target('SHOW COLUMNS FROM beloan_ui_customer_profiles')]
select=['a.display_name AS client_name' if c=='client_name' else 'a.account_number AS cus_acc' if c=='cus_acc' else 'p.`'+c+'`' for c in cols]
m.target('CREATE OR REPLACE VIEW beloan_ui_clients AS SELECT '+','.join(select)+' FROM beloan_ui_customer_profiles p JOIN customer_accounts a ON a.id=p.id;')
missing=int(m.target("SELECT COUNT(*) FROM beloan_ui_customer_profiles p LEFT JOIN customer_accounts a ON a.id=p.id AND a.source_system='beloan' AND a.source_id=p.id WHERE a.id IS NULL")[0])
if missing:raise RuntimeError('Original-ID coverage failed')
m.target("CREATE USER IF NOT EXISTS 'unified_beloan_customer_ui'@'%' IDENTIFIED BY 'local-only-unified-customer-ui';")
for name in ['beloan_ui_'+('customer_profiles' if t=='clients' else t) for t in raw]+['beloan_ui_clients']:
 m.target("GRANT SELECT ON "+name+" TO 'unified_beloan_customer_ui'@'%';")
m.target("CREATE OR REPLACE VIEW beloan_ui_account_sequence AS SELECT entity,next_id FROM unified_id_allocators WHERE entity='customer_accounts' WITH CHECK OPTION;")
print(json.dumps({'status':'READ_PROFILE_PREPARED','profile_tables':len(raw),'unmapped_original_accounts':missing,'scope':'SELECT-only account profile extension reader. No write/delete grants.'},indent=2))
