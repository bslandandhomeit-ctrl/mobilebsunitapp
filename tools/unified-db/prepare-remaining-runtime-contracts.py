"""Prepare original Beloan table contracts over final records; no runtime activation.

Shared masters/authentication remain explicit unresolved gates. Non-shared operational
extensions are preserved from the frozen snapshot, not read through a source database.
"""
import importlib.util,json
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
OUT=HERE.parents[1]/'outputs/unified-db/remaining-runtime-contract-preparation.json'
SHARED={'users','roles','permission','user_permission','clients','company_branch','projects','units','unit_types','project','sale_representatives','banks','payment_options'}
ALIASES={'sale_order':'sales','client_loan_accounts':'loan_accounts','drawdown_account':'drawdown_accounts','currency':'currencies'}
RENAMES={'sale_order':{'client_id':'customer_account_id'},'client_loan_accounts':{'client_id':'customer_account_id'},'drawdown_account':{'client_id':'customer_account_id'},'loans':{'client_id':'customer_account_id','contract_id':'legacy_contract_number'}}

def columns(schema,table):
 return m.target('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='+m.literal(schema)+' AND TABLE_NAME='+m.literal(table)+' ORDER BY ORDINAL_POSITION')
def kind(table):
 values=m.target('SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='+m.literal(table))
 return values[0] if values else None

def run():
 audit=json.loads((HERE.parents[1]/'outputs/unified-db/full-runtime-contract-audit.json').read_text())
 previous=json.loads(OUT.read_text()) if OUT.exists() else {'contracts':{}}
 results=previous['contracts'];pending=[]
 for item in audit['apps']['beloan']['contracts']:
  name=item['original_table']
  if not item['source_exists']:continue
  if not name.replace('_','').isalnum():raise RuntimeError('Unsafe metadata identifier')
  source='tb_'+name;original=columns('beloan_migration_stage',source)
  if name in SHARED:
   pending.append({'contract':source,'reason':'Shared master or scoped authentication needs explicit write adapter; no independent copy created.'});continue
  target=None;expressions=None
  for candidate in [ALIASES.get(name,name),'beloan_ui_'+name,'beloan_compat_'+name,'beloan_reference_'+name]:
   available=columns(m.TARGET_DATABASE,candidate)
   renamed=RENAMES.get(name,{}) if candidate==ALIASES.get(name,name) else {}
   actual=[renamed.get(col,col) for col in original]
   if available and all(col in available for col in actual):
    target=candidate;expressions=['`'+actual[i]+'` AS `'+col+'`' for i,col in enumerate(original)];break
  preserved=False
  if target is None:
   target='beloan_runtime_'+name
   if kind(target):
    if results.get(source,{}).get('canonical_table')!=target:raise RuntimeError('Unverified extension already exists; refusing overwrite')
   else:
    m.target('CREATE TABLE `'+target+'` LIKE beloan_migration_stage.`'+source+'`;INSERT INTO `'+target+'` SELECT * FROM beloan_migration_stage.`'+source+'`;')
   expressions=['`'+col+'`' for col in original];preserved=True
   # Count plus digest of every original stored field. No values enter reports.
   expr='SHA2(JSON_OBJECT('+','.join(m.literal(col)+',`'+col+'`' for col in original)+'),256)'
   digest='SELECT COUNT(*),COALESCE(SUM(CAST(CONV(SUBSTRING('+expr+',1,15),16,10) AS DECIMAL(30,0))),0),COALESCE(SUM(CAST(CONV(SUBSTRING('+expr+',16,15),16,10) AS DECIMAL(30,0))),0) FROM '
   if m.target(digest+'`'+target+'`')!=m.target(digest+'beloan_migration_stage.`'+source+'`'):raise RuntimeError('Extension preservation checksum differs')
  current_kind=kind(source)
  if current_kind and current_kind!='VIEW':raise RuntimeError('Original contract name occupied; refusing replacement')
  m.target('CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW `'+source+'` AS SELECT '+','.join(expressions)+' FROM `'+target+'`;')
  if columns(m.TARGET_DATABASE,source)!=original:raise RuntimeError('Contract column/order mismatch')
  info=m.target('SELECT IS_UPDATABLE FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='+m.literal(source))
  results[source]={'canonical_table':target,'original_columns':len(original),'operational_extension_preserved':preserved,'updatable_view':info==['YES'],'source_schema_read_by_view':False}
  OUT.write_text(json.dumps({'status':'PREPARING','contracts':results,'unresolved_shared_contracts':pending,'runtime_activated':False},indent=2)+'\n')
 report={'status':'PREPARED_WITH_EXPLICIT_SHARED_GATES','contracts':results,'unresolved_shared_contracts':pending,'runtime_activated':False,'source_databases_changed':False,'financial_values_changed':False,'scope':'Original Beloan names resolve within final DB to existing canonical records or source-preserved non-shared operational extensions. No duplicate property/customer/sale/loan masters copied. This is schema preparation, not permission or workflow coverage; shared master/authentication writes remain blocked.'}
 OUT.write_text(json.dumps(report,indent=2)+'\n')
 print(json.dumps({'status':report['status'],'prepared_contracts':len(results),'unresolved_shared_contracts':len(pending),'runtime_activated':False},indent=2))
if __name__=='__main__':run()
