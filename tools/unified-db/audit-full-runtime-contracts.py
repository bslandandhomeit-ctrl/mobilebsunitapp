"""Inventory explicit model/raw table contracts before full runtime cutover. Metadata only."""
import importlib.util,json,re,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
meta=m.target("SELECT TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA IN ('bsunits_migration_stage','beloan_migration_stage','bsunits_beloan_final') ORDER BY TABLE_SCHEMA,TABLE_NAME,ORDINAL_POSITION")
schemas={}
for row in meta:
 schema,table,col=row.split('\t');schemas.setdefault(schema,{}).setdefault(table,[]).append(col)
final=schemas['bsunits_beloan_final'];apps={}
modelNames=set()
for root in [Path('/Users/ramonoem/Documents/project/webbsunitapp/app'),Path('/Users/ramonoem/Documents/project/beloan-code/app')]:
 for path in root.rglob('*.php'):
  if 'Models' in path.relative_to(root).parts or path.parent==root:
   modelNames.update(re.findall(r'class\s+(\w+)\s+extends',path.read_bytes().decode('utf8',errors='replace')))
payload='<?php require \'/var/www/html/vendor/autoload.php\'; $names=json_decode(\''+json.dumps(sorted(modelNames))+'\',true);$out=array();foreach($names as $name)$out[$name]=Illuminate\\Support\\Str::snake(Illuminate\\Support\\Str::plural($name));echo json_encode($out);'
pluralized=subprocess.run(['docker','exec','-i','beloan-local','php'],input=payload,text=True,capture_output=True,check=True)
defaultTables=json.loads(pluralized.stdout)

for app,root,source,prefix in [('bsunits',Path('/Users/ramonoem/Documents/project/webbsunitapp'),'bsunits_migration_stage',''),('beloan',Path('/Users/ramonoem/Documents/project/beloan-code'),'beloan_migration_stage','tb_')]:
 usages={}
 for path in sorted((root/'app').rglob('*.php')):
  code=path.read_bytes().decode('utf8',errors='replace')
  if ('Models' in path.relative_to(root/'app').parts or path.parent==root/'app') and not re.search(r'\$table\s*=',code):
   match=re.search(r'class\s+(\w+)\s+extends',code)
   if match:
    table=defaultTables[match.group(1)];usages.setdefault(table,[]).append({'file':str(path),'line':code[:match.start()].count('\n')+1,'kind':'inferred_model_default'})
  patterns=[('model',r'(?:protected|public)\s+\$table\s*=\s*[\'\"]([A-Za-z0-9_]+)[\'\"]'),('raw_query',r'(?:DB|\\?Illuminate\\Support\\Facades\\DB)::table\(\s*[\'\"]([A-Za-z0-9_]+)(?:\s+(?:as\s+)?\w+)?[\'\"]')]
  for kind,pattern in patterns:
   for match in re.finditer(pattern,code):usages.setdefault(match.group(1),[]).append({'file':str(path),'line':code[:match.start()].count('\n')+1,'kind':kind})
 rows=[]
 for table,locations in sorted(usages.items()):
  original=schemas[source].get(prefix+table,[]);same=final.get(table,[]);missing=[col for col in original if col not in same]
  candidates=[name for name in [table,prefix+table,'beloan_compat_'+table,'beloan_ui_'+table,'beloan_reference_'+table] if name in final]
  rows.append({'original_table':table,'source_exists':bool(original),'same_name_final_exists':bool(same),'missing_same_name_columns':missing,'available_final_contracts':list(dict.fromkeys(candidates)),'usage_count':len(locations),'locations':locations,'needs_runtime_mapping_review':bool(original) and (not same or bool(missing))})
 apps[app]={'static_tables':len(rows),'table_contracts_needing_review':sum(r['needs_runtime_mapping_review'] for r in rows),'contracts':rows}
report={'scope':'All app PHP explicit table properties and literal DB::table calls. Static metadata audit; includes framework-pluralized model defaults; does not resolve inherited/custom getTable overrides or dynamic SQL or account for completed connection adapters. Review entries are not all implementation defects. No runtime mutation.','apps':apps,'full_cutover_ready':False}
(HERE.parents[1]/'outputs/unified-db/full-runtime-contract-audit.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({app:{k:v for k,v in values.items() if k!='contracts'} for app,values in apps.items()},indent=2))
