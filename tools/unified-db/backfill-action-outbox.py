"""Queue catalog/source pointer differences only; no financial or price changes."""
import importlib.util,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
canonical={int(r.split('\t')[0]):r.split('\t')[1:] for r in m.target("SELECT id,COALESCE(current_action_id,0),is_saleable,IF(deleted_at IS NULL,0,1) FROM units WHERE catalog_state='CURRENT'")}
c,p,d,_=m.SOURCES['bsunits'];source=m.mysql(c,p,d,'SELECT id,COALESCE(unit_action_id,0),IF(saleable=1 AND deleted_at IS NULL,1,0),IF(deleted_at IS NULL,0,1) FROM units')
queued=0;missing=0
for line in source:
 fields=line.split('\t');id=int(fields[0]);act=int(fields[1]);old=canonical.get(id)
 if old is None:continue
 if fields[1:]!=old:
  if not act:missing+=1;continue
  m.mysql(c,p,d,f'INSERT INTO unified_unit_action_outbox (unit_id,action_id,queued_at) VALUES ({id},{act},NOW()) ON DUPLICATE KEY UPDATE action_id=VALUES(action_id),queued_at=VALUES(queued_at)');queued+=1
report={'queued_unit_differences':queued,'missing_action_needing_review':missing,'scope':'Current BS unit pointer/eligibility only; no prices or historical financial amounts changed.'}
Path('outputs/unified-db/action-outbox-backfill-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
if missing:raise RuntimeError('Missing current action requires review before synchronization activation.')
