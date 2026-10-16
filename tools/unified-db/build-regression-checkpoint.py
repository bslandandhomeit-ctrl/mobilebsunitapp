"""Summarize evidence freshness without equating isolated tests with live cutover."""
import json
from pathlib import Path
from datetime import datetime,timezone
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'outputs/unified-db'
NAMES={'original-customer-write-test-result':'PASSED','original-journal-write-test-result':'PASSED','original-cashier-controller-test-result':'PASSED','original-auto-repayment-test-result':'PASSED','receipt-concurrency-test-result':'PASSED','accounting-report-test-result':'PASSED','finance-adapter-readiness':'PRESERVED','shared-finance-reference-verification':'MAPPED_REFERENCES_VERIFIED','final-rehearsal-verification':'REHEARSAL_CHECKS_PASSED','unified-auth-provider-test-result':'PASSED'}
results={}
for name,expected in NAMES.items():
 p=OUT/(name+'.json');data=json.loads(p.read_text())
 if data.get('status')!=expected:raise RuntimeError('Failed evidence: '+name)
 results[name]={'status':data['status'],'evidence_updated_utc':datetime.fromtimestamp(p.stat().st_mtime,timezone.utc).isoformat()}
report={'generated_utc':datetime.now(timezone.utc).isoformat(),'status':'ISOLATED_REGRESSIONS_PASSED','results':results,'application_cutover_ready':False,'production_changed':False,'remaining':['Unified authentication writes/session and one finance transaction connection','Remaining original controllers, currencies/reports and permission UI tests','BS request CRUD and attachments on unified runtime','Full local cutover/rollback rehearsal'],'limits':'Evidence timestamps show freshness; isolated scratch tests and preserved data do not establish end-to-end runtime readiness. Concurrent receipt test covers the disabled unified allocator, not the original live MAX-number helper.'}
(OUT/'regression-checkpoint.json').write_text(json.dumps(report,indent=2)+'\n');print('Regression evidence validated; application cutover remains pending.')
