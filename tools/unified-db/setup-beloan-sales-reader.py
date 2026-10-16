import importlib.util
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
m.target("CREATE USER IF NOT EXISTS 'unified_beloan_sales_reader'@'%' IDENTIFIED BY 'local-only-unified-sales-reader'")
for t in ['sales','sale_items','customer_accounts','units','unit_types','projects','branches','currencies','approved_deposit_sale_links','approved_deposit_sale_terms','customer_account_members','persons']:
 m.target(f"GRANT SELECT ON bsunits_beloan_final.{t} TO 'unified_beloan_sales_reader'@'%'")
print('Local Beloan sales reader created; no write or loan permissions granted.')
