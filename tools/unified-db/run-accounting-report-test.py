"""Run original trial balance calculations against source snapshots and unified read views."""
import importlib.util,json,subprocess,re
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_accounting_report_test';USER='unified_accounting_test'
if m.target("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME="+m.literal(DB)):raise RuntimeError('Scratch exists; refusing overwrite.')
created=False;user_created=False
try:
    m.target('CREATE DATABASE '+DB);created=True
    m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-accounting-test'");user_created=True
    m.target("GRANT SELECT ON "+DB+".* TO '"+USER+"'@'%'")
    tables=['journal_detail','journal_requiry','coa_categories']
    for schema,names in [('beloan_migration_stage',['tb_'+t for t in tables]+['tb_company_branch']),('bsunits_beloan_final',tables+['branches'])]:
        for table in names:m.target("GRANT SELECT ON "+schema+'.'+table+" TO '"+USER+"'@'%'")
    results=[]
    for source in ['original','unified']:
        for table in tables:
            target='beloan_migration_stage.tb_'+table if source=='original' else 'bsunits_beloan_final.'+table
            m.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW '+DB+'.tb_'+table+' AS SELECT * FROM '+target)
        definition='SELECT * FROM beloan_migration_stage.tb_company_branch' if source=='original' else "SELECT id,code AS branch_code,name AS branch_name,JSON_UNQUOTE(JSON_EXTRACT(attributes,'$.status')) AS status FROM bsunits_beloan_final.branches"
        m.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW '+DB+'.tb_company_branch AS '+definition)
        result=subprocess.run(['docker','exec','-i','beloan-local','php','-d','memory_limit=1024M','-d','display_errors=stderr'],input=(HERE/'test-original-accounting-report.php').read_bytes(),capture_output=True)
        if result.returncode:
            diagnostic=result.stdout.decode(errors='replace')
            try:
                failure=json.loads(diagnostic)
                if 'error' in failure:raise RuntimeError('Original accounting report: '+failure['error']+' '+failure['message']+' at '+failure['location'])
            except ValueError:pass
            markers=re.findall(r'Undefined variable|Argument 1|ErrorException|TypeError|SQLSTATE\[[^]]+\]|getUserByBranch|PDOException',diagnostic)
            raise RuntimeError('Original accounting report failed: '+result.stderr.decode(errors='replace')[-1000:]+' markers='+str(sorted(set(markers)))+' bytes='+str(len(result.stdout)))
        results.append(json.loads(result.stdout))
    if results[0]!=results[1]:raise RuntimeError('Trial balance calculations differ; financial rows withheld.')
    report={'status':'PASSED','original_trial_balance_calculation':True,'source_unified_amount_parity':True,'coa_rows':len(results[0]['rows']),'scope':'Corrected September USD calculation (date/audit/branch helper) through disposable read-only aliases; live report cutover, UI and full permissions remain pending.'}
    (HERE.parents[1]/'outputs/unified-db/accounting-report-test-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
finally:
    if created:m.target('DROP DATABASE '+DB)
    if user_created:m.target("DROP USER '"+USER+"'@'%'")
