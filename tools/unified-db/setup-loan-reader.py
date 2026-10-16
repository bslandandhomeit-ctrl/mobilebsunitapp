import importlib.util
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
m.target("CREATE USER IF NOT EXISTS 'unified_beloan_loan_reader'@'%' IDENTIFIED BY 'local-only-unified-loan-reader';")
for table in ('loans','customer_accounts','branches','projects','units','repayment_schedule','loan_payments'):
 m.target("GRANT SELECT ON bsunits_beloan_final."+table+" TO 'unified_beloan_loan_reader'@'%';")
print('Read-only loan/schedule/payment account prepared.')
