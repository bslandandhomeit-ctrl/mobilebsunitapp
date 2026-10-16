import importlib.util,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
fields={'loans':('tb_loans',('loan_amount','unit_sale_price','final_price','ending_balance','interest_rate','loan_duration')),'repayment_schedule':('tb_repayment_schedule',('loan_id','schedule_date','total_payment','principal','interest','fee','other_fee','balance','no')),'loan_payments':('tb_loan_payments',('loan_id','repayment_date','paid_principal','paid_interest','penalty_amount','waived_penalty'))}
report={}
for table,(old,cols) in fields.items():
 query='SELECT COUNT(*) FROM '+table+' n JOIN beloan_migration_stage.'+old+' o ON o.id=n.id WHERE NOT ('+' AND '.join('n.`'+c+'` <=> o.`'+c+'`' for c in cols)+');'
 differences=int(m.target(query)[0]);counts=m.target('SELECT (SELECT COUNT(*) FROM '+table+'),(SELECT COUNT(*) FROM beloan_migration_stage.'+old+');')[0].split('\t')
 report[table]={'rows':int(counts[0]),'source_rows':int(counts[1]),'displayed_field_mismatches':differences}
 if differences or counts[0]!=counts[1]:raise RuntimeError('Financial read preservation check failed: '+table)
report['source_missing_schedule_dates']=int(m.target("SELECT COUNT(*) FROM repayment_schedule WHERE schedule_date='0000-00-00' OR schedule_date IS NULL;")[0])
report['status']='PASSED';report['scope']='Original stored amounts, dates, schedule sequence and repayment precision preserved. Missing legacy dates display N/A; no financial recalculation.'
(Path(__file__).resolve().parents[2]/'outputs/unified-db/loan-read-preservation-result.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
