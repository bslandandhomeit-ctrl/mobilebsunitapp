import importlib.util,subprocess,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
db='unified_original_customer_ui_test';user="'unified_beloan_customer_ui'@'%'"
if m.target("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+db+"'"):raise RuntimeError('Scratch exists; refusing overwrite.')
tables=['beloan_ui_customer_profiles','beloan_ui_client_cbc_general','beloan_ui_client_cbc_contact','beloan_ui_client_cbc_address','beloan_ui_client_cbc_identification','beloan_ui_client_cbc_employer','beloan_ui_client_cbc_spouse','beloan_ui_save_draft','beloan_ui_audit','customer_accounts','customer_account_evidence','customer_account_change_audit','unified_id_allocators']
try:
 m.target('CREATE DATABASE '+db)
 for t in tables:m.target(f'CREATE TABLE {db}.{t} LIKE {t}')
 m.target(f'INSERT INTO {db}.beloan_ui_customer_profiles SELECT * FROM beloan_ui_customer_profiles WHERE id=1;INSERT INTO {db}.customer_accounts SELECT * FROM customer_accounts WHERE id=1;')
 for t in ['beloan_ui_client_cbc_general','beloan_ui_client_cbc_contact','beloan_ui_client_cbc_address','beloan_ui_client_cbc_identification']:
  m.target(f'INSERT INTO {db}.{t} SELECT * FROM {t} WHERE client_id=1;')
 m.target(f"INSERT INTO {db}.unified_id_allocators(entity,next_id) VALUES('customer_accounts',1000000);CREATE VIEW {db}.beloan_ui_account_sequence AS SELECT entity,next_id FROM {db}.unified_id_allocators WHERE entity='customer_accounts' WITH CHECK OPTION;CREATE TABLE {db}.beloan_actor_aliases AS SELECT * FROM beloan_actor_aliases;")
 m.target(f"CREATE VIEW {db}.beloan_ui_contact_override AS SELECT * FROM {db}.customer_account_evidence WHERE source_table='unified_contact_override' WITH CHECK OPTION;GRANT SELECT,INSERT,UPDATE ON {db}.beloan_ui_contact_override TO {user}")
 cols=[r.split('\t')[0] for r in m.target('SHOW COLUMNS FROM beloan_ui_customer_profiles')];fields=['a.display_name AS client_name' if c=='client_name' else 'a.account_number AS cus_acc' if c=='cus_acc' else 'p.`'+c+'`' for c in cols]
 m.target(f'CREATE VIEW {db}.beloan_ui_clients AS SELECT '+','.join(fields)+f' FROM {db}.beloan_ui_customer_profiles p JOIN {db}.customer_accounts a ON a.id=p.id')
 for t in tables+['beloan_ui_clients','beloan_actor_aliases','beloan_ui_account_sequence','beloan_ui_contact_override']:m.target(f'GRANT SELECT ON {db}.{t} TO {user}')
 for t in ['beloan_ui_customer_profiles','beloan_ui_client_cbc_general','beloan_ui_audit','customer_account_evidence']:m.target(f'GRANT INSERT,UPDATE ON {db}.{t} TO {user}')
 for t in ['beloan_ui_client_cbc_contact','beloan_ui_client_cbc_address','beloan_ui_client_cbc_identification','beloan_ui_save_draft']:m.target(f'GRANT INSERT,UPDATE,DELETE ON {db}.{t} TO {user}')
 m.target(f'GRANT INSERT ON {db}.customer_accounts TO {user};GRANT UPDATE(display_name,account_number,identity_review_state) ON {db}.customer_accounts TO {user};GRANT UPDATE(next_id) ON {db}.beloan_ui_account_sequence TO {user};GRANT INSERT ON {db}.customer_account_change_audit TO {user}')
 result=subprocess.run(['docker','exec','-i','beloan-local','php'],input=Path(__file__).with_name('test-original-customer-write.php').read_text(),text=True,capture_output=True)
 if result.returncode:raise RuntimeError(result.stderr.strip() or 'Original customer write test failed.')
 report=json.loads(result.stdout);Path('outputs/unified-db/original-customer-write-test-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
finally:
 for t in tables+['beloan_ui_clients','beloan_actor_aliases','beloan_ui_account_sequence','beloan_ui_contact_override']:
  try:m.target(f'REVOKE ALL PRIVILEGES ON {db}.{t} FROM {user}')
  except RuntimeError:pass
 m.target('DROP DATABASE IF EXISTS '+db)
