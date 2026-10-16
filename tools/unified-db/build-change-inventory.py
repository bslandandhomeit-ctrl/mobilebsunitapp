"""Schema metadata + conservative static dependency inventory; no application writes."""
import csv,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'outputs/unified-db/change-inventory';OUT.mkdir(exist_ok=True)
audit=json.loads((ROOT/'outputs/unified-db/source-audit.json').read_text())['sources']
final={}
for line in (OUT/'final-schema-metadata.tsv').read_text().splitlines():
 t,c,typ,null,key=line.split('\t');final.setdefault(t,{})[c]=(typ,null)
roots={'bsunits':Path('/Users/ramonoem/Documents/project/webbsunitapp'),'beloan':Path('/Users/ramonoem/Documents/project/beloan-code'),'mobile':ROOT}
# Actual promoted targets are established by migration reports, not guessed from naming.
be_targets={}
for filename in ('financial-auxiliary-result','ledger-history-result'):
 report=json.loads((ROOT/'outputs/unified-db'/f'{filename}.json').read_text())
 tables=report.get('tables',{})
 for target in tables:
  be_targets['tb_'+('currency' if target=='currencies' else target)]=target
be_targets.update({'tb_units':'units','tb_projects':'projects','tb_unit_types':'unit_types','tb_clients':'customer_accounts','tb_client_cbc_general':'persons','tb_client_cbc_identification':'customer_account_evidence','tb_client_cbc_contact':'customer_account_evidence','tb_client_cbc_address':'customer_account_evidence','tb_company_branch':'branches','tb_sale_order':'sales','tb_sale_items':'sale_items','tb_client_loan_accounts':'loan_accounts','tb_drawdown_account':'drawdown_accounts','tb_loans':'loans','tb_users':'actors'})
bs_targets={'companies':'organisations','projects':'projects','unit_types':'unit_types','units':'units','users':'actors'}
bs_targets.update({t:t for t in json.loads((ROOT/'outputs/unified-db/request-history-result.json').read_text())['tables']})
bs_targets['unit_actions']='unit_actions'
be_targets['tb_permission']='access_permissions'
bs_targets.update({'model_has_roles':'access_user_roles','model_has_permissions':'access_user_permissions','role_has_permissions':'access_role_permissions'})
# Access tables have semantic/application scopes; explicit multi-table destinations.
access={'roles':'access_roles','permissions':'access_permissions','role_user':'access_user_roles','permission_role':'access_role_permissions','permission_user':'access_user_permissions','user_permission':'access_user_permissions'}
for t,target in access.items():
 if target in final: bs_targets[t]=target;be_targets['tb_'+t]=target
files={}
for project,root in roots.items():
 folders=('lib',) if project=='mobile' else ('app','config','routes','resources/views','helpers')
 items=[]
 for folder in folders:
  for p in (root/folder).rglob('*') if (root/folder).exists() else []:
   if p.is_file() and p.suffix in ('.php','.dart'):
    lines=p.read_text(errors='replace').splitlines();items.append((str(p),lines,'\n'.join(lines)))
 files[project]=items

def features(table):
 t=table.removeprefix('tb_')
 if t in ('units','unit_types','projects','companies','company_branch'):return 'Units list/detail/search; project/type management; price editing; availability; sale/loan unit selection'
 if t in ('users','roles','permissions','role_user','permission_role','permission_user','user_permission'):return 'Login/logout; disabled accounts; roles; approval permissions; historical author display'
 if 'contract' in t:return 'Contract request create/list/detail/approval; attachments; PDF preview/download; signed document history'
 if 'deposit' in t:return 'Deposit four-step form/list/detail; manager/controller approvals; discounts; approved-request sale creation/retry'
 if 'hold' in t:return 'Hold create/list/detail; expiry/release/cancel; unit availability'
 if 'client' in t or 'cbc' in t:return 'Customer search/create/edit; joint accounts; identity evidence; contacts; attachments; loan customer selection'
 if 'sale' in t or 'invoice' in t:return 'Sale create/detail/change unit; discounts; invoices; approval-to-sale retry'
 if 'loan' in t or 'drawdown' in t or 'repayment' in t:return 'Loan creation/account/drawdown; stored schedules; interest/penalty; repayment/restructure/payoff; historical reports'
 if any(x in t for x in ('journal','transaction','till','coa','cash','currency')):return 'Posting/debit-credit totals; branch/currency filters; cashier close; receipts; financial reports; reconciliation'
 return 'Owner feature requires review; create/read/update/delete/export and role checks where used'

def mapping(project,table,column):
 target=(bs_targets if project=='bsunits' else be_targets).get(table)
 if not target or target not in final:return '', '', 'PENDING_TARGET_DESIGN', 'Retained in raw staging; operational destination is not verified. Do not drop or repoint this feature yet.'
 cols=final[target];base=table.removeprefix('tb_'); dest=column
 action='RETAIN_COLUMN';impact='Name retained; verify query table, source IDs, relations, validation and reports before cutover.'
 if base=='units':
  if column=='price':dest='list_price';action='RENAME_AND_AUTHORITY';impact='Current unit price comes from latest BS Units; Beloan historical agreed loan/sale amounts must stay in transaction fields.'
  elif column=='saleable':dest='is_saleable';action='RENAME_SEMANTIC_CHECK';impact='Catalog eligibility only. Availability comes from current BS Units action, not this flag.'
  elif column=='unit_action_id':dest='current_action_id';action='RENAME_REFERENCE';impact='Use latest BS Units action pointer; retest hold/deposit/contract state transitions.'
  elif column=='status':dest='availability_state';action='AUTHORITY_ADAPTER';impact='Beloan status is not authoritative for current availability. Preserve financial workflow statuses separately.'
  elif column in ('id','unit_type_id'):action='SOURCE_ID_MAPPING';impact='Beloan IDs must resolve through source aliases; never join old IDs directly to merged units/types.'
  elif column in ('street','street_corner','street_size','floor','land_size_width','land_size_length','land_area','building_size_width','building_size_length','building_area','gross_area','total_area_size','living_room','kitchen','bedroom','bathroom','swimming_pool','block','unit_zone','construction_overall_progress'):dest='specifications.'+column;action='JSON_COLUMN_ADAPTER';impact='Latest BS Units value for current units; legacy-only specifications preserve Beloan values.'
  elif column not in cols:dest='source lineage / pending operational field';action='JSON_OR_LINEAGE_REVIEW';impact='Physical BS fields may be in specifications; Beloan-only operational fields require explicit mapping, not blind JSON writes.'
 elif base=='projects':
  if column in ('short_code','code'):dest='code';action='NAME_COMPATIBILITY'
  elif column=='company_id':
   dest='organisation_id' if project=='bsunits' else 'project_accounting_branch_links.branch_id';action='SEPARATE_COMPANY_BRANCH';impact='BS company and Beloan accounting branch are independent. Do not merge ownership by name or ID.'
  elif column=='published':dest='is_published';action='RENAME_COLUMN'
  elif column in ('name','dealer'):dest='name_km';action='RENAME_COLUMN'
  elif column=='dealer_en':dest='name_en';action='RENAME_COLUMN'
  elif column=='dealer_cn':dest='name_zh';action='RENAME_COLUMN'
  elif column not in cols:dest='attributes / source lineage (field-level review)';action='JSON_OR_LINEAGE_REVIEW'
 elif base=='unit_types':
  if column=='short_code':dest='code';action='RENAME_COLUMN'
  elif column=='name':dest='name_km';action='RENAME_COLUMN'
  elif column in ('id','project_id'):action='SOURCE_ID_MAPPING'
  elif column not in cols:dest='attributes / source lineage (field-level review)';action='JSON_OR_LINEAGE_REVIEW'
 elif base in ('companies','company_branch'):
  if column=='tax_no':dest='tax_number';action='RENAME_COLUMN'
  elif column=='branch_code':dest='code';action='RENAME_COLUMN'
  elif column in ('branch_name','branch_name_en'):dest='name';action='COALESCE_NAME_ADAPTER'
  elif column not in cols:dest='attributes / source lineage (field-level review)';action='JSON_OR_LINEAGE_REVIEW'
 elif target.startswith('access_'):
  dest='source-qualified IDs / '+target+' / attributes snapshot';action='SCOPED_PERMISSION_ADAPTER';impact='Preserve app scope, polymorphic model type, role levels and approval flags; do not merge privileges by name.'
 elif base=='users':
  dest='login_identities.password_hash' if column=='password' else 'login_identities / actors.profile / source lineage'
  action='AUTHENTICATION_ADAPTER';impact='Preserve source-qualified login and actor identities; do not merge phone/name matches; preserve app-scoped permissions.'
 elif base=='clients':
  dest={'id':'id','cus_acc':'account_number','client_name':'display_name','account_cbc_type':'account_type'}.get(column,'source profile / pending operational adapter')
  action='CUSTOMER_ACCOUNT_ADAPTER';impact='Customer accounts stay separate from deduplicated people. Joint account semantics and CBC completeness require validation.'
 elif base=='client_cbc_general':
  dest={'id':'person_source_map + persons.id','client_id':'customer_account_members.account_id','date_of_birth':'birthdate','gender':'gender'}.get(column,'persons names / customer_source_profiles snapshot')
  action='PERSON_MEMBERSHIP_ADAPTER';impact='Only approved strict identity matches merge people. Preserve submitted/signed values and map original profile IDs.'
 elif base in ('client_cbc_identification','client_cbc_contact','client_cbc_address'):
  dest='customer_account_evidence snapshot / source lineage';action='EVIDENCE_ADAPTER';impact='Account-scoped evidence retained; normalised editing/search and person ownership require reviewed adapter.'
 elif target in ('sales','loan_accounts','drawdown_accounts','loans') and column=='client_id':dest='customer_account_id';action='RENAME_REFERENCE';impact='Use preserved account IDs; do not substitute deduplicated person IDs.'
 elif target=='loans' and column=='contract_id':dest='legacy_contract_number';action='RENAME_SEMANTIC_CHECK';impact='This is a historical contract-number string, not BS contracts.id.'
 elif project=='beloan' and column in ('unit_id','project_id','unit_type_id'):
  action='SOURCE_ID_MAPPING';impact='Canonical property references remapped; raw SQL, validators, relationships and exports must use the same mapped IDs.'
 elif column not in cols:dest='semantic target / raw lineage (manual review)';action='SEMANTIC_MAPPING_REVIEW';impact='Field has no direct same-name canonical column; review actual promoted layout before wiring reads or writes.'
 if target!=table and action=='RETAIN_COLUMN':action='TABLE_RENAME_COLUMN_RETAINED'
 if dest in cols and (cols[dest][0],cols[dest][1])!=source_columns[project][table][column]:
  impact+=' Target type/nullability differs; verify casts and validation.'
 return target,dest,action,impact
source_columns={}
for project,data in audit.items():
 source_columns[project]={}
 for t,c,typ,null,*_ in data['schema']:source_columns[project].setdefault(t,{})[c]=(typ,null)
# Lexical file/line evidence deliberately distinguishes candidates from proven changes.
evidence=[];table_files={};model_names={}
for project in ('bsunits','beloan'):
 for path,lines,body in files[project]:
  if 'extends Model' not in body and 'extends Authenticatable' not in body:continue
  cls=re.search(r'class\s+(\w+)',body);explicit=re.search(r'\$table\s*=\s*[\'\"]([^\'\"]+)',body)
  if not cls:continue
  name=cls.group(1);snake=re.sub(r'(?<!^)(?=[A-Z])','_',name).lower()
  plural=snake[:-1]+'ies' if snake.endswith('y') else snake+'s'
  source_names=source_columns[project]
  singular_physical=('tb_'+snake if project=='beloan' else snake)
  logical=explicit.group(1) if explicit else (snake if singular_physical in source_names else plural)
  physical=('tb_'+logical if project=='beloan' and not logical.startswith('tb_') else logical)
  if physical in source_columns[project]:model_names.setdefault((project,physical),set()).add(name)
 for table in source_columns[project]:
  logical=table.removeprefix('tb_');names=model_names.get((project,table),set());matched=set()
  # Bare table tokens can also denote a variable; this is candidate evidence, not AST resolution.
  pat=re.compile(r'(?<![\w])(?:'+ '|'.join(re.escape(x) for x in ({table,logical}|names))+r')(?![\w])')
  for path,lines,body in files[project]:
   if not pat.search(body):continue
   hits=[(i,line) for i,line in enumerate(lines,1) if pat.search(line)]
   matched.add(path)
   for i,line in hits:
    kind='MODEL_OR_TABLE_TOKEN_CANDIDATE'
    evidence.append([project,table,path,i,kind,'Table/model token reference; inspect locally at this line'])
  table_files[(project,table)]=sorted(matched)
columns=[];tables=[]
for project in ('bsunits','beloan'):
 for table,cols in source_columns[project].items():
  target=(bs_targets if project=='bsunits' else be_targets).get(table,'')
  actions=set()
  for column,(typ,null) in cols.items():
   to,dest,action,impact=mapping(project,table,column);actions.add(action)
   columns.append([project,table,column,typ,null,to,dest,action,impact,features(table),'; '.join(table_files[(project,table)])])
  tables.append([project,table,target,'VERIFIED_TARGET_EXISTS' if target in final else 'PENDING_TARGET_DESIGN',len(cols),len(table_files[(project,table)]),'; '.join(sorted(actions)),features(table)])
mobile=[]
for path,lines,body in files['mobile']:
 if '/features/' not in path and '/core/' not in path:continue
 for i,line in enumerate(lines,1):
  if re.search(r'fromJson|toJson|json\[|/api/|baseUrl|unit_id|customer|contract|deposit|hold',line,re.I):
   mobile.append(['mobile',path,i,'API_CONTRACT_CANDIDATE','API/DTO/feature token reference; inspect locally at this line','Retain current API field names and IDs/response structure; backend DB rename alone does not require a Dart edit. Retest owning feature.'])
for name,header,rows in [
 ('table-changes.csv',['project','old_table','current_target_table','target_status','source_columns','candidate_code_files','change_categories','features_to_retest'],tables),
 ('column-changes.csv',['project','old_table','old_column','old_type','old_nullable','current_target_table','target_column_or_review_destination','change_category','impact','features_to_retest','candidate_code_files'],columns),
 ('code-references.csv',['project','old_table','absolute_file','line','evidence_type','source_excerpt'],evidence),
 ('mobile-api-references.csv',['project','absolute_file','line','evidence_type','source_excerpt','impact'],mobile)]:
 with (OUT/name).open('w',newline='') as f:w=csv.writer(f);w.writerow(header);w.writerows(rows)
# Group evidence by file and expose actual changed-column token line candidates.
by_file={}
for project,table,path,line,kind,excerpt in evidence:
 by_file.setdefault((project,path),set()).add(table)
file_cache={path:lines for project in files for path,lines,body in files[project]}
column_evidence=[]
for project,table,column,typ,null,target,dest,action,impact,retest,candidates in columns:
 if action in ('RETAIN_COLUMN','TABLE_RENAME_COLUMN_RETAINED'):continue
 pattern=re.compile(r'(?<![\w])'+re.escape(column)+r'(?![\w])')
 for path in table_files[(project,table)]:
  hits=[str(i) for i,line in enumerate(file_cache[path],1) if pattern.search(line)]
  if hits:column_evidence.append([project,table,column,target,dest,path,';'.join(hits),'COLUMN_TOKEN_CANDIDATE_IN_TABLE_OR_MODEL_CANDIDATE_FILE'])
with (OUT/'column-code-references.csv').open('w',newline='') as f:
 w=csv.writer(f);w.writerow(['project','source_table','source_column','current_target_table','target_column_or_review_destination','absolute_file','candidate_lines','evidence_type']);w.writerows(column_evidence)
with (OUT/'code-file-impact.csv').open('w',newline='') as f:
 w=csv.writer(f);w.writerow(['project','absolute_file','candidate_source_tables','features_to_retest','review_status'])
 for (project,path),tableset in sorted(by_file.items()):
  w.writerow([project,path,'; '.join(sorted(tableset)),'; '.join(sorted({features(t) for t in tableset})),'STATIC_CANDIDATE_REQUIRES_TRIAGE'])
summary={'source_table_counts':{p:len(source_columns[p]) for p in source_columns},'column_rows':len(columns),'code_evidence_rows':len(evidence),'mobile_evidence_rows':len(mobile),'column_code_reference_rows':len(column_evidence),'candidate_backend_files':len(by_file),'scanned_files':{p:len(v) for p,v in files.items()},'pending_target_tables':sum(1 for r in tables if r[3]=='PENDING_TARGET_DESIGN'),'scope':'All source schema columns from verified audit. Static table/model token candidates are not proven runtime dependencies. Includes views and archived/unused code. Final destination names are current rehearsal design, still revisable.'}
(OUT/'inventory-summary.json').write_text(json.dumps(summary,indent=2)+'\n');print(json.dumps(summary,indent=2))
