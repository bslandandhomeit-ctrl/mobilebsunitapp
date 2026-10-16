import importlib.util
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
u="'unified_beloan_customer_ui'@'%'"
m.target("CREATE OR REPLACE VIEW beloan_ui_contact_override AS SELECT * FROM customer_account_evidence WHERE source_table='unified_contact_override' WITH CHECK OPTION;")
for t in ['customer_accounts','beloan_actor_aliases','beloan_ui_account_sequence','beloan_ui_contact_override']:
 m.target(f'GRANT SELECT ON {t} TO {u}')
for t in ['beloan_ui_customer_profiles','beloan_ui_client_cbc_general','beloan_ui_client_cbc_employer','beloan_ui_client_cbc_spouse','beloan_ui_audit']:
 m.target(f'GRANT INSERT,UPDATE ON {t} TO {u}')
# The original form replaces only mutable account-profile child rows. No deletion of master accounts, persons, historical evidence or finance rows is granted.
for t in ['beloan_ui_client_cbc_contact','beloan_ui_client_cbc_address','beloan_ui_client_cbc_identification','beloan_ui_save_draft']:
 m.target(f'GRANT INSERT,UPDATE,DELETE ON {t} TO {u}')
m.target(f'GRANT INSERT ON customer_accounts TO {u};GRANT UPDATE(display_name,account_number,identity_review_state) ON customer_accounts TO {u};GRANT UPDATE(next_id) ON beloan_ui_account_sequence TO {u};GRANT INSERT ON customer_account_change_audit TO {u};GRANT INSERT,UPDATE ON beloan_ui_contact_override TO {u};')
print('Customer-only local writer prepared; financial and master/person deletes are not granted.')
