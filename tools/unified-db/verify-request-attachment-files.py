"""Read-only local attachment verification; never output personal filenames."""
import importlib.util,json,urllib.request,urllib.parse
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
ROOT=Path('/Users/ramonoem/Documents/project/webbsunitapp/storage/app/public').resolve()
report={};http_samples=[]
for table in ['unit_contract_request_attachments','contract_attachments','contract_request_attachments']:
 columns=m.target('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='+m.literal(table))
 if 'path' not in columns:raise RuntimeError('Attachment path schema not supported: '+table)
 rows=m.target('SELECT id,path'+(',deleted_at' if 'deleted_at' in columns else '')+' FROM '+table)
 counts={'records':len(rows),'present_nonempty':0,'missing':0,'empty':0,'unsafe_paths':0,'active_missing':0,'deleted_missing':0}
 for row in rows:
  cells=row.split('\t');raw=cells[1];deleted=len(cells)>2 and cells[2]!='NULL'
  if raw=='NULL' or not raw:counts['missing']+=1;counts['deleted_missing' if deleted else 'active_missing']+=1;continue
  rel=Path(raw);path=(ROOT/rel).resolve()
  if rel.is_absolute() or ROOT not in path.parents:counts['unsafe_paths']+=1;continue
  if not path.is_file():counts['missing']+=1;counts['deleted_missing' if deleted else 'active_missing']+=1;continue
  if path.stat().st_size==0:counts['empty']+=1;continue
  counts['present_nonempty']+=1
  if table=='unit_contract_request_attachments' and not deleted and len(http_samples)<20:http_samples.append((raw,path.stat().st_size))
 report[table]=counts
http={'samples':len(http_samples),'http_200':0,'length_matches_local':0,'nonempty_response':0,'failed':0}
for raw,size in http_samples:
 try:
  with urllib.request.urlopen('http://127.0.0.1:8000/storage/'+urllib.parse.quote(raw,safe='/'),timeout=15) as response:
   if response.status==200:http['http_200']+=1
   if int(response.headers.get('Content-Length',-1))==size:http['length_matches_local']+=1
   if response.read(64):http['nonempty_response']+=1
 except Exception:http['failed']+=1
result={'status':'VERIFIED' if all(not x['active_missing'] and not x['empty'] and not x['unsafe_paths'] for x in report.values()) and http['failed']==0 and http['http_200']==http['samples'] else 'REVIEW_REQUIRED','tables':report,'http_delivery':http,'scope':'Local public-storage files and sampled HTTP delivery for preserved attachment paths. Missing files are reported, never synthesized; production and database records unchanged.'}
(HERE.parents[1]/'outputs/unified-db/request-attachment-file-verification.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
